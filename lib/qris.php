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
        $qris = trim($this->static_qris);
        $amount_str = (string)(int)round($amount);
        $tag_54 = "54" . sprintf("%02d", strlen($amount_str)) . $amount_str;

        // Parse EMVCo TLV tags: strip old Tag 54 and Tag 63, switch Tag 01 to dynamic (12), insert Tag 54 before Tag 58
        $out = '';
        $inserted = false;
        $i = 0;
        $len = strlen($qris);
        while ($i + 4 <= $len) {
            $tag = substr($qris, $i, 2);
            $valLen = (int)substr($qris, $i + 2, 2);
            if ($tag === '63' || $i + 4 + $valLen > $len) break;
            $val = substr($qris, $i + 4, $valLen);
            $i += 4 + $valLen;

            if ($tag === '54') continue;
            if ($tag === '01' && $val === '11') $val = '12';
            if ($tag === '58' && !$inserted) {
                $out .= $tag_54;
                $inserted = true;
            }
            $out .= $tag . sprintf("%02d", strlen($val)) . $val;
        }
        if (!$inserted) {
            $out .= $tag_54;
        }

        $payload_for_crc = $out . "6304";
        return $payload_for_crc . $this->calculateCRC16($payload_for_crc);
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
