<?php
header("Content-Type: application/json");

// ============================
// CONFIG & PATH DETECTOR
// ============================
$configPath = dirname(__DIR__, 3) . '/config.php';

if (file_exists($configPath)) {
    require_once $configPath;
} else {
    $configSafePath = dirname(__DIR__, 3) . '/config_safe.php';
    if (file_exists($configSafePath)) {
        require_once $configSafePath;
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "File config.php tidak ditemukan!"
        ]);
        exit;
    }
}

$TOKEN = "MINVEN_SECRET_2026";

// ============================
// AUTHORIZATION
// ============================
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (empty($authHeader)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authorization Required"]);
    exit;
}

$token = str_replace("Bearer ", "", $authHeader);

if ($token !== $TOKEN) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Invalid Token"]);
    exit;
}

// ============================
// JSON INPUT
// ============================
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON Payload"]);
    exit;
}

// ============================
// DATA & VALIDATION
// ============================
$tanggal    = $data['tanggal'] ?? date('Y-m-d');
$nominal    = (float)($data['nominal'] ?? 0);
$nama       = $data['nama'] ?? 'Karyawan';
$periode    = $data['periode'] ?? date('Y-m');
// created_by harus INTEGER sesuai kolom DB (#9 created_by int(11))
$created_by = isset($data['created_by']) && is_numeric($data['created_by']) ? (int)$data['created_by'] : 1; 

if (empty($nominal) || empty($nama)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Parameter nominal dan nama wajib diisi!"]);
    exit;
}

// ============================
// PREPARE VALUES
// ============================
$today = date('Ymd');
$no = "PENG-" . $today . sprintf("%04d", rand(1, 9999));
$keteranganGaji = "Gaji Karyawan";
$deskripsiGaji  = "Pembayaran Gaji " . $nama . " (Periode " . $periode . ")";
$totalItem      = 1; // Mengisi NOT NULL total_item (#4 total_item int(11))

// ============================
// EXECUTE INSERT (PDO OR MYSQLI)
// ============================

// 1. OPSI DENGAN PDO
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sql = $pdo->prepare("
            INSERT INTO pengeluaran 
            (tanggal, no_pengeluaran, total_item, total_harga, keterangan, payment_method, deskripsi, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, 'cash', ?, ?, NOW())
        ");
        
        $sql->execute([
            $tanggal,
            $no,
            $totalItem,
            $nominal,
            $keteranganGaji,
            $deskripsiGaji,
            $created_by
        ]);

        echo json_encode(["success" => true, "message" => "Gaji berhasil dicatat (PDO)", "no_pengeluaran" => $no]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "PDO Error: " . $e->getMessage()]);
        exit;
    }
} 

// 2. OPSI DENGAN MYSQLI
$mysqli = $conn ?? $db ?? $GLOBALS['boMainConn'] ?? $GLOBALS['conn'] ?? null;

if ($mysqli && $mysqli instanceof mysqli) {
    $stmt = $mysqli->prepare("
        INSERT INTO pengeluaran 
        (tanggal, no_pengeluaran, total_item, total_harga, keterangan, payment_method, deskripsi, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, 'cash', ?, ?, NOW())
    ");
    
    if ($stmt) {
        // s = string, i = int, d = double/decimal
        $stmt->bind_param('ssidssi', $tanggal, $no, $totalItem, $nominal, $keteranganGaji, $deskripsiGaji, $created_by);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Gaji berhasil dicatat (MySQLi)", "no_pengeluaran" => $no]);
            $stmt->close();
            exit;
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "MySQLi Execute Error: " . $stmt->error]);
            exit;
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "MySQLi Prepare Error: " . $mysqli->error]);
        exit;
    }
}

// JIKA TIDAK ADA KONEKSI
http_response_code(500);
echo json_encode(["success" => false, "message" => "Koneksi database tidak ditemukan pada config.php"]);