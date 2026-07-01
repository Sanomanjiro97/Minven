<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

// Check if user has access to edit surat jalan
if (!hasAccess('surat_jalan')) {
    header('Location: ../../unauthorized.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $surat_jalan_id = isset($_POST['surat_jalan_id']) ? intval($_POST['surat_jalan_id']) : 0;
    $status_pembayaran = isset($_POST['status_pembayaran']) ? $_POST['status_pembayaran'] : '';
    $keterangan_pembayaran = isset($_POST['keterangan_pembayaran']) ? trim($_POST['keterangan_pembayaran']) : '';
    
    // Validate input
    if ($surat_jalan_id <= 0) {
        $_SESSION['error_message'] = "ID Surat Jalan tidak valid!";
        header('Location: view.php?id=' . $surat_jalan_id);
        exit();
    }
    
    if (empty($status_pembayaran)) {
        $_SESSION['error_message'] = "Status pembayaran harus dipilih!";
        header('Location: view.php?id=' . $surat_jalan_id);
        exit();
    }
    
    // Validate status pembayaran
    $valid_statuses = ['belum_dibayar', 'sebagian', 'lunas'];
    if (!in_array($status_pembayaran, $valid_statuses)) {
        $_SESSION['error_message'] = "Status pembayaran tidak valid!";
        header('Location: view.php?id=' . $surat_jalan_id);
        exit();
    }
    
    // Start transaction
    $GLOBALS['conn']->begin_transaction();
    
    try {
        // Get current surat jalan data
        $sql_get = "SELECT po_id, status_pembayaran FROM surat_jalan WHERE id = ?";
        $stmt_get = $GLOBALS['conn']->prepare($sql_get);
        $stmt_get->bind_param("i", $surat_jalan_id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        $surat_jalan_data = $result_get->fetch_assoc();
        
        if (!$surat_jalan_data) {
            throw new Exception("Surat Jalan tidak ditemukan!");
        }
        
        $old_status = $surat_jalan_data['status_pembayaran'];
        $po_id = $surat_jalan_data['po_id'];
        
        // Update surat jalan status pembayaran
        // Check if updated_at and keterangan_pembayaran columns exist
        $check_columns_sql = "SHOW COLUMNS FROM surat_jalan LIKE 'updated_at'";
        $check_columns_result = $GLOBALS['conn']->query($check_columns_sql);
        $has_updated_at = $check_columns_result->num_rows > 0;
        
        $check_keterangan_sql = "SHOW COLUMNS FROM surat_jalan LIKE 'keterangan_pembayaran'";
        $check_keterangan_result = $GLOBALS['conn']->query($check_keterangan_sql);
        $has_keterangan = $check_keterangan_result->num_rows > 0;
        
        if ($has_updated_at && $has_keterangan) {
            // Both columns exist
            $sql_update = "UPDATE surat_jalan SET status_pembayaran = ?, keterangan_pembayaran = ?, updated_at = NOW() WHERE id = ?";
            $stmt_update = $GLOBALS['conn']->prepare($sql_update);
            if (!$stmt_update) {
                throw new Exception("Prepare statement failed: " . $GLOBALS['conn']->error);
            }
            $stmt_update->bind_param("ssi", $status_pembayaran, $keterangan_pembayaran, $surat_jalan_id);
        } elseif ($has_updated_at) {
            // Only updated_at exists
            $sql_update = "UPDATE surat_jalan SET status_pembayaran = ?, updated_at = NOW() WHERE id = ?";
            $stmt_update = $GLOBALS['conn']->prepare($sql_update);
            if (!$stmt_update) {
                throw new Exception("Prepare statement failed: " . $GLOBALS['conn']->error);
            }
            $stmt_update->bind_param("si", $status_pembayaran, $surat_jalan_id);
        } else {
            // Neither column exists, use basic update
            $sql_update = "UPDATE surat_jalan SET status_pembayaran = ? WHERE id = ?";
            $stmt_update = $GLOBALS['conn']->prepare($sql_update);
            if (!$stmt_update) {
                throw new Exception("Prepare statement failed: " . $GLOBALS['conn']->error);
            }
            $stmt_update->bind_param("si", $status_pembayaran, $surat_jalan_id);
        }
        
        if (!$stmt_update->execute()) {
            throw new Exception("Gagal mengupdate status pembayaran: " . $stmt_update->error);
        }
        
        // If status changed to 'lunas', update PO status to 'delivered'
        if ($status_pembayaran == 'lunas' && $old_status != 'lunas') {
            // Check if updated_at column exists in purchase_order table
            $check_po_updated_sql = "SHOW COLUMNS FROM purchase_order LIKE 'updated_at'";
            $check_po_updated_result = $GLOBALS['conn']->query($check_po_updated_sql);
            $po_has_updated_at = $check_po_updated_result && $check_po_updated_result->num_rows > 0;
            
            if ($po_has_updated_at) {
                $sql_update_po = "UPDATE purchase_order SET status = 'delivered', updated_at = NOW() WHERE id = ?";
            } else {
                $sql_update_po = "UPDATE purchase_order SET status = 'delivered' WHERE id = ?";
            }
            
            $stmt_update_po = $GLOBALS['conn']->prepare($sql_update_po);
            if (!$stmt_update_po) {
                throw new Exception("Prepare statement for PO update failed: " . $GLOBALS['conn']->error);
            }
            $stmt_update_po->bind_param("i", $po_id);
            
            if (!$stmt_update_po->execute()) {
                throw new Exception("Gagal mengupdate status PO: " . $stmt_update_po->error);
            }
        }
        
        // Log the payment status change (if user_activity_log table exists)
        try {
            $check_log_table_sql = "SHOW TABLES LIKE 'user_activity_log'";
            $check_log_table_result = $GLOBALS['conn']->query($check_log_table_sql);
            
            if ($check_log_table_result && $check_log_table_result->num_rows > 0) {
                // Check if the required columns exist
                $check_log_columns_sql = "SHOW COLUMNS FROM user_activity_log LIKE 'activity_type'";
                $check_log_columns_result = $GLOBALS['conn']->query($check_log_columns_sql);
                
                if ($check_log_columns_result && $check_log_columns_result->num_rows > 0) {
                    $log_sql = "INSERT INTO user_activity_log (user_id, activity_type, activity_description, related_id, related_table, created_at) 
                                VALUES (?, 'update_payment', ?, ?, 'surat_jalan', NOW())";
                    $log_stmt = $GLOBALS['conn']->prepare($log_sql);
                    if ($log_stmt) {
                        $activity_description = "Mengubah status pembayaran dari '$old_status' menjadi '$status_pembayaran'";
                        if ($keterangan_pembayaran) {
                            $activity_description .= " - Keterangan: $keterangan_pembayaran";
                        }
                        $log_stmt->bind_param("isi", $_SESSION['user_id'], $activity_description, $surat_jalan_id);
                        $log_stmt->execute();
                    }
                }
            }
        } catch (Exception $log_error) {
            // Log error is not critical, continue with the main operation
            error_log("Failed to log payment update: " . $log_error->getMessage());
        }
        
        // Commit transaction
        $GLOBALS['conn']->commit();
        
        // Set success message
        $status_text = '';
        switch($status_pembayaran) {
            case 'belum_dibayar':
                $status_text = 'Belum Dibayar';
                break;
            case 'sebagian':
                $status_text = 'Dibayar Sebagian';
                break;
            case 'lunas':
                $status_text = 'Lunas';
                break;
        }
        
        $_SESSION['success_message'] = "Status pembayaran berhasil diubah menjadi: $status_text";
        
        // Redirect back to view page
        header('Location: view.php?id=' . $surat_jalan_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $GLOBALS['conn']->rollback();
        $_SESSION['error_message'] = "Gagal mengupdate status pembayaran: " . $e->getMessage();
        header('Location: view.php?id=' . $surat_jalan_id);
        exit();
    }
} else {
    // If not POST request, redirect to index
    header('Location: index.php');
    exit();
}
?> 