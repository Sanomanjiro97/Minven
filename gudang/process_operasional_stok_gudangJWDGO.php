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
$expectsJson = $isAjaxRequest ||
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (!empty($_POST['expects_json']));

// Initialize response array
$response = ['success' => false, 'message' => '', 'data' => null];

// Make sure action is defined
$action = $_POST['action'] ?? '';

// Remove all the duplicate code at the end of the file
// and fix the response handling
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            if ($expectsJson) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'User not logged in', 'data' => null]);
                exit();
            }
            header('Location: ../index.php');
            exit();
        }

        // Pastikan action update_stok_keluar ada di validActions
        $validActions = ['add_stok', 'delete_stok', 'update_stok', 'update_stok_terpakai', 'update_stok_keluar', 'update_sisa_stok', 'quick_stok_masuk', 'add'];
        
        if (empty($action)) {
            $_SESSION['error'] = 'Parameter action harus diisi';
            header('Location: gudang_antapani.php');
            exit();
        }

        if (!in_array($action, $validActions)) {
            $_SESSION['error'] = 'Aksi tidak valid: ' . $action;
            header('Location: gudang_antapani.php');
            exit();
        }

        $gudang_id = 30; // ID Jawil Dago
        $requiredPermission = 'edit';
        if ($action === 'add' || $action === 'add_stok') {
            $requiredPermission = 'add';
        } elseif ($action === 'delete_stok') {
            $requiredPermission = 'delete';
        }

        if (!checkAccess('gudang_JWDGO', $requiredPermission)) {
            if ($expectsJson) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses tidak diizinkan', 'data' => null]);
                exit();
            }
            $_SESSION['error'] = 'Akses tidak diizinkan';
            header('Location: gudang_antapani.php');
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
                        header('Location: gudang_antapani.php');
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
                    // Check if the item exists in Antapani
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
                    header('Location: gudang_antapani.php');
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;

            case 'delete_stok':
                if (empty($_POST['id'])) {
                    $_SESSION['error'] = "ID stok harus diisi";
                    header('Location: gudang_antapani.php');
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
                    
                    // Return stock to Central if it came from there
                    $checkCentralSql = "SELECT id FROM gudang_stok WHERE gudang_id = 23 AND barang_id = ?";
                    $checkCentralStmt = $conn->prepare($checkCentralSql);
                    $checkCentralStmt->bind_param("i", $barang_id);
                    $checkCentralStmt->execute();
                    $checkCentralResult = $checkCentralStmt->get_result();
                    
                    if ($checkCentralResult->num_rows > 0) {
                        $centralRow = $checkCentralResult->fetch_assoc();
                        $centralStokId = $centralRow['id'];
                        
                        // Return stock to Central
                        $updateCentralSql = "UPDATE gudang_stok SET 
                                          stok_awal = stok_awal + ?,
                                          stok_sisa = (stok_awal + ?) - stok_terpakai,
                                          modified_by = ?,
                                          updated_at = NOW()
                                      WHERE id = ?";
                        $updateCentralStmt = $conn->prepare($updateCentralSql);
                        $updateCentralStmt->bind_param("iiii", $stok_awal, $stok_awal, $_SESSION['user_id'], $centralStokId);
                        
                        if (!$updateCentralStmt->execute()) {
                            throw new Exception("Gagal mengembalikan stok ke Gudang Central: " . $updateCentralStmt->error);
                        }
                        
                        // Add history records
                        $historyInsertSql = "INSERT INTO stok_history 
                                           (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, created_by) 
                                           VALUES (NOW(), ?, 13, ?, 'delete', 'Hapus stok dan kembalikan ke Gudang Central', ?)";
                        $historyInsertStmt = $conn->prepare($historyInsertSql);
                        $historyInsertStmt->bind_param("idi", $barang_id, $stok_awal, $_SESSION['user_id']);
                        $historyInsertStmt->execute();
                        
                        $historyInsertSql = "INSERT INTO stok_history 
                                           (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, created_by) 
                                           VALUES (NOW(), ?, 23, ?, 'return', 'Pengembalian stok dari Jawil Dago', ?)";
                        $historyInsertStmt = $conn->prepare($historyInsertSql);
                        $historyInsertStmt->bind_param("idi", $barang_id, $stok_awal, $_SESSION['user_id']);
                        $historyInsertStmt->execute();
                    }
                    
                    $conn->commit();
                    $_SESSION['success'] = "Stok berhasil dihapus" . 
                        ($checkCentralResult->num_rows > 0 ? " dan dikembalikan ke Gudang Central" : "");
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = $e->getMessage();
                }
                
                header('Location: gudang_antapani.php');
                exit();
                break;

            case 'update_stok':
                if (empty($_POST['edit_stok_id']) || !isset($_POST['edit_stok_awal']) || !isset($_POST['edit_stok_terpakai']) || !isset($_POST['edit_stok_minimum'])) {
                    $_SESSION['error'] = "Data tidak lengkap";
                    header('Location: gudang_antapani.php');
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
                    header('Location: gudang_antapani.php');
                    exit();
                }
                
                $currentData = $currentResult->fetch_assoc();
                $barang_id = $currentData['barang_id'];
                $current_stok_awal = $currentData['stok_awal'];
                $stok_diff = $stok_awal - $current_stok_awal;
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // If stock is increased, check Central stock
                    if ($stok_diff > 0) {
                        // Check if item exists in Central
                        $checkCentralSql = "SELECT id, stok_awal, stok_terpakai FROM gudang_stok 
                                          WHERE gudang_id = 23 AND barang_id = ?";
                        $checkCentralStmt = $conn->prepare($checkCentralSql);
                        $checkCentralStmt->bind_param("i", $barang_id);
                        $checkCentralStmt->execute();
                        $checkCentralResult = $checkCentralStmt->get_result();
                        
                        if ($checkCentralResult->num_rows > 0) {
                            $centralRow = $checkCentralResult->fetch_assoc();
                            $centralStokId = $centralRow['id'];
                            $centralStokAwal = $centralRow['stok_awal'];
                            $centralStokTerpakai = $centralRow['stok_terpakai'];
                            $centralStokAkhir = $centralStokAwal - $centralStokTerpakai;
                            
                            // Check if Central has enough stock
                            if ($stok_diff > $centralStokAkhir) {
                                throw new Exception("Penambahan stok melebihi stok yang tersedia di Gudang Central (tersedia: $centralStokAkhir)");
                            }
                            
                            // Reduce stock from Central
                            $updateCentralSql = "UPDATE gudang_stok SET 
                                              stok_awal = stok_awal - ?,
                                              stok_sisa = (stok_awal - ?) - stok_terpakai,
                                              modified_by = ?,
                                              updated_at = NOW()
                                          WHERE id = ?";
                            $updateCentralStmt = $conn->prepare($updateCentralSql);
                            $updateCentralStmt->bind_param("ddii", $stok_diff, $stok_diff, $user_id, $centralStokId);
                            
                            if (!$updateCentralStmt->execute()) {
                                throw new Exception("Gagal update stok di Gudang Central: " . $updateCentralStmt->error);
                            }
                            
                            // Add history records
                            $historyInsertSql = "INSERT INTO stok_history 
                                               (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, created_by) 
                                               VALUES (NOW(), ?, 23, ?, 'transfer_out', 'Transfer ke Jawil Dago (edit)', ?)";
                            $historyInsertStmt = $conn->prepare($historyInsertSql);
                            $historyInsertStmt->bind_param("idi", $barang_id, $stok_diff, $user_id);
                            $historyInsertStmt->execute();
                            
                            $historyInsertSql = "INSERT INTO stok_history 
                                               (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, created_by) 
                                               VALUES (NOW(), ?, 13, ?, 'transfer_in', 'Transfer dari Gudang Central (edit)', ?)";
                            $historyInsertStmt = $conn->prepare($historyInsertSql);
                            $historyInsertStmt->bind_param("idi", $barang_id, $stok_diff, $user_id);
                            $historyInsertStmt->execute();
                        }
                    } else if ($stok_diff < 0) {
                        // If stock is decreased, return to Central if exists
                        $checkCentralSql = "SELECT id FROM gudang_stok WHERE gudang_id = 23 AND barang_id = ?";
                        $checkCentralStmt = $conn->prepare($checkCentralSql);
                        $checkCentralStmt->bind_param("i", $barang_id);
                        $checkCentralStmt->execute();
                        $checkCentralResult = $checkCentralStmt->get_result();
                        
                        if ($checkCentralResult->num_rows > 0) {
                            $centralRow = $checkCentralResult->fetch_assoc();
                            $centralStokId = $centralRow['id'];
                            $return_amount = abs($stok_diff);
                            
                            // Return stock to Central
                            $updateCentralSql = "UPDATE gudang_stok SET 
                                              stok_awal = stok_awal + ?,
                                              stok_sisa = (stok_awal + ?) - stok_terpakai,
                                              modified_by = ?,
                                              updated_at = NOW()
                                          WHERE id = ?";
                            $updateCentralStmt = $conn->prepare($updateCentralSql);
                            $updateCentralStmt->bind_param("ddii", $return_amount, $return_amount, $user_id, $centralStokId);
                            
                            if (!$updateCentralStmt->execute()) {
                                throw new Exception("Gagal mengembalikan stok ke Gudang Central: " . $updateCentralStmt->error);
                            }
                            
                            // Add history records
                            $historyInsertSql = "INSERT INTO stok_history 
                                               (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, created_by) 
                                               VALUES (NOW(), ?, 13, ?, 'return_out', 'Pengembalian ke Gudang Central (edit)', ?)";
                            $historyInsertStmt = $conn->prepare($historyInsertSql);
                            $historyInsertStmt->bind_param("idi", $barang_id, $return_amount, $user_id);
                            $historyInsertStmt->execute();
                            
                            $historyInsertSql = "INSERT INTO stok_history 
                                               (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, created_by) 
                                               VALUES (NOW(), ?, 23, ?, 'return_in', 'Pengembalian dari Jawil Dago (edit)', ?)";
                            $historyInsertStmt = $conn->prepare($historyInsertSql);
                            $historyInsertStmt->bind_param("idi", $barang_id, $return_amount, $user_id);
                            $historyInsertStmt->execute();
                        }
                    }
                    
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
                    
                    if ($stok_diff != 0) {
                        $_SESSION['success'] .= $stok_diff > 0 
                            ? " dan stok di Gudang Central dikurangi" 
                            : " dan stok dikembalikan ke Gudang Central";
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = $e->getMessage();
                }
                
                header('Location: gudang_antapani.php');
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
                // Debug logging
                error_log("=== UPDATE SISA STOK DEBUG ===");
                error_log("POST data: " . json_encode($_POST));
                error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
                
                if (empty($_POST['id']) || !isset($_POST['sisa_stok'])) {
                    $error_msg = 'Data tidak lengkap. ID: ' . ($_POST['id'] ?? 'empty') . ', Sisa Stok: ' . ($_POST['sisa_stok'] ?? 'empty');
                    error_log($error_msg);
                    $response = ['success' => false, 'message' => $error_msg];
                    if ($expectsJson) {
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit();
                    }
                    throw new Exception($error_msg);
                }
                
                $id = (int)$_POST['id'];
                $sisa_stok = (int)$_POST['sisa_stok'];
                $user_id = $_SESSION['user_id'] ?? 0;
                
                error_log("Processing update - ID: $id, Sisa Stok: $sisa_stok, User ID: $user_id, Gudang ID: $gudang_id");
                
                // Validasi sisa stok tidak melebihi stok awal
                $checkSql = "SELECT stok_awal FROM gudang_stok WHERE id = ? AND gudang_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                if (!$checkStmt) {
                    error_log("Prepare failed: " . $conn->error);
                    throw new Exception("Database prepare error: " . $conn->error);
                }
                $checkStmt->bind_param("ii", $id, $gudang_id);
                if (!$checkStmt->execute()) {
                    error_log("Execute failed: " . $checkStmt->error);
                    throw new Exception("Database execute error: " . $checkStmt->error);
                }
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows === 0) {
                    error_log("Data stok tidak ditemukan untuk ID: $id dan Gudang ID: $gudang_id");
                    throw new Exception("Data stok tidak ditemukan");
                }
                
                $row = $checkResult->fetch_assoc();
                $stok_awal = $row['stok_awal'];
                
                error_log("Validasi stok - Stok Awal: $stok_awal, Sisa Stok Request: $sisa_stok");
                
                if ($sisa_stok > $stok_awal) {
                    error_log("Validasi gagal: Sisa stok ($sisa_stok) > Stok awal ($stok_awal)");
                    throw new Exception("Sisa stok tidak boleh melebihi stok awal");
                }
                
                // Hitung stok terpakai berdasarkan stok awal dikurangi sisa stok
                $stok_terpakai = $stok_awal - $sisa_stok;
                
                error_log("Update calculation - Stok Terpakai: $stok_terpakai");
                
                // Update stok terpakai
                $updateSql = "UPDATE gudang_stok SET 
                            stok_terpakai = ?,
                            stok_sisa = ?,
                            modified_by = ?,
                            updated_at = NOW()
                        WHERE id = ? AND gudang_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                if (!$updateStmt) {
                    error_log("Update prepare failed: " . $conn->error);
                    throw new Exception("Database prepare error: " . $conn->error);
                }
                $updateStmt->bind_param("iiiii", $stok_terpakai, $sisa_stok, $user_id, $id, $gudang_id);
                
                if ($updateStmt->execute()) {
                    error_log("Update berhasil untuk ID: $id");
                    // Clear any output buffer
                    ob_end_clean();
                    
                    header('Content-Type: application/json');
                    $response = [
                        'success' => true,
                        'message' => 'Sisa stok berhasil diupdate',
                        'data' => [
                            'stok_awal' => $stok_awal,
                            'sisa_stok' => $sisa_stok,
                            'stok_terpakai' => $stok_terpakai,
                            'stok_akhir' => $sisa_stok
                        ]
                    ];
                    error_log("Response: " . json_encode($response));
                    echo json_encode($response);
                    exit();
                } else {
                    error_log("Update gagal untuk ID: $id - Error: " . $updateStmt->error);
                    throw new Exception("Gagal update stok: " . $updateStmt->error);
                }
                break;

            case 'quick_stok_masuk':
                $user_id = $_SESSION['user_id'] ?? 0;
                if (!$user_id) {
                    throw new Exception("Unauthorized");
                }

                $tanggal_raw = $_POST['tanggal'] ?? date('Y-m-d');
                $tanggal_dt = DateTime::createFromFormat('Y-m-d', (string)$tanggal_raw);
                $tanggal = ($tanggal_dt && $tanggal_dt->format('Y-m-d') === (string)$tanggal_raw) ? $tanggal_dt->format('Y-m-d') : date('Y-m-d');
                $keterangan_transaksi = trim((string)($_POST['keterangan'] ?? ''));
                $created_at = $tanggal . ' ' . date('H:i:s');

                $items_data_json = $_POST['items_data'] ?? '[]';
                $items = json_decode($items_data_json, true);

                if (!is_array($items) || count($items) === 0) {
                    throw new Exception("Item stok masuk tidak boleh kosong");
                }

                $conn->begin_transaction();
                try {
                    $no_transaksi_date = str_replace('-', '', $tanggal);
                    $seqSql = "SELECT COUNT(*) as count
                        FROM transaksi_stok
                        WHERE tanggal = ? AND jenis_transaksi = 'masuk'";
                    $seqStmt = $conn->prepare($seqSql);
                    if (!$seqStmt) {
                        throw new Exception("Database prepare error: " . $conn->error);
                    }
                    $seqStmt->bind_param("s", $tanggal);
                    if (!$seqStmt->execute()) {
                        throw new Exception("Database execute error: " . $seqStmt->error);
                    }
                    $seqRow = $seqStmt->get_result()->fetch_assoc();
                    $sequence = str_pad(((int)($seqRow['count'] ?? 0)) + 1, 4, '0', STR_PAD_LEFT);
                    $no_transaksi = "SM-" . $no_transaksi_date . $sequence;

                    $insertTransaksiSql = "INSERT INTO transaksi_stok
                        (no_transaksi, tanggal, gudang_id, jenis_transaksi, keterangan, created_by, jumlah, barang_id)
                        VALUES (?, ?, ?, 'masuk', ?, ?, 0, NULL)";
                    $insertTransaksiStmt = $conn->prepare($insertTransaksiSql);
                    if (!$insertTransaksiStmt) {
                        throw new Exception("Database prepare error: " . $conn->error);
                    }
                    $insertTransaksiStmt->bind_param("ssisi", $no_transaksi, $tanggal, $gudang_id, $keterangan_transaksi, $user_id);
                    if (!$insertTransaksiStmt->execute()) {
                        throw new Exception("Gagal membuat transaksi stok masuk: " . $insertTransaksiStmt->error);
                    }
                    $transaksi_stok_id = (int)$conn->insert_id;

                    $insertDetailSql = "INSERT INTO detail_transaksi_stok
                        (transaksi_stok_id, barang_id, detail_barang, jumlah)
                        VALUES (?, ?, ?, ?)";
                    $insertDetailStmt = $conn->prepare($insertDetailSql);
                    if (!$insertDetailStmt) {
                        throw new Exception("Database prepare error: " . $conn->error);
                    }

                    $checkByIdSql = "SELECT id, barang_id, stok_awal, stok_terpakai, stok_sisa, jumlah
                        FROM gudang_stok
                        WHERE id = ? AND gudang_id = ? FOR UPDATE";
                    $checkByIdStmt = $conn->prepare($checkByIdSql);
                    if (!$checkByIdStmt) {
                        throw new Exception("Database prepare error: " . $conn->error);
                    }

                    $checkByBarangSql = "SELECT id, barang_id, stok_awal, stok_terpakai, stok_sisa, jumlah
                        FROM gudang_stok
                        WHERE gudang_id = ? AND barang_id = ? FOR UPDATE";
                    $checkByBarangStmt = $conn->prepare($checkByBarangSql);
                    if (!$checkByBarangStmt) {
                        throw new Exception("Database prepare error: " . $conn->error);
                    }

                    $updateSql = "UPDATE gudang_stok SET
                            jumlah = jumlah + ?,
                            stok_awal = stok_awal + ?,
                            stok_sisa = stok_sisa + ?,
                            modified_by = ?,
                            updated_at = NOW()
                        WHERE id = ? AND gudang_id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    if (!$updateStmt) {
                        throw new Exception("Database prepare error: " . $conn->error);
                    }

                    $insertSql = "INSERT INTO gudang_stok
                            (gudang_id, barang_id, jumlah, stok_awal, stok_terpakai, stok_sisa, stok_minimum, expire_date, created_by, modified_by, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 0, ?, 0, NULL, ?, ?, NOW(), NOW())";
                    $insertStmt = $conn->prepare($insertSql);
                    if (!$insertStmt) {
                        throw new Exception("Database prepare error: " . $conn->error);
                    }

                    $insertHistorySql = "INSERT INTO gudang_stok_history (
                            gudang_stok_id, gudang_id, barang_id,
                            stok_awal_sebelum, stok_awal_sesudah,
                            stok_terpakai_sebelum, stok_terpakai_sesudah,
                            stok_sisa_sebelum, stok_sisa_sesudah,
                            jenis_perubahan, jumlah_perubahan, keterangan, referensi, created_by, created_at
                        ) VALUES (
                            ?, ?, ?,
                            ?, ?,
                            ?, ?,
                            ?, ?,
                            'masuk', ?, ?, ?, ?, ?
                        )";
                    $insertHistoryStmt = $conn->prepare($insertHistorySql);
                    if (!$insertHistoryStmt) {
                        throw new Exception("Database prepare error: " . $conn->error);
                    }

                    $totalUpdated = 0;
                    $totalQty = 0;
                    foreach ($items as $item) {
                        $stok_id = (int)($item['stok_id'] ?? 0);
                        $barang_id = (int)($item['barang_id'] ?? 0);
                        $jumlah = (int)($item['jumlah'] ?? 0);
                        $detail_barang = trim((string)($item['detail_barang'] ?? ''));

                        if ($jumlah <= 0) {
                            throw new Exception("Jumlah stok masuk harus lebih dari 0");
                        }
                        $totalQty += $jumlah;

                        $row = null;
                        if ($stok_id > 0) {
                            $checkByIdStmt->bind_param("ii", $stok_id, $gudang_id);
                            if (!$checkByIdStmt->execute()) {
                                throw new Exception("Database execute error: " . $checkByIdStmt->error);
                            }
                            $res = $checkByIdStmt->get_result();
                            if ($res && $res->num_rows > 0) {
                                $row = $res->fetch_assoc();
                                $barang_id = (int)($row['barang_id'] ?? $barang_id);
                            }
                        }

                        if (!$row) {
                            if ($barang_id <= 0) {
                                throw new Exception("Barang tidak valid");
                            }
                            $checkByBarangStmt->bind_param("ii", $gudang_id, $barang_id);
                            if (!$checkByBarangStmt->execute()) {
                                throw new Exception("Database execute error: " . $checkByBarangStmt->error);
                            }
                            $res = $checkByBarangStmt->get_result();
                            if ($res && $res->num_rows > 0) {
                                $row = $res->fetch_assoc();
                                $stok_id = (int)($row['id'] ?? 0);
                            }
                        }

                        $stok_awal_sebelum = (int)($row['stok_awal'] ?? 0);
                        $stok_terpakai_sebelum = (int)($row['stok_terpakai'] ?? 0);
                        $stok_sisa_sebelum = (int)($row['stok_sisa'] ?? ($stok_awal_sebelum - $stok_terpakai_sebelum));
                        $stok_awal_sesudah = $stok_awal_sebelum + $jumlah;
                        $stok_terpakai_sesudah = $stok_terpakai_sebelum;
                        $stok_sisa_sesudah = $stok_sisa_sebelum + $jumlah;

                        if ($stok_id > 0) {
                            $updateStmt->bind_param("iiiiii", $jumlah, $jumlah, $jumlah, $user_id, $stok_id, $gudang_id);
                            if (!$updateStmt->execute()) {
                                throw new Exception("Gagal update stok masuk: " . $updateStmt->error);
                            }
                            $totalUpdated++;
                        } else {
                            $insertStmt->bind_param("iiiiiii", $gudang_id, $barang_id, $jumlah, $jumlah, $jumlah, $user_id, $user_id);
                            if (!$insertStmt->execute()) {
                                throw new Exception("Gagal insert stok masuk: " . $insertStmt->error);
                            }
                            $stok_id = (int)$conn->insert_id;
                            $totalUpdated++;
                        }

                        $keterangan_history = $keterangan_transaksi !== '' ? $keterangan_transaksi : 'Stok masuk (quick)';
                        if ($detail_barang !== '') {
                            $keterangan_history .= " | Detail: " . $detail_barang;
                        }

                        $referensi = 'QUICK_MASUK';
                        $insertHistoryStmt->bind_param(
                            "iiiiiiiiiissis",
                            $stok_id,
                            $gudang_id,
                            $barang_id,
                            $stok_awal_sebelum,
                            $stok_awal_sesudah,
                            $stok_terpakai_sebelum,
                            $stok_terpakai_sesudah,
                            $stok_sisa_sebelum,
                            $stok_sisa_sesudah,
                            $jumlah,
                            $keterangan_history,
                            $referensi,
                            $user_id,
                            $created_at
                        );
                        if (!$insertHistoryStmt->execute()) {
                            throw new Exception("Gagal insert laporan stok: " . $insertHistoryStmt->error);
                        }

                        $detail_barang_to_save = $detail_barang !== '' ? $detail_barang : '0';
                        $insertDetailStmt->bind_param("iisi", $transaksi_stok_id, $barang_id, $detail_barang_to_save, $jumlah);
                        if (!$insertDetailStmt->execute()) {
                            throw new Exception("Gagal insert detail transaksi: " . $insertDetailStmt->error);
                        }
                    }

                    $updateTransaksiTotalSql = "UPDATE transaksi_stok SET jumlah = ? WHERE id = ?";
                    $updateTransaksiTotalStmt = $conn->prepare($updateTransaksiTotalSql);
                    if ($updateTransaksiTotalStmt) {
                        $updateTransaksiTotalStmt->bind_param("ii", $totalQty, $transaksi_stok_id);
                        $updateTransaksiTotalStmt->execute();
                    }

                    $conn->commit();
                    $_SESSION['success'] = "Stok masuk berhasil disimpan ($totalUpdated item) - No: $no_transaksi";
                    $response = ['success' => true, 'message' => 'Stok masuk berhasil disimpan', 'data' => ['count' => $totalUpdated]];
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
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
    
    if ($expectsJson) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => null
        ]);
        exit();
    } else {
        // For regular form submissions
        $_SESSION['error'] = $e->getMessage();
        header('Location: gudang_antapani.php');
        exit();
    }
}

if ($expectsJson) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    if (!headers_sent()) {
        header('Location: gudang_antapani.php');
    }
    exit();
}
