<?php
namespace App\Services;

use App\Models\AppSettings;

/**
 * GoPay Merchant (com.gojek.gopaymerchant) API Service
 * MikhPay Engine
 * 
 * Mengelola otentikasi (OTP / Manual Bearer Token), pembacaan riwayat mutasi QRIS,
 * dan auto-sinkronisasi transaksi pending secara mandiri tanpa bergantung pada push notification HP.
 */
class GoPayMerchantService {
    private $settings;
    private $processedFile;
    private $userAgent = 'GoPayMerchant/1.30.0 (Android 12; Mobile; id_ID)';
    private $appVersion = '1.30.0';

    public function __construct() {
        $this->settings = new AppSettings();
        $this->processedFile = dirname(__FILE__) . '/../../data/gopay_processed.json';
    }

    /**
     * Ambil status konfigurasi GoPay Merchant saat ini
     */
    public function getStatus() {
        $token = $this->settings->get('gopay_access_token', '');
        $phone = $this->settings->get('gopay_phone', '');
        $enabled = (bool)$this->settings->get('gopay_sync_enabled', false);
        $lastSync = $this->settings->get('gopay_last_sync', 0);
        $syncInterval = (int)$this->settings->get('gopay_sync_interval', 30);
        $authMode = $this->settings->get('gopay_auth_mode', 'otp');
        $merchantName = $this->settings->get('gopay_merchant_name', '');
        $merchantId = $this->settings->get('gopay_merchant_id', '');

        // Jika sudah ada token tapi merchant belum terdeteksi, deteksi otomatis
        if (!empty($token) && empty($merchantId)) {
            $mInfo = $this->getMerchantDetails($token);
            if ($mInfo) {
                $merchantId = $mInfo['id'];
                $merchantName = $mInfo['name'];
            }
        }

        return [
            'is_connected' => !empty($token),
            'phone' => $phone,
            'enabled' => $enabled,
            'merchant_name' => $merchantName,
            'merchant_id' => $merchantId,
            'last_sync' => $lastSync ? date('Y-m-d H:i:s', $lastSync) : 'Belum pernah',
            'last_sync_timestamp' => $lastSync,
            'sync_interval' => $syncInterval > 0 ? $syncInterval : 30,
            'auth_mode' => $authMode,
            'token_preview' => !empty($token) ? substr($token, 0, 10) . '...' . substr($token, -6) : ''
        ];
    }

    /**
     * Simpan pengaturan umum sinkronisasi
     */
    public function updateSyncSettings($enabled, $interval = 30) {
        $this->settings->set('gopay_sync_enabled', (bool)$enabled);
        $this->settings->set('gopay_sync_interval', max(10, (int)$interval));
        return true;
    }

    /**
     * Normalisasi nomor telepon ke format internasional Gojek (+62...)
     */
    public function formatPhoneNumber($phone) {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (substr($clean, 0, 1) === '0') {
            $clean = '62' . substr($clean, 1);
        } elseif (substr($clean, 0, 2) !== '62') {
            $clean = '62' . $clean;
        }
        return '+' . $clean;
    }

    /**
     * Dapatkan atau generate Unique ID perangkat yang konsisten
     */
    private function getUniqueId() {
        $uniqueId = $this->settings->get('gopay_unique_id', '');
        if (empty($uniqueId)) {
            $uniqueId = md5(uniqid('mikhpay_gopay_', true));
            $this->settings->set('gopay_unique_id', $uniqueId);
        }
        return $uniqueId;
    }

    /**
     * Langkah 1: Request OTP ke Gojek / GoPay Merchant
     */
    public function requestOtp($phone) {
        $formattedPhone = $this->formatPhoneNumber($phone);
        $phoneWithoutPlus = ltrim($formattedPhone, '+');
        $phoneNational = (substr($phoneWithoutPlus, 0, 2) === '62') ? substr($phoneWithoutPlus, 2) : $phoneWithoutPlus;

        $uniqueId = $this->getUniqueId();
        
        $payload = [
            'client_id' => 'gojek:cons:android',
            'client_secret' => '83415d06-ec4e-11e6-a41b-6c40088ab51e',
            'country_code' => '+62',
            'phone_number' => $phoneNational
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-AppVersion: ' . $this->appVersion,
            'X-DeviceOS: Android',
            'X-PhoneModel: Xiaomi Redmi Note 10',
            'X-UniqueId: ' . $uniqueId,
            'User-Agent: ' . $this->userAgent
        ];

        $response = $this->makeHttpRequest('https://api.gojekapi.com/v3/auth/token', 'POST', $payload, $headers);

        if ($response['success'] && isset($response['data']['data']['otp_token'])) {
            $otpToken = $response['data']['data']['otp_token'];
            // Simpan sementara nomor dan otp_token
            $_SESSION['gopay_temp_phone'] = $formattedPhone;
            $_SESSION['gopay_temp_otp_token'] = $otpToken;

            return [
                'success' => true,
                'message' => 'Kode OTP berhasil dikirimkan ke nomor ' . $formattedPhone . '. Silakan periksa SMS / WhatsApp Anda.',
                'otp_token' => $otpToken
            ];
        }

        // Coba endpoint alternatif jika endpoint di atas gagal
        $altPayload = ['phone_number' => $formattedPhone];
        $altResponse = $this->makeHttpRequest('https://api.gobiz.co.id/v1/auth/request_otp', 'POST', $altPayload, $headers);
        if ($altResponse['success']) {
            $_SESSION['gopay_temp_phone'] = $formattedPhone;
            $_SESSION['gopay_temp_otp_token'] = isset($altResponse['data']['otp_token']) ? $altResponse['data']['otp_token'] : 'gobiz';

            return [
                'success' => true,
                'message' => 'Kode OTP berhasil dikirim ke nomor ' . $formattedPhone . '.',
                'otp_token' => $_SESSION['gopay_temp_otp_token']
            ];
        }

        $rawError = isset($response['data']['errors'][0]['message']) 
            ? $response['data']['errors'][0]['message'] 
            : (isset($response['data']['message']) ? $response['data']['message'] : '');

        if (strpos($rawError, 'no Route matched') !== false || $response['code'] === 404) {
            $errorMsg = 'Server Gojek membatasi request OTP otomatis dari luar aplikasi resmi. Silakan gunakan Metode 2 (Input Bearer Token Manual) atau gunakan notifikasi MikhPay Forwarder.';
        } elseif (!empty($rawError)) {
            $errorMsg = $rawError;
        } else {
            $errorMsg = 'Gagal mengirim OTP. Pastikan nomor HP aktif dan terdaftar di GoPay Merchant.';
        }

        return [
            'success' => false,
            'message' => $errorMsg
        ];
    }

    /**
     * Langkah 2: Verifikasi OTP dan dapatkan Access Token
     */
    public function verifyOtp($otp, $otpToken = '') {
        if (empty($otpToken) && isset($_SESSION['gopay_temp_otp_token'])) {
            $otpToken = $_SESSION['gopay_temp_otp_token'];
        }
        $phone = isset($_SESSION['gopay_temp_phone']) ? $_SESSION['gopay_temp_phone'] : '';

        $cleanOtp = preg_replace('/[^0-9]/', '', $otp);
        if (strlen($cleanOtp) < 4) {
            return ['success' => false, 'message' => 'Kode OTP harus berupa angka 4-6 digit.'];
        }

        $uniqueId = $this->getUniqueId();
        $payload = [
            'client_id' => 'gojek:cons:android',
            'client_secret' => '83415d06-ec4e-11e6-a41b-6c40088ab51e',
            'grant_type' => 'otp',
            'otp' => $cleanOtp,
            'otp_token' => $otpToken
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-AppVersion: ' . $this->appVersion,
            'X-DeviceOS: Android',
            'X-PhoneModel: Xiaomi Redmi Note 10',
            'X-UniqueId: ' . $uniqueId,
            'User-Agent: ' . $this->userAgent
        ];

        $response = $this->makeHttpRequest('https://api.gojekapi.com/v3/auth/token', 'POST', $payload, $headers);

        if ($response['success']) {
            $data = $response['data'];
            $accessToken = '';
            $refreshToken = '';

            if (isset($data['data']['access_token'])) {
                $accessToken = $data['data']['access_token'];
                $refreshToken = isset($data['data']['refresh_token']) ? $data['data']['refresh_token'] : '';
            } elseif (isset($data['access_token'])) {
                $accessToken = $data['access_token'];
                $refreshToken = isset($data['refresh_token']) ? $data['refresh_token'] : '';
            }

            if (!empty($accessToken)) {
                $this->settings->set('gopay_access_token', $accessToken);
                $this->settings->set('gopay_refresh_token', $refreshToken);
                $this->settings->set('gopay_phone', $phone);
                $this->settings->set('gopay_auth_mode', 'otp');
                $this->settings->set('gopay_sync_enabled', true);

                unset($_SESSION['gopay_temp_phone']);
                unset($_SESSION['gopay_temp_otp_token']);

                if (function_exists('writeAppLog')) {
                    writeAppLog('GOPAY_AUTH', 'Akun GoPay Merchant (' . $phone . ') berhasil terhubung via OTP.');
                }

                return [
                    'success' => true,
                    'message' => 'Login Berhasil! Akun GoPay Merchant berhasil dihubungkan ke MikhPay.'
                ];
            }
        }

        $errorMsg = isset($response['data']['errors'][0]['message']) 
            ? $response['data']['errors'][0]['message'] 
            : (isset($response['data']['message']) ? $response['data']['message'] : 'Verifikasi OTP gagal. Periksa kembali kode OTP Anda.');

        return [
            'success' => false,
            'message' => $errorMsg
        ];
    }

    /**
     * Opsi Manual: Simpan Token Bearer / Cookie Sesi secara langsung
     */
    public function saveManualToken($token, $phone = '') {
        $cleanToken = trim($token);

        // Jika user mem-paste cookie browser lengkap (berisi access_token=...;)
        if (strpos($cleanToken, 'access_token=') !== false) {
            if (preg_match('/access_token=([^;]+)/', $cleanToken, $matches)) {
                $cleanToken = trim($matches[1]);
            }
            if (preg_match('/refresh_token=([^;]+)/', $token, $rMatches)) {
                $this->settings->set('gopay_refresh_token', trim($rMatches[1]));
            }
        }

        if (strpos($cleanToken, 'Bearer ') === 0) {
            $cleanToken = substr($cleanToken, 7);
        }

        $cleanToken = trim($cleanToken);

        if (empty($cleanToken) || strlen($cleanToken) < 15) {
            return ['success' => false, 'message' => 'Format Token tidak valid. Masukkan Bearer token sesi GoPay Anda.'];
        }

        // Simpan token ke database
        $this->settings->set('gopay_access_token', $cleanToken);
        if (!empty($phone)) {
            $this->settings->set('gopay_phone', $this->formatPhoneNumber($phone));
        }
        $this->settings->set('gopay_auth_mode', 'manual_token');
        $this->settings->set('gopay_sync_enabled', true);

        // Auto-deteksi merchant
        $mInfo = $this->getMerchantDetails($cleanToken);
        $merchantMsg = '';
        if ($mInfo) {
            $merchantMsg = " Terhubung ke merchant: {$mInfo['name']} ({$mInfo['id']}).";
        }

        if (function_exists('writeAppLog')) {
            writeAppLog('GOPAY_AUTH', 'Token manual GoPay Merchant berhasil disimpan.' . $merchantMsg);
        }

        return [
            'success' => true,
            'message' => 'Token GoPay Merchant berhasil disimpan dan auto-sync diaktifkan!' . $merchantMsg
        ];
    }

    /**
     * Putus koneksi akun GoPay Merchant
     */
    public function disconnect() {
        $phone = $this->settings->get('gopay_phone', '');
        $this->settings->set('gopay_access_token', '');
        $this->settings->set('gopay_refresh_token', '');
        $this->settings->set('gopay_phone', '');
        $this->settings->set('gopay_merchant_id', '');
        $this->settings->set('gopay_merchant_name', '');
        $this->settings->set('gopay_sync_enabled', false);

        if (function_exists('writeAppLog')) {
            writeAppLog('GOPAY_AUTH', 'Akun GoPay Merchant (' . $phone . ') diputuskan dari MikhPay.');
        }

        return ['success' => true, 'message' => 'Akun GoPay Merchant berhasil diputuskan.'];
    }

    /**
     * Deteksi atau ambil identitas Merchant GoBiz dari token aktif
     */
    public function getMerchantDetails($token = '') {
        if (empty($token)) {
            $token = $this->settings->get('gopay_access_token', '');
        }
        if (empty($token)) return null;

        $cachedId = $this->settings->get('gopay_merchant_id', '');
        $cachedName = $this->settings->get('gopay_merchant_name', '');
        if (!empty($cachedId)) {
            return ['id' => $cachedId, 'name' => $cachedName];
        }

        $uniqueId = $this->getUniqueId();
        $headers = [
            'Accept: application/json, text/plain, */*',
            'Authentication-Type: go-id',
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Origin: https://portal.gofoodmerchant.co.id',
            'Referer: https://portal.gofoodmerchant.co.id/',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',
            'X-AppVersion: platform-v3.107.0-94ce5d57',
            'X-Platform: Web',
            'X-User-Type: merchant',
            'x-appId: go-biz-web-dashboard',
            'x-uniqueid: ' . $uniqueId
        ];

        $payload = ['from' => 0, 'to' => 10, '_source' => ['id', 'merchant_name']];
        $resp = $this->makeHttpRequest('https://api.gobiz.co.id/v1/merchants/search', 'POST', $payload, $headers);

        if ($resp['success'] && isset($resp['data']['hits'][0]['id'])) {
            $mId = (string)$resp['data']['hits'][0]['id'];
            $mName = isset($resp['data']['hits'][0]['merchant_name']) ? (string)$resp['data']['hits'][0]['merchant_name'] : 'GoPay Merchant';
            $this->settings->set('gopay_merchant_id', $mId);
            $this->settings->set('gopay_merchant_name', $mName);
            return ['id' => $mId, 'name' => $mName];
        }

        return null;
    }

    /**
     * Ambil riwayat mutasi transaksi langsung dari GoPay
     */
    public function getRecentTransactions($limit = 15) {
        $token = $this->settings->get('gopay_access_token', '');
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Akun GoPay Merchant belum terhubung.',
                'transactions' => []
            ];
        }

        return $this->fetchTransactionsFromApi($token, $limit);
    }

    /**
     * Helper internal untuk memanggil endpoint mutasi GoPay via GoBiz Journals API
     */
    private function fetchTransactionsFromApi($token, $limit = 15) {
        $merchant = $this->getMerchantDetails($token);
        if (!$merchant || empty($merchant['id'])) {
            return [
                'success' => false,
                'message' => 'Gagal mendeteksi Merchant ID dari akun GoPay Anda. Pastikan token sesi valid.',
                'transactions' => []
            ];
        }

        $merchantId = $merchant['id'];
        $startTime = date('c', strtotime('-30 days'));
        $endTime = date('c');

        $requestBody = [
            'from' => 0,
            'size' => min(50, max(5, (int)$limit)),
            'sort' => [
                'time' => ['order' => 'desc']
            ],
            'included_categories' => [
                'incoming' => ['transaction_share', 'action']
            ],
            'query' => [
                [
                    'clauses' => [
                        [
                            'op' => 'not',
                            'clauses' => [
                                [
                                    'clauses' => [
                                        ['field' => 'metadata.source', 'op' => 'in', 'value' => ['GOSAVE_ONLINE', 'GoSave', 'GODEALS_ONLINE']],
                                        ['field' => 'metadata.gopay.source', 'op' => 'in', 'value' => ['GOSAVE_ONLINE', 'GoSave', 'GODEALS_ONLINE']]
                                    ],
                                    'op' => 'or'
                                ]
                            ]
                        ],
                        [
                            'field' => 'metadata.transaction.status',
                            'op' => 'in',
                            'value' => ['settlement', 'capture', 'refund', 'partial_refund']
                        ],
                        [
                            'op' => 'or',
                            'clauses' => [
                                [
                                    'op' => 'or',
                                    'clauses' => [
                                        [
                                            'field' => 'metadata.transaction.payment_type',
                                            'op' => 'in',
                                            'value' => ['qris', 'gopay', 'offline_credit_card', 'offline_debit_card', 'credit_card']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'field' => 'metadata.transaction.transaction_time',
                            'op' => 'gte',
                            'value' => $startTime
                        ],
                        [
                            'field' => 'metadata.transaction.transaction_time',
                            'op' => 'lte',
                            'value' => $endTime
                        ],
                        [
                            'field' => 'metadata.transaction.merchant_id',
                            'op' => 'equal',
                            'value' => $merchantId
                        ]
                    ],
                    'op' => 'and'
                ]
            ]
        ];

        $headers = [
            'Accept: application/json, text/plain, */*, application/vnd.journal.v1+json',
            'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            'Authentication-Type: go-id',
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Origin: https://portal.gofoodmerchant.co.id',
            'Referer: https://portal.gofoodmerchant.co.id/',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',
            'X-AppVersion: platform-v3.107.0-94ce5d57',
            'X-Platform: Web',
            'X-User-Type: merchant',
            'x-appId: go-biz-web-dashboard',
            'x-uniqueid: ' . $this->getUniqueId()
        ];

        $resp = $this->makeHttpRequest('https://api.gobiz.co.id/journals/search', 'POST', $requestBody, $headers);

        if ($resp['success'] && isset($resp['data']['hits']) && is_array($resp['data']['hits'])) {
            $standardized = [];
            foreach ($resp['data']['hits'] as $hit) {
                $tx = isset($hit['metadata']['transaction']) ? $hit['metadata']['transaction'] : [];
                $rawAmount = isset($tx['real_gross_amount']) ? $tx['real_gross_amount'] : (isset($tx['gross_amount']) ? $tx['gross_amount'] : 0);
                $amount = (int)round($rawAmount / 100);

                $orderId = isset($tx['order_id']) ? $tx['order_id'] : (isset($hit['reference_id']) ? $hit['reference_id'] : (string)$hit['version']);
                $paymentType = isset($tx['payment_type']) ? strtoupper($tx['payment_type']) : 'QRIS';
                $status = isset($tx['status']) ? strtoupper($tx['status']) : 'SETTLED';
                $time = isset($hit['time']) ? date('Y-m-d H:i:s', strtotime($hit['time'])) : date('Y-m-d H:i:s');

                $standardized[] = [
                    'id' => $orderId,
                    'amount' => $amount,
                    'type' => 'credit',
                    'status' => $status,
                    'time' => $time,
                    'description' => "Pembayaran {$paymentType} GoPay Merchant"
                ];
            }

            return [
                'success' => true,
                'transactions' => $standardized
            ];
        }

        $lastError = isset($resp['data']['message']) ? $resp['data']['message'] : (isset($resp['message']) ? $resp['message'] : 'Gagal membaca mutasi GoBiz.');
        return [
            'success' => false,
            'message' => $lastError,
            'transactions' => []
        ];
    }

    /**
     * Ekstrak daftar array transaksi dari berbagai kemungkinan struktur JSON Gojek
     */
    private function extractTransactionList($data) {
        if (isset($data['data']['settlements']) && is_array($data['data']['settlements'])) {
            return $data['data']['settlements'];
        }
        if (isset($data['data']['transactions']) && is_array($data['data']['transactions'])) {
            return $data['data']['transactions'];
        }
        if (isset($data['data']['history']) && is_array($data['data']['history'])) {
            return $data['data']['history'];
        }
        if (isset($data['data']['items']) && is_array($data['data']['items'])) {
            return $data['data']['items'];
        }
        if (isset($data['data']) && is_array($data['data']) && !isset($data['data']['id'])) {
            return $data['data'];
        }
        if (isset($data['transactions']) && is_array($data['transactions'])) {
            return $data['transactions'];
        }
        if (isset($data['settlements']) && is_array($data['settlements'])) {
            return $data['settlements'];
        }
        return [];
    }

    /**
     * Standarisasi format transaksi menjadi format seragam
     */
    private function standardizeTransactionItem($item) {
        if (!is_array($item)) return null;

        // Ambil ID Transaksi
        $id = '';
        if (isset($item['transaction_id'])) $id = (string)$item['transaction_id'];
        elseif (isset($item['id'])) $id = (string)$item['id'];
        elseif (isset($item['order_id'])) $id = (string)$item['order_id'];
        elseif (isset($item['reference_no'])) $id = (string)$item['reference_no'];
        
        if (empty($id)) return null;

        // Ambil Nominal
        $amount = 0;
        if (isset($item['amount']['value'])) {
            $amount = (int)preg_replace('/[^0-9]/', '', $item['amount']['value']);
        } elseif (isset($item['amount'])) {
            $amount = (int)preg_replace('/[^0-9]/', '', $item['amount']);
        } elseif (isset($item['nominal'])) {
            $amount = (int)preg_replace('/[^0-9]/', '', $item['nominal']);
        }

        // Ambil Tipe (Credit / Debit)
        $type = 'credit';
        if (isset($item['type'])) {
            $rawType = strtolower($item['type']);
            if (strpos($rawType, 'debit') !== false || strpos($rawType, 'out') !== false) {
                $type = 'debit';
            }
        }

        // Ambil Status
        $status = 'SETTLED';
        if (isset($item['status'])) {
            $status = strtoupper($item['status']);
        }

        // Ambil Waktu
        $time = date('Y-m-d H:i:s');
        if (isset($item['created_at'])) $time = $item['created_at'];
        elseif (isset($item['transaction_date'])) $time = $item['transaction_date'];
        elseif (isset($item['date'])) $time = $item['date'];

        // Ambil Deskripsi / Catatan Pembayar
        $desc = 'QRIS GoPay Merchant';
        if (isset($item['description'])) $desc = $item['description'];
        elseif (isset($item['title'])) $desc = $item['title'];
        elseif (isset($item['payer_name'])) $desc = 'Pembayaran dari ' . $item['payer_name'];

        return [
            'id' => $id,
            'amount' => $amount,
            'type' => $type,
            'status' => $status,
            'time' => $time,
            'description' => $desc
        ];
    }

    /**
     * Inti Sinkronisasi: Mencocokkan mutasi uang masuk GoPay dengan antrean pending MikhPay
     */
    public function syncPendingOrders() {
        // Cek apakah ada order pending
        $dir = dirname(__FILE__) . '/../../voucher/';
        $pendingFiles = [];
        if (is_dir($dir)) {
            $allFiles = array_merge(glob($dir . 'trans-*.json'), glob($dir . 'trans-*.php'));
            foreach ($allFiles as $file) {
                $trans = readTransactionFile($file);
                if ($trans && isset($trans['status']) && $trans['status'] === 'pending') {
                    $pendingFiles[] = $trans;
                }
            }
        }

        if (empty($pendingFiles)) {
            $this->settings->set('gopay_last_sync', time());
            return [
                'success' => true,
                'matched' => 0,
                'message' => 'Antrean bersih. Tidak ada transaksi pending di MikhPay.'
            ];
        }

        // Ambil mutasi transaksi dari GoPay
        $fetch = $this->getRecentTransactions(20);
        if (!$fetch['success']) {
            return [
                'success' => false,
                'matched' => 0,
                'message' => 'Gagal mengambil mutasi GoPay: ' . $fetch['message']
            ];
        }

        // Muat daftar transaksi GoPay yang sudah pernah diproses (deduplication)
        $processedIds = [];
        if (file_exists($this->processedFile)) {
            $json = @file_get_contents($this->processedFile);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $processedIds = $decoded;
            }
        }

        // Muat engine verifikasi QRIS
        require_once dirname(__FILE__) . '/../../include/qris_core.php';

        $matchedCount = 0;
        $matchedDetails = [];

        foreach ($fetch['transactions'] as $trans) {
            $transId = $trans['id'];
            $amount = $trans['amount'];
            $type = strtolower($trans['type']);
            $status = strtoupper($trans['status']);

            // Hanya proses uang masuk (credit) dengan status sukses / settled
            if ($type !== 'credit') continue;
            if (!in_array($status, ['SETTLEMENT', 'SETTLED', 'SUCCESS', 'COMPLETED', 'CAPTURE'])) continue;

            // Lewati jika transaksi GoPay ini sudah pernah diproses sebelumnya
            if (in_array($transId, $processedIds)) {
                continue;
            }

            // Cari apakah ada pending order yang nominalnya persis sama
            $matchedOrder = null;
            foreach ($pendingFiles as $p) {
                if (isset($p['price']) && (int)$p['price'] === $amount) {
                    $matchedOrder = $p;
                    break;
                }
            }

            if ($matchedOrder !== null) {
                // Eksekusi verifikasi & pembuatan voucher
                $result = processQrisSettlement($amount);

                if ($result['status'] === 'success') {
                    $matchedCount++;
                    $processedIds[] = $transId;
                    $matchedDetails[] = [
                        'order_id' => $result['order_id'],
                        'amount' => $amount,
                        'voucher' => isset($result['username']) ? $result['username'] : '-',
                        'gopay_trans_id' => $transId
                    ];

                    if (function_exists('writeAppLog')) {
                        writeAppLog(
                            'GOPAY_AUTO_SYNC',
                            "Mutasi GoPay Rp " . number_format($amount, 0, ',', '.') . " (ID: {$transId}) berhasil mencocokkan Order ID: {$result['order_id']} via Auto-Sync API."
                        );
                    }
                }
            }
        }

        // Simpan riwayat processed transaction (batasi 500 ID terakhir)
        if (count($processedIds) > 500) {
            $processedIds = array_slice($processedIds, -500);
        }
        @file_put_contents($this->processedFile, json_encode($processedIds, JSON_PRETTY_PRINT));

        $this->settings->set('gopay_last_sync', time());

        $msg = ($matchedCount > 0)
            ? "Sukses! {$matchedCount} transaksi GoPay berhasil disinkronkan dan voucher telah otomatis terbit."
            : "Sinkronisasi selesai. Belum ada mutasi baru yang sesuai dengan nominal antrean pending.";

        return [
            'success' => true,
            'matched' => $matchedCount,
            'details' => $matchedDetails,
            'message' => $msg
        ];
    }

    /**
     * Helper cURL request dengan timeout ketat
     */
    private function makeHttpRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false || !empty($curlError)) {
            return [
                'success' => false,
                'code' => $httpCode,
                'data' => null,
                'message' => 'Koneksi ke server GoPay gagal: ' . $curlError
            ];
        }

        $decoded = json_decode($rawResponse, true);
        $isSuccess = ($httpCode >= 200 && $httpCode < 300);

        return [
            'success' => $isSuccess,
            'code' => $httpCode,
            'data' => $decoded,
            'message' => $isSuccess ? 'OK' : 'HTTP Error ' . $httpCode
        ];
    }
}
