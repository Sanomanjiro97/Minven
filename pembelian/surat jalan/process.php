<?php
include_once "../../config.php";
include_once "../../includes/access_check.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $po_id = $_POST['po_id'];
    $surat_jalan_number = $_POST['surat_jalan_number'];
    $surat_jalan_date = $_POST['surat_jalan_date'];
    $created_at = date('Y-m-d H:i:s');

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert into surat_jalan table
        $sql_sj = "INSERT INTO surat_jalan (po_id, surat_jalan_number, surat_jalan_date, created_at) VALUES (?, ?, ?, ?)";
        $stmt_sj = $conn->prepare($sql_sj);
        if (!$stmt_sj) {
            throw new Exception("Prepare statement for surat_jalan failed: " . $conn->error);
        }
        $stmt_sj->bind_param("isss", $po_id, $surat_jalan_number, $surat_jalan_date, $created_at);
        $stmt_sj->execute();
        $surat_jalan_id = $conn->insert_id;

        // Get items from the approved Purchase Order
        $sql_po_items = "SELECT 
                            dpo.barang_id,
                            dpo.jumlah as quantity,
                            COALESCE(cpd.satuan_asal_id, dpo.satuan_id, b.satuan_id, 78) as satuan_id
                         FROM detail_purchase_order dpo
                         JOIN barang b ON dpo.barang_id = b.id
                         LEFT JOIN conversi_po_detail cpd ON cpd.detail_purchase_order_id = dpo.id
                         WHERE dpo.purchase_order_id = ?";


        $stmt_po_items = $conn->prepare($sql_po_items);
        if (!$stmt_po_items) {
            throw new Exception("Prepare statement for PO items failed: (" . $conn->errno . ") " . $conn->error);
        }

        $stmt_po_items->bind_param("i", $po_id);
        $stmt_po_items->execute();
        $result_po_items = $stmt_po_items->get_result();


        // Insert items into surat_jalan_items table
        $sql_sj_item = "INSERT INTO surat_jalan_items (surat_jalan_id, barang_id, quantity, satuan_id) 
                        VALUES (?, ?, ?, ?)"; 
        $stmt_sj_item = $conn->prepare($sql_sj_item);
        if (!$stmt_sj_item) {
            throw new Exception("Prepare statement for surat_jalan_items failed: " . $conn->error);
        }

        while ($row_item = $result_po_items->fetch_assoc()) {
            $satuan_id = isset($row_item['satuan_id']) ? (int)$row_item['satuan_id'] : 78;
            $quantity = isset($row_item['quantity']) ? (int)$row_item['quantity'] : 0;

            $stmt_sj_item->bind_param("iiii", $surat_jalan_id, $row_item['barang_id'], $quantity, $satuan_id);
            $stmt_sj_item->execute();
        }

        // Update PO status to 'delivered'
        $sql_update_po = "UPDATE purchase_order SET status = 'delivered' WHERE id = ?";
        $stmt_update_po = $conn->prepare($sql_update_po);
        if (!$stmt_update_po) {
            throw new Exception("Prepare statement for update PO status failed: " . $conn->error);
        }
        $stmt_update_po->bind_param("i", $po_id);
        $stmt_update_po->execute();

        // Commit transaction
        $conn->commit();
        echo "<script>alert('Surat Jalan berhasil dibuat!'); window.location.href='index.php';</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo "<script>alert('Gagal membuat Surat Jalan: " . $e->getMessage() . "'); window.location.href='create.php';</script>";
    }
}
?>
