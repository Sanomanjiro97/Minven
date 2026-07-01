<?php
include_once "../config.php";

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'po' => null, 'items' => []];

if (isset($_GET['po_id'])) {
    $po_id = $_GET['po_id'];

    // Fetch PO details and ensure it's approved
    // Query untuk mengambil detail PO dan memastikan statusnya approved
    $sql_po = "SELECT po.id, po.no_po as po_number, DATE_FORMAT(po.tanggal, '%d/%m/%Y') as po_date, s.nama_supplier as supplier_name 
               FROM purchase_order po
               JOIN supplier s ON po.supplier_id = s.id
               WHERE po.id = ? AND po.status = 'approved'";
    
    // Query untuk mengambil item-item PO
    $sql_items = "SELECT 
                    poi.id,
                    poi.barang_id,
                    poi.jumlah as quantity,
                    b.nama_barang as barang_name,
                    sa.nama_satuan as satuan_name
                  FROM detail_purchase_order poi
                  JOIN barang b ON poi.barang_id = b.id
                  LEFT JOIN conversi_po_detail cpd ON cpd.detail_purchase_order_id = poi.id
                  LEFT JOIN satuan sa ON sa.id = COALESCE(cpd.satuan_asal_id, poi.satuan_id, b.satuan_id)
                  WHERE poi.purchase_order_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $po_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $stmt_po = $conn->prepare($sql_po);
    $stmt_po->bind_param("i", $po_id);
    $stmt_po->execute();
    $result_po = $stmt_po->get_result();
    
    if ($row_po = $result_po->fetch_assoc()) {
        $response['po'] = $row_po;
    }

    while ($row_item = $result_items->fetch_assoc()) {
        $response['items'][] = $row_item;
    }

    $response['success'] = true;
}

echo json_encode($response);?>
