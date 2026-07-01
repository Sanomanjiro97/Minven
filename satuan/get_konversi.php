<?php
require_once '../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT ks.*, 
        s1.nama_satuan as satuan_asal,
        s2.nama_satuan as satuan_tujuan
        FROM konversi_satuan ks
        JOIN satuan s1 ON ks.satuan_asal_id = s1.id
        JOIN satuan s2 ON ks.satuan_tujuan_id = s2.id
        WHERE ks.satuan_asal_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<table class="table table-sm">';
    echo '<thead><tr><th>Dari</th><th>Ke</th><th>Nilai Konversi</th><th>Aksi</th></tr></thead>';
    echo '<tbody>';
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['satuan_asal']) . '</td>';
        echo '<td>' . htmlspecialchars($row['satuan_tujuan']) . '</td>';
        echo '<td>' . htmlspecialchars($row['nilai_konversi']) . '</td>';
        echo '<td>';
        echo '<button class="btn btn-sm btn-danger delete-konversi" data-id="' . $row['id'] . '">';
        echo '<i class="bx bx-trash"></i>';
        echo '</button>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p class="text-muted">Belum ada data konversi</p>';
}
?>