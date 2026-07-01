<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

include_once "../../config.php";
include_once "../../includes/access_check.php";

// Check access for surat_jalan_create
// checkAccess('surat_jalan_create'); // Uncomment and implement if needed

include_once "../../templates/header.php";
include_once "../../templates/navbar.php";

// Fetch approved Purchase Orders
$approved_pos = [];
// Query untuk mengambil daftar PO yang sudah diapprove
$sql_po = "SELECT po.id, po.no_po as po_number, po.supplier_id, po.tanggal, s.nama_supplier 
           FROM purchase_order po
           LEFT JOIN supplier s ON po.supplier_id = s.id
           WHERE po.status = 'approved'
           ORDER BY po.tanggal DESC";
$result_po = $conn->query($sql_po);

if ($result_po) {
    if ($result_po->num_rows > 0) {
        while ($row_po = $result_po->fetch_assoc()) {
            $approved_pos[] = $row_po;
        }
    }
} else {
    echo "<div class='alert alert-danger'>Error fetching approved POs: " . $conn->error . "</div>";
}
// Add error checking for the first query (line 16)
if ($result_po) {
    if ($result_po->num_rows > 0) {
        while ($row_po = $result_po->fetch_assoc()) {
            $approved_pos[] = $row_po;
        }
    }
} else {
    // Handle query error, e.g., log the error or display a message
    echo "<div class='alert alert-danger'>Error fetching approved POs: " . $conn->error . "</div>";
}

// Fetch suppliers for display
$suppliers = [];
$sql_supplier = "SELECT id, nama_supplier as name FROM supplier";
$result_supplier = $conn->query($sql_supplier);

// Add error checking for the second query (line 27)
if ($result_supplier) {
    if ($result_supplier->num_rows > 0) {
        while ($row_supplier = $result_supplier->fetch_assoc()) {
            $suppliers[$row_supplier['id']] = $row_supplier['name'];
        }
    }
} else {
    // Handle query error, e.g., log the error or display a message
    echo "<div class='alert alert-danger'>Error fetching suppliers: " . $conn->error . "</div>";
}

?>
<div class="container mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
    <h2>Buat Surat Jalan</h2>
    </div>
    <div class="card-body">
    <form id="suratJalanForm" action="create.php" method="POST">
        <div class="form-group">
            <label for="po_id">Pilih Purchase Order (Approved):</label>
            <select class="form-control" id="po_id" name="po_id" required>
                <option value="">-- Pilih PO --</option>
                <?php foreach ($approved_pos as $po): ?>
                    <option value="<?php echo $po['id']; ?>" data-supplier-id="<?php echo $po['supplier_id']; ?>">
                        <?php echo $po['po_number']; ?> - <?php echo $po['nama_supplier']; ?> (<?php echo date('d/m/Y', strtotime($po['tanggal'])); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="poDetails" style="display: none;">
            <h4>Detail Purchase Order</h4>
            <p><strong>Nomor PO:</strong> <span id="po_number"></span></p>
            <p><strong>Supplier:</strong> <span id="supplier_name"></span></p>
            <p><strong>Tanggal PO:</strong> <span id="po_date"></span></p>

            <h5>Item PO:</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Satuan</th>
                    </tr>
                </thead>
                <tbody id="poItemsTableBody">
                    <!-- PO items will be loaded here via AJAX -->
                </tbody>
            </table>
        </div>

        <div class="form-group">
            <label for="surat_jalan_number">Nomor Surat Jalan:</label>
            <input type="text" class="form-control" id="surat_jalan_number" name="surat_jalan_number" required>
        </div>

        <div class="form-group">
            <label for="surat_jalan_date">Tanggal Surat Jalan:</label>
            <input type="date" class="form-control" id="surat_jalan_date" name="surat_jalan_date" required>
        </div>

        <div class="form-group">
            <label for="status_pembayaran">Status Pembayaran:</label>
            <select class="form-control" id="status_pembayaran" name="status_pembayaran" required>
                <option value="">-- Pilih Status Pembayaran --</option>
                <option value="belum_dibayar">Belum Dibayar</option>
                <option value="sebagian">Dibayar Sebagian</option>
                <option value="lunas">Lunas</option>
            </select>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Simpan Surat Jalan</button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#po_id').change(function() {
        var poId = $(this).val();
        if (poId) {
            $.ajax({
                url: '../../ajax/get_po_details.php',
                type: 'GET',
                data: { po_id: poId },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        $('#poDetails').show();
                        $('#po_number').text(data.po.po_number);
                        $('#supplier_name').text(data.po.supplier_name);
                        $('#po_date').text(data.po.po_date);

                        var itemsHtml = '';
                        if (data.items && data.items.length > 0) {
                            $.each(data.items, function(index, item) {
                                itemsHtml += '<tr>';
                                itemsHtml += '<td>' + (item.barang_name || item.nama_barang || 'N/A') + '</td>';
                                itemsHtml += '<td>' + (item.quantity || 0) + '</td>';
                                itemsHtml += '<td>' + (item.satuan_name || item.nama_satuan || '') + '</td>';
                                itemsHtml += '</tr>';
                            });
                        } else {
                            itemsHtml = '<tr><td colspan="3" class="text-center">Tidak ada item barang</td></tr>';
                        }
                        $('#poItemsTableBody').html(itemsHtml);
                    } else {
                        $('#poDetails').hide();
                        alert('Gagal mengambil detail PO: ' + data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $('#poDetails').hide();
                    console.error(xhr.responseText);
                    alert('Terjadi kesalahan saat mengambil detail PO.');
                }
            });
        } else {
            $('#poDetails').hide();
        }
    });
});
</script>
<?php
include_once "../../templates/footer.php";

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_id = $_POST['po_id'];
    $surat_jalan_number = $_POST['surat_jalan_number'];
    $surat_jalan_date = $_POST['surat_jalan_date'];
    $status_pembayaran = $_POST['status_pembayaran'];
    
    // Remove debug output
    // echo "<pre>Debug: " . print_r($_POST, true) . "</pre>";
    
    // Insert into surat_jalan table
    $sql = "INSERT INTO surat_jalan (po_id, surat_jalan_number, surat_jalan_date, status_pembayaran, created_by) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssi", $po_id, $surat_jalan_number, $surat_jalan_date, $status_pembayaran, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $surat_jalan_id = $stmt->insert_id;
        
        // Get items from the Purchase Order and insert into surat_jalan_items
        $items_sql = "SELECT 
                        dpo.barang_id,
                        dpo.jumlah as quantity,
                        COALESCE(cpd.satuan_asal_id, dpo.satuan_id, b.satuan_id, 78) as satuan_id
                      FROM detail_purchase_order dpo
                      JOIN barang b ON dpo.barang_id = b.id
                      LEFT JOIN conversi_po_detail cpd ON cpd.detail_purchase_order_id = dpo.id
                      WHERE dpo.purchase_order_id = ?";
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param("i", $po_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        // Insert items into surat_jalan_items table
        $insert_item_sql = "INSERT INTO surat_jalan_items (surat_jalan_id, barang_id, quantity, satuan_id) 
                            VALUES (?, ?, ?, ?)";
        $insert_item_stmt = $conn->prepare($insert_item_sql);
        
        while ($item = $items_result->fetch_assoc()) {
            $satuan_id = isset($item['satuan_id']) ? (int)$item['satuan_id'] : 78;
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;

            $insert_item_stmt->bind_param("iiii", $surat_jalan_id, $item['barang_id'], $quantity, $satuan_id);
            $insert_item_stmt->execute();
        }
        
        // If payment status is "lunas", update PO status to "delivered"
        if ($status_pembayaran === 'lunas') {
            $update_po_sql = "UPDATE purchase_order SET status = 'delivered' WHERE id = ?";
            $update_stmt = $conn->prepare($update_po_sql);
            $update_stmt->bind_param("i", $po_id);
            $update_stmt->execute();
        }
        
        // Store success message in session instead of redirecting directly
        $_SESSION['success_message'] = "Surat Jalan berhasil dibuat!";
        echo "<script>window.location.href = 'view.php?id=" . $surat_jalan_id . "';</script>";
        exit();
    } else {
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }
}
?>
