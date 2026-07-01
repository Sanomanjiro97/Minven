<?php
// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include config
require_once '../../config.php';

// Set header JSON dan error handling
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include access check
require_once '../../includes/access_check.php';

// Custom error handler
function handleError($errno, $errstr, $errfile, $errline) {
    $error = [
        'status' => 'error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ];
    writeLog("PHP Error: " . json_encode($error), 'error');
    echo json_encode($error);
    exit;
}

// Set error handler
set_error_handler('handleError');

// Fungsi untuk log
function writeLog($message, $type = 'info') {
    $logFile = __DIR__ . '/payment.log';
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date][$type] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Log request dengan detail lebih lengkap
    writeLog("Payment request received: " . json_encode([
        'GET' => $_GET,
        'POST' => $_POST,
        'SESSION' => isset($_SESSION) ? array_keys($_SESSION) : 'No Session',
        'USER_ID' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not Set',
        'HTTP_METHOD' => $_SERVER['REQUEST_METHOD'],
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR']
    ]));

    // Validasi session
    if (!isset($_SESSION['user_id'])) {
        writeLog("Session check failed - user_id not found");
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesi tidak valid. Silakan login kembali.',
            'redirect' => '../../index.php'
        ]);
        exit;
    } else {
        writeLog("Session valid - user_id: " . $_SESSION['user_id']);
    }

    // Validasi akses ke payment
    if (!checkAccess('payment', 'complete')) {
        writeLog("Access check failed - no access to payment completion");
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Anda tidak memiliki akses untuk melakukan pembayaran.'
        ]);
        exit;
    } else {
        writeLog("Access check passed - user has payment completion access");
    }

    // Validasi ID
    if (!isset($_GET['id'])) {
        throw new Exception("ID tidak ditemukan");
    }

    // Validasi dan sanitasi ID
    $raw_id = trim($_GET['id']);
    if (empty($raw_id)) {
        throw new Exception("ID tidak boleh kosong");
    }
    
    // Validasi bahwa ID adalah angka positif
    if (!is_numeric($raw_id)) {
        throw new Exception("ID harus berupa angka");
    }
    
    $id = (int)$raw_id;
    if ($id <= 0) {
        throw new Exception("ID tidak valid: " . htmlspecialchars($raw_id));
    }

    // Log ID yang diproses
    writeLog("Processing payment for ID: $id");

    // Mulai transaksi
    try {
        $conn->autocommit(FALSE);
        writeLog("Transaction started - autocommit disabled");
    } catch (Exception $e) {
        writeLog("Failed to start transaction: " . $e->getMessage(), 'error');
        throw new Exception("Gagal memulai transaksi");
    }

    // Log koneksi database
    writeLog("Database connection status: " . ($conn ? "Connected" : "Not connected"));
    
    // Cek dan lock data pembelian
    $check_sql = "SELECT * FROM direct_purchase WHERE id = ? FOR UPDATE";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        writeLog("Database prepare error: " . $conn->error, 'error');
        throw new Exception("Database error: " . $conn->error);
    }

    $check_stmt->bind_param('i', $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Data pembelian tidak ditemukan");
    }

    $purchase = $result->fetch_assoc();
    writeLog("Current purchase status: " . $purchase['status']);

    if ($purchase['status'] !== 'menunggu') {
        throw new Exception("Status pembelian saat ini: {$purchase['status']}. Hanya status 'menunggu' yang dapat diproses.");
    }

    // Update status
    $update_sql = "UPDATE direct_purchase 
                   SET status = 'payment',
                       updated_at = NOW()
                   WHERE id = ? AND status = 'menunggu'";
    
    $update_stmt = $conn->prepare($update_sql);
    
    if (!$update_stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $update_stmt->bind_param('i', $id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Gagal update: " . $update_stmt->error);
    }

    if ($update_stmt->affected_rows === 0) {
        throw new Exception("Tidak ada perubahan status");
    }

    writeLog("Status updated successfully");

    // Commit transaksi
    try {
        $conn->commit();
        $conn->autocommit(TRUE);
        writeLog("Transaction committed successfully");
    } catch (Exception $e) {
        writeLog("Commit failed: " . $e->getMessage(), 'error');
        throw new Exception("Gagal menyimpan perubahan");
    }

    // Return success
    echo json_encode([
        'status' => 'success',
        'message' => 'Pembayaran berhasil diproses'
    ]);

    writeLog("Success response sent");

} catch (Exception $e) {
    // Log error dengan detail lebih lengkap
    writeLog("Error detail: " . json_encode([
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]), 'error');

    // Rollback transaksi
    if (isset($conn)) {
        try {
            $conn->rollback();
            writeLog("Transaction rolled back", 'error');
        } catch (Exception $rollbackError) {
            writeLog("Rollback error: " . $rollbackError->getMessage(), 'error');
        }
    }

    // Return error
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Close statements
if (isset($check_stmt) && $check_stmt instanceof mysqli_stmt) $check_stmt->close();
if (isset($update_stmt) && $update_stmt instanceof mysqli_stmt) $update_stmt->close();