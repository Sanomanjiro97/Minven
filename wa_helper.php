<?php
/**
 * Helper untuk WhatsApp API
 * Fungsi-fungsi untuk mengirim pesan WhatsApp
 */

/**
 * Kirim pesan WhatsApp via API
 * @param string $nomor_telepon Nomor telepon tujuan (format: 62xxx atau 08xxx)
 * @param string $pesan Isi pesan yang akan dikirim
 * @return array Hasil pengiriman ['success' => bool, 'message' => string]
 */
function kirimWhatsApp($nomor_telepon, $pesan) {
    try {
        // Bersihkan nomor telepon dari karakter non-digit
        $nomor_telepon = preg_replace('/[^0-9]/', '', $nomor_telepon);
        
        // Validasi nomor telepon
        if (empty($nomor_telepon)) {
            return ['success' => false, 'message' => 'Nomor telepon tidak boleh kosong'];
        }
        
        // Pastikan nomor dimulai dengan 62 (kode negara Indonesia)
        if (substr($nomor_telepon, 0, 2) !== '62') {
            if (substr($nomor_telepon, 0, 1) === '0') {
                $nomor_telepon = '62' . substr($nomor_telepon, 1);
            } else {
                return ['success' => false, 'message' => 'Format nomor telepon tidak valid. Gunakan format 08xxx atau 628xxx'];
            }
        }
        
        // Siapkan data untuk API
        $data = [
            'number' => $nomor_telepon,
            'message' => $pesan
        ];
        
        // Tambahkan API key jika tersedia
        if (defined('WA_API_KEY') && WA_API_KEY !== 'your_api_key_here') {
            $data['api_key'] = WA_API_KEY;
        }
        
        // Konfigurasi cURL
        $ch = curl_init(WA_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        // Eksekusi request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Cek error cURL
        if ($response === false) {
            return ['success' => false, 'message' => 'cURL Error: ' . $curl_error];
        }
        
        // Decode response
        $result = json_decode($response, true);
        
        // Cek HTTP status code
        if ($http_code !== 200) {
            $error_msg = isset($result['message']) ? $result['message'] : 'HTTP Error: ' . $http_code;
            return ['success' => false, 'message' => $error_msg];
        }
        
        // Cek status dari response
        if (isset($result['success']) && $result['success'] === true) {
            return ['success' => true, 'message' => 'Pesan WhatsApp berhasil dikirim'];
        } else {
            $error_msg = isset($result['message']) ? $result['message'] : 'Gagal mengirim pesan WhatsApp';
            return ['success' => false, 'message' => $error_msg];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
    }
}

/**
 * Format pesan untuk reset password
 * @param string $nama_user Nama user
 * @param string $kode_reset Kode reset password
 * @param string $expired_at Waktu expired (format: Y-m-d H:i:s)
 * @return string Pesan yang sudah diformat
 */
function formatPesanResetPassword($nama_user, $kode_reset, $expired_at) {
    $pesan = "🔐 *RESET PASSWORD MINVEN*\n\n";
    $pesan .= "Halo *" . $nama_user . "*,\n\n";
    $pesan .= "Anda telah meminta reset password untuk akun MINVEN Anda.\n\n";
    $pesan .= "*Kode Verifikasi:*\n";
    $pesan .= "```" . $kode_reset . "```\n\n";
    $pesan .= "⏰ *Kode ini berlaku hingga:*\n";
    $pesan .= date('d/m/Y H:i', strtotime($expired_at)) . " WIB\n\n";
    $pesan .= "Silakan masukkan kode ini di halaman reset password untuk melanjutkan proses reset password.\n\n";
    $pesan .= "⚠️ *Peringatan:* Jangan berikan kode ini kepada siapapun demi keamanan akun Anda.\n\n";
    $pesan .= "Jika Anda tidak meminta reset password, abaikan pesan ini.\n\n";
    $pesan .= "_Pesan ini dikirim secara otomatis oleh sistem MINVEN._";
    
    return $pesan;
}

/**
 * Validasi format nomor telepon
 * @param string $nomor Nomor telepon yang akan divalidasi
 * @return bool True jika valid, false jika tidak
 */
function validasiNomorTelepon($nomor) {
    // Bersihkan dari spasi dan karakter khusus
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    
    // Cek panjang minimal
    if (strlen($nomor) < 10) {
        return false;
    }
    
    // Cek format nomor Indonesia
    if (substr($nomor, 0, 2) === '62') {
        return strlen($nomor) >= 11; // 62 + minimal 9 digit
    } elseif (substr($nomor, 0, 1) === '0') {
        return strlen($nomor) >= 10; // 0 + minimal 9 digit
    }
    
    return false;
}

/**
 * Generate kode verifikasi 6 digit
 * @return string Kode verifikasi 6 digit
 */
function generateKodeVerifikasi() {
    return sprintf("%06d", mt_rand(100000, 999999));
}

/**
 * Cek apakah WhatsApp API tersedia dan berfungsi
 * @return array Status API ['available' => bool, 'message' => string]
 */
function cekWhatsAppAPI() {
    if (!defined('WA_API_URL') || WA_API_URL === 'http://localhost:3000/send-message') {
        return ['available' => false, 'message' => 'WhatsApp API belum dikonfigurasi'];
    }
    
    try {
        // Test dengan request sederhana
        $ch = curl_init(WA_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 || $http_code === 400) { // 400 = bad request tapi API aktif
            return ['available' => true, 'message' => 'WhatsApp API tersedia'];
        } else {
            return ['available' => false, 'message' => 'WhatsApp API tidak merespon (HTTP ' . $http_code . ')'];
        }
        
    } catch (Exception $e) {
        return ['available' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}