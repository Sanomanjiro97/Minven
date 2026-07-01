<?php
// Start output buffering at the very beginning
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
require_once '../includes/access_check.php';

// Determine if the request expects JSON
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Initialize response array
$response = ['success' => false, 'message' => '', 'data' => null];

// Make sure action is defined
$action = $_POST['action'] ?? '';

// Remove all the duplicate code at the end of the file
// and fix the response handling
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            if ($isAjaxRequest) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'User not logged in', 'data' => null]);
                exit();
            }
            header('Location: ../index.php');
            exit();
        }

        // Pastikan action update_stok_keluar ada di validActions
        $validActions = ['add_stok', 'delete_stok', 'update_stok', 'update_stok_terpakai', 'update_stok_keluar', 'update_sisa_stok', 'add'];
        
        if (empty($action)) {
            $_SESSION['error'] = 'Parameter action harus diisi';
            header('Location: gudang_central.php');
            exit();
        }

        if (!in_array($action, $validActions)) {
            $_SESSION['error'] = 'Aksi tidak valid: ' . $action;
            header('Location: gudang_central.php');
            exit();
        }

        $gudang_id = 23; // ID Gudang Central
        $requiredPermission = 'edit';
        if ($action === 'add' || $action === 'add_stok') {
            $requiredPermission = 'add';
        } elseif ($action === 'delete_stok') {
            $requiredPermission = 'delete';
        }

        if (!checkAccess('gudang_central', $requiredPermission)) {
            if ($isAjaxRequest) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses tidak diizinkan', 'data' => null]);
                exit();
            }
            $_SESSION['error'] = 'Akses tidak diizinkan';
            header('Location: gudang_central.php');
            exit();
        }

        switch ($action) {
            case 'add':
            case 'add_stok':
                // Validasi input
                $required = ['barang_id'];  // Remove stok_awal from required fields
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        $_SESSION['error'] = "Field $field harus diisi";
                        header('Location: gudang_central.php');
                        exit();
                    }
                }

                $barang_id = (int)$_POST['barang_id'];
                $stok_awal = 0;  // Default to 0
                $stok_minimum = (int)($_POST['stok_minimum'] ?? 0);
                $expire_date = !empty($_POST['expire_date']) ? date('Y-m-d', strtotime($_POST['expire_date'])) : null;
                
                // Validasi format tanggal
                if (!empty($expire_date) && $expire_date == '1970-01-01') {
                    $expire_date = null;
                }
                
                $user_id = $_SESSION['user_id'];

                // Start transaction for atomic operation
                $conn->begin_transaction();

                try {
                    // Check if the item exists in Central
                    $checkSql = "SELECT id FROM gudang_stok 
                                WHERE gudang_id = ? AND barang_id = ? FOR UPDATE";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("ii", $gudang_id, $barang_id);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();

                    if ($checkResult->num_rows > 0) {
                        // Update existing record
                        $row = $checkResult->fetch_assoc();
                        $stokId = $row['id'];

                        $updateSql = "UPDATE gudang_stok SET 
                                    stok_minimum = ?,
                                    expire_date = ?,
                                    modified_by = ?,
                                    updated_at = NOW()
                                WHERE id = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param("isii", $stok_minimum, $expire_date, $user_id, $stokId);
                        
                        if (!$updateStmt->execute()) {
                            throw new Exception("Gagal update stok: " . $updateStmt->error);
                        }
                    } else {
                        // Insert new record with stok_awal = 0
                        $insertSql = "INSERT INTO gudang_stok 
                                    (gudang_id, barang_id, stok_awal, stok_terpakai, stok_sisa, stok_minimum, expire_date, created_by, modified_by, created_at, updated_at)
                                    VALUES (?, ?, 0, 0, 0, ?, ?, ?, ?, NOW(), NOW())";
                        $insertStmt = $conn->prepare($insertSql);
                        $insertStmt->bind_param("iiisii", $gudang_id, $barang_id, $stok_minimum, $expire_date, $user_id, $user_id);
                        
                        if (!$insertStmt->execute()) {
                            throw new Exception("Gagal menambahkan stok: " . $insertStmt->error);
                        }
                    }

                    $conn->commit();
                    $_SESSION['success'] = "Item berhasil ditambahkan dengan stok awal 0";
                    header('Location: gudang_central.php');
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;

            case 'delete_stok':
                if (empty($_POST['id'])) {
                    $_SESSION['error'] = "ID stok harus diisi";
                    header('Location: gudang_central.php');
                    exit();
                }
                
                $id = (int)$_POST['id'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Get item details before deletion
                    $getItemSql = "SELECT barang_id, stok_awal FROM gudang_stok WHERE id = ? AND gudang_id = ?";
                    $getItemStmt = $conn->prepare($getItemSql);
                    $getItemStmt->bind_param("ii", $id, $gudang_id);
                    $getItemStmt->execute();
                    $itemResult = $getItemStmt->get_result();
                    
                    if ($itemResult->num_rows === 0) {
                        throw new Exception("Item tidak ditemukan");
                    }
                    
                    $itemData = $itemResult->fetch_assoc();
                    $barang_id = $itemData['barang_id'];
                    $stok_awal = $itemData['stok_awal'];
                    
                    // Delete the item
                    $deleteSql = "DELETE FROM gudang_stok WHERE id = ? AND gudang_id = ?";
                    $deleteStmt = $conn->prepare($deleteSql);
                    $deleteStmt->bind_param("ii", $id, $gudang_id);
                    
                    if (!$deleteStmt->execute()) {
                        throw new Exception("Gagal menghapus stok: " . $deleteStmt->error);
                    }
                    
                    // Check if item exists in Antapani warehouse
                    $checkAntapaniSql = "SELECT id FROM gudang_stok WHERE gudang_id = 13 AND barang_id = ?";
                    $checkAntapaniStmt = $conn->prepare($checkAntapaniSql);
                    $checkAntapaniStmt->bind_param("i", $barang_id);
                    $checkAntapaniStmt->execute();
                    $checkAntapaniResult = $checkAntapaniStmt->get_result();
                    
                    if ($checkAntapaniResult->num_rows > 0) {
                        $antapaniRow = $checkAntapaniResult->fetch_assoc();
                        $antapaniStokId = $antapaniRow['id'];
                        
                        // Transfer stock to Antapani
                        $updateAntapaniSql = "UPDATE gudang_stok SET 
                                          stok_awal = stok_awal + ?,
                                          stok_sisa = (stok_awal + ?) - stok_terpakai,
                                          modified_by = ?,
                                          updated_at = NOW()
                                      WHERE id = ?";
                        $updateAntapaniStmt = $conn->prepare($updateAntapaniSql);
                        $updateAntapaniStmt->bind_param("iiii", $stok_awal, $stok_awal, $_SESSION['user_id'], $antapaniStokId);
                        
                        if (!$updateAntapaniStmt->execute()) {
                            throw new Exception("Gagal transfer stok ke Gudang Antapani: " . $updateAntapaniStmt->error);
                        }
                        
                        // Add history records
                        $historyInsertSql = "INSERT INTO stok_history 
                                           (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, created_by) 
                                           VALUES (NOW(), ?, 23, ?, 'delete', 'Hapus stok dan transfer ke Gudang Antapani', ?)";
                        $historyInsertStmt = $conn->prepare($historyInsertSql);
                        $historyInsertStmt->bind_param("idi", $barang_id, $stok_awal, $_SESSION['user_id']);
                        $historyInsertStmt->execute();
                        
                        $historyInsertSql = "INSERT INTO stok_history 
                                           (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, created_by) 
                                           VALUES (NOW(), ?, 13, ?, 'transfer_in', 'Transfer stok dari Gudang Central', ?)";
                        $historyInsertStmt = $conn->prepare($historyInsertSql);
                        $historyInsertStmt->bind_param("idi", $barang_id, $stok_awal, $_SESSION['user_id']);
                        $historyInsertStmt->execute();
                    }
                    
                    $conn->commit();
                    $_SESSION['success'] = "Stok berhasil dihapus" . 
                        ($checkAntapaniResult->num_rows > 0 ? " dan ditransfer ke Gudang Antapani" : "");
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = $e->getMessage();
                }
                
                header('Location: gudang_central.php');
                exit();
                break;

            case 'update_stok':
                if (empty($_POST['edit_stok_id']) || !isset($_POST['edit_stok_awal']) || !isset($_POST['edit_stok_terpakai']) || !isset($_POST['edit_stok_minimum'])) {
                    $_SESSION['error'] = "Data tidak lengkap";
                    header('Location: gudang_central.php');
                    exit();
                }
                
                $id = (int)$_POST['edit_stok_id'];
                $stok_awal = (float)$_POST['edit_stok_awal'];
                $stok_terpakai = (float)$_POST['edit_stok_terpakai'];
                $stok_minimum = (float)$_POST['edit_stok_minimum'];
                $expire_date = $_POST['edit_expire_date'] ?? null;
                $user_id = $_SESSION['user_id'];
                
                // Get current stock data
                $getCurrentSql = "SELECT barang_id, stok_awal FROM gudang_stok WHERE id = ? AND gudang_id = ?";
                $getCurrentStmt = $conn->prepare($getCurrentSql);
                $getCurrentStmt->bind_param("ii", $id, $gudang_id);
                $getCurrentStmt->execute();
                $currentResult = $getCurrentStmt->get_result();
                
                if ($currentResult->num_rows === 0) {
                    $_SESSION['error'] = "Data stok tidak ditemukan";
                    header('Location: gudang_central.php');
                    exit();
                }
                
                $currentData = $currentResult->fetch_assoc();
                $barang_id = $currentData['barang_id'];
                $current_stok_awal = $currentData['stok_awal'];
                $stok_diff = $stok_awal - $current_stok_awal;
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Validasi stok terpakai tidak melebihi stok awal
                    if ($stok_terpakai > $stok_awal) {
                        throw new Exception("Stok terpakai tidak boleh melebihi stok awal");
                    }
                    
                    // Update the stock
                    $updateSql = "UPDATE gudang_stok SET 
                                stok_awal = ?,
                                stok_terpakai = ?,
                                stok_sisa = (? - ?),
                                stok_minimum = ?,
                                expire_date = ?,
                                modified_by = ?,
                                updated_at = NOW()
                            WHERE id = ? AND gudang_id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("dddddsiii", $stok_awal, $stok_terpakai, $stok_awal, $stok_terpakai, $stok_minimum, $expire_date, $user_id, $id, $gudang_id);
                    
                    if (!$updateStmt->execute()) {
                        throw new Exception("Gagal update stok: " . $updateStmt->error);
                    }
                    
                    $conn->commit();
                    $_SESSION['success'] = "Stok berhasil diupdate";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = $e->getMessage();
                }
                
                header('Location: gudang_central.php');
                exit();
                break;

            case 'update_stok_terpakai':
                if (empty($_POST['id']) || !isset($_POST['stok_terpakai'])) {
                    throw new Exception("Data tidak lengkap");
                }
                
                $id = (int)$_POST['id'];
                $stok_terpakai = (int)$_POST['stok_terpakai'];
                $user_id = $_SESSION['user_id'];
                
                // Validasi stok terpakai tidak melebihi stok awal
                $checkSql = "SELECT stok_awal FROM gudang_stok WHERE id = ? AND gudang_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $id, $gudang_id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows === 0) {
                    throw new Exception("Data stok tidak ditemukan");
                }
                
                $stok_awal = $checkResult->fetch_assoc()['stok_awal'];
                
                if ($stok_terpakai > $stok_awal) {
                    throw new Exception("Stok terpakai tidak boleh melebihi stok awal");
                }
                
                $updateSql = "UPDATE gudang_stok SET 
                            stok_terpakai = ?,
                            stok_sisa = (stok_awal - ?),
                            modified_by = ?,
                            updated_at = NOW()
                        WHERE id = ? AND gudang_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("iiiii", $stok_terpakai, $stok_terpakai, $user_id, $id, $gudang_id);
                
                if ($updateStmt->execute()) {
                    $response = [
                        'success' => true,
                        'message' => 'Stok terpakai berhasil diupdate',
                        'data' => [
                            'stok_awal' => $stok_awal,
                            'stok_terpakai' => $stok_terpakai,
                            'stok_akhir' => $stok_awal - $stok_terpakai
                        ]
                    ];
                } else {
                    throw new Exception("Gagal update stok terpakai: " . $updateStmt->error);
                }
                break;

            case 'update_stok_keluar':
                if (empty($_POST['id']) || !isset($_POST['stok_keluar'])) {
                    throw new Exception("Data tidak lengkap");
                }
                
                $id = (int)$_POST['id'];
                $stok_keluar = (int)$_POST['stok_keluar'];
                $user_id = $_SESSION['user_id'];
                
                // Validasi stok keluar tidak melebihi stok akhir
                $checkSql = "SELECT stok_awal, stok_terpakai FROM gudang_stok WHERE id = ? AND gudang_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $id, $gudang_id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows === 0) {
                    throw new Exception("Data stok tidak ditemukan");
                }
                
                $row = $checkResult->fetch_assoc();
                $stok_awal = $row['stok_awal'];
                $stok_terpakai = $row['stok_terpakai'];
                $stok_akhir = $stok_awal - $stok_terpakai;
                
                if ($stok_keluar > $stok_akhir) {
                    throw new Exception("Stok keluar tidak boleh melebihi stok akhir");
                }
                
                // Update stok terpakai dengan menambahkan stok keluar
                $new_stok_terpakai = $stok_terpakai + $stok_keluar;
                $updateSql = "UPDATE gudang_stok SET 
                            stok_terpakai = ?,
                            stok_sisa = (stok_awal - ?),
                            modified_by = ?,
                            updated_at = NOW()
                        WHERE id = ? AND gudang_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("iiiii", $new_stok_terpakai, $new_stok_terpakai, $user_id, $id, $gudang_id);
                
                if ($updateStmt->execute()) {
                    $response = [
                        'success' => true,
                        'message' => 'Stok keluar berhasil diupdate',
                        'data' => [
                            'stok_awal' => $stok_awal,
                            'stok_keluar' => $stok_keluar,
                            'stok_terpakai' => $new_stok_terpakai,
                            'stok_akhir' => $stok_awal - $new_stok_terpakai
                        ]
                    ];
                } else {
                    throw new Exception("Gagal update stok keluar: " . $updateStmt->error);
                }
                break;

            case 'update_sisa_stok':
                if (empty($_POST['id']) || !isset($_POST['sisa_stok'])) {
                    $response = ['success' => false, 'message' => 'Data tidak lengkap'];
                    if ($isAjaxRequest) {
                        // At the beginning of the file
                        ob_start();
                        header('Content-Type: application/json');
                        
                        echo json_encode($response);
                        exit();
                    }
                    throw new Exception("Data tidak lengkap");
                }
                
                $id = (int)$_POST['id'];
                $sisa_stok = (int)$_POST['sisa_stok'];
                $user_id = $_SESSION['user_id'];
                
                // Validasi sisa stok tidak melebihi stok awal
                $checkSql = "SELECT stok_awal FROM gudang_stok WHERE id = ? AND gudang_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $id, $gudang_id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows === 0) {
                    throw new Exception("Data stok tidak ditemukan");
                }
                
                $row = $checkResult->fetch_assoc();
                $stok_awal = $row['stok_awal'];
                
                if ($sisa_stok > $stok_awal) {
                    throw new Exception("Sisa stok tidak boleh melebihi stok awal");
                }
                
                // Hitung stok terpakai berdasarkan stok awal dikurangi sisa stok
                $stok_terpakai = $stok_awal - $sisa_stok;
                
                // Update stok terpakai
                $updateSql = "UPDATE gudang_stok SET 
                            stok_terpakai = ?,
                            stok_sisa = ?,
                            modified_by = ?,
                            updated_at = NOW()
                        WHERE id = ? AND gudang_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("iiiii", $stok_terpakai, $sisa_stok, $user_id, $id, $gudang_id);
                
                if ($updateStmt->execute()) {
                    // Clear any output buffer
                    ob_end_clean();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Sisa stok berhasil diupdate',
                        'data' => [
                            'stok_awal' => $stok_awal,
                            'sisa_stok' => $sisa_stok,
                            'stok_terpakai' => $stok_terpakai,
                            'stok_akhir' => $sisa_stok
                        ]
                    ]);
                    exit();
                }
                break;

            default:
                $response = [
                    'success' => false,
                    'message' => 'Aksi tidak dikenali. Silakan hubungi administrator'
                ];
                break;
        }
    } else {
        throw new Exception("Metode request tidak valid");
    }
} catch (Exception $e) {
    // Clear any previous output
    ob_end_clean();
    
    if ($isAjaxRequest) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => null
        ]);
    } else {
        // For regular form submissions
        $_SESSION['error'] = $e->getMessage();
        header('Location: gudang_central.php');
        exit();
    }
}

// Default redirect if we somehow get here
header('Location: gudang_central.php');
exit();

// At the end of the try block, handle the response
if ($isAjaxRequest) {
    // Clear any output buffer
    ob_end_clean();
    
    // Set proper headers
    header('Content-Type: application/json');
    
    // Send JSON response
    echo json_encode($response);
    exit();
} else {
    // For regular form submissions that don't have their own redirects
    if (!headers_sent()) {
        header('Location: gudang_central.php');
    }
    exit();
}
exit();
?>
