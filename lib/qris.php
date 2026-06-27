<?php
/**
 * QRIS Generator (Static to Dynamic)
 * Library untuk mengubah QRIS statis menjadi dinamis.
 */

class QrisGenerator {
    private $static_qris;
    
    public function __construct($static_qris) {
        $this->static_qris = $static_qris;
    }
    
    /**
     * Menambahkan nominal ke dalam string QRIS dan menghitung ulang CRC
     * @param int|float $amount Nominal transaksi
     * @return string String QRIS Dinamis
     */
    public function generateDynamic($amount) {
        $qris = $this->static_qris;
        
        // Buang Tag 63 (CRC) dari string asli (karena akan dihitung ulang)
        // Tag 63 panjang valuenya pasti 4, ditambah tag (2) dan length (2) = 8 karakter dari ujung
        $qris_without_crc = substr($qris, 0, -8);
        
        // Buang tag 54 (jika sudah ada sebelumnya)
        // Kita gunakan regex sederhana atau parsing manual. Karena ini string statis dasar,
        // asumsikan belum ada tag 54, atau kita cukup sisipkan sebelum tag 58 (Country Code) yang pasti ada.
        
        // Format tag 54: 54 + sprintf("%02d", strlen($amount)) + $amount
        $amount_str = (string)$amount;
        $tag_54 = "54" . sprintf("%02d", strlen($amount_str)) . $amount_str;
        
        // Sisipkan tag 54. Kita cari posisi tag 58 (ID negara, biasanya "5802ID") 
        // dan masukkan tepat sebelum tag 58.
        $pos_58 = strpos($qris_without_crc, "5802ID");
        if ($pos_58 !== false) {
            $qris_with_amount = substr($qris_without_crc, 0, $pos_58) . $tag_54 . substr($qris_without_crc, $pos_58);
        } else {
            // Fallback, tempel di akhir (sebelum 63)
            $qris_with_amount = $qris_without_crc . $tag_54;
        }
        
        // Ubah jenis QRIS dari statis (010211) menjadi dinamis (010212) jika tag 01 ada.
        $qris_with_amount = str_replace("010211", "010212", $qris_with_amount);
        
        // Tambahkan header Tag 63 (6304) untuk persiapan perhitungan CRC
        $payload_for_crc = $qris_with_amount . "6304";
        
        // Hitung CRC
        $crc = $this->calculateCRC16($payload_for_crc);
        
        // Gabungkan payload dengan hasil CRC
        return $payload_for_crc . $crc;
    }

    /**
     * Menghitung CRC16 (CCITT-FALSE)
     */
    private function calculateCRC16($str) {
        $crc = 0xFFFF;
        $strlen = strlen($str);
        for ($c = 0; $c < $strlen; $c++) {
            $crc ^= ord($str[$c]) << 8;
            for ($i = 0; $i < 8; $i++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc = $crc << 1;
                }
            }
        }
        $crc &= 0xFFFF; // Pastikan 16-bit
        $hex = dechex($crc);
        $hex = strtoupper(str_pad($hex, 4, '0', STR_PAD_LEFT));
        return $hex;
    }
}
?>
