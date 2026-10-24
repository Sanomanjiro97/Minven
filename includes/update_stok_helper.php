<?php
// Helper function untuk update stok terpakai tanpa session
// File ini tidak boleh memanggil session_start()

if (!function_exists('updateStokTerpakaiHelper')) {
    function updateStokTerpakaiHelper($conn, $gudang_id = null) {
        try {
            $conn->begin_transaction();
            
            // Get barang yang memiliki transaksi keluar di gudang tertentu
            $sql_barang_with_transaksi = "SELECT DISTINCT 
                                            ts.gudang_id, 
                                            dts.barang_id
                                        FROM detail_transaksi_stok dts
                                        JOIN transaksi_stok ts ON dts.transaksi_stok_id = ts.id
                                        WHERE ts.jenis_transaksi = 'keluar'
                                        AND ts.deleted_at IS NULL";
            
            if ($gudang_id) {
                $sql_barang_with_transaksi .= " AND ts.gudang_id = ?";
                $stmt = $conn->prepare($sql_barang_with_transaksi);
                if (!$stmt) {
                    throw new Exception('Failed to prepare barang query: ' . $conn->error);
                }
                $stmt->bind_param("i", $gudang_id);
            } else {
                $stmt = $conn->prepare($sql_barang_with_transaksi);
                if (!$stmt) {
                    throw new Exception('Failed to prepare barang query: ' . $conn->error);
                }
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $updated_count = 0;
            
            while ($row = $result->fetch_assoc()) {
                $gudang_id = $row['gudang_id'];
                $barang_id = $row['barang_id'];
                
                // Calculate total stok keluar for this barang in this gudang
                $sql_stok_keluar = "SELECT COALESCE(SUM(dts.jumlah), 0) as total_keluar
                                    FROM detail_transaksi_stok dts
                                    JOIN transaksi_stok ts ON dts.transaksi_stok_id = ts.id
                                    WHERE ts.gudang_id = ?
                                    AND dts.barang_id = ?
                                    AND ts.jenis_transaksi = 'keluar'
                                    AND ts.deleted_at IS NULL";
                
                $stmt_keluar = $conn->prepare($sql_stok_keluar);
                if (!$stmt_keluar) {
                    throw new Exception('Failed to prepare stok keluar query: ' . $conn->error);
                }
                $stmt_keluar->bind_param("ii", $gudang_id, $barang_id);
                $stmt_keluar->execute();
                $result_keluar = $stmt_keluar->get_result();
                $total_keluar = $result_keluar->fetch_assoc()['total_keluar'];
                $stmt_keluar->close();
                
                // Check if gudang_stok record exists
                $sql_check = "SELECT id, stok_terpakai FROM gudang_stok WHERE gudang_id = ? AND barang_id = ?";
                $stmt_check = $conn->prepare($sql_check);
                if (!$stmt_check) {
                    throw new Exception('Failed to prepare check query: ' . $conn->error);
                }
                $stmt_check->bind_param("ii", $gudang_id, $barang_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->num_rows > 0) {
                    // Update existing record
                    $current_data = $result_check->fetch_assoc();
                    $current_stok_terpakai = $current_data['stok_terpakai'];
                    
                    if ($current_stok_terpakai != $total_keluar) {
                        $sql_update = "UPDATE gudang_stok 
                                       SET stok_terpakai = ?, 
                                           updated_at = NOW() 
                                       WHERE gudang_id = ? AND barang_id = ?";
                        $stmt_update = $conn->prepare($sql_update);
                        if (!$stmt_update) {
                            throw new Exception('Failed to prepare update query: ' . $conn->error);
                        }
                        $stmt_update->bind_param("dii", $total_keluar, $gudang_id, $barang_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                        $updated_count++;
                    }
                    $stmt_check->close();
                } else {
                    // Create new record if doesn't exist
                    $sql_insert = "INSERT INTO gudang_stok (gudang_id, barang_id, stok_awal, stok_terpakai, stok_minimum, created_at, updated_at) 
                                   VALUES (?, ?, 0, ?, 0, NOW(), NOW())";
                    $stmt_insert = $conn->prepare($sql_insert);
                    if (!$stmt_insert) {
                        throw new Exception('Failed to prepare insert query: ' . $conn->error);
                    }
                    $stmt_insert->bind_param("iid", $gudang_id, $barang_id, $total_keluar);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                    $stmt_check->close();
                    $updated_count++;
                }
            }
            $stmt->close();
            
            $conn->commit();
            return ['success' => true, 'updated_count' => $updated_count];
            
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
