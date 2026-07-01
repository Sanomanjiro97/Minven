<?php
session_start();
require_once '../config.php';
include '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Manajemen Lokasi Mapping</h1>
    
    <?php
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Daftar Lokasi Mapping
            <div class="float-end">
                <select id="statusFilter" class="form-select form-select-sm d-inline-block w-auto me-2">
                    <option value="">Semua Status</option>
                    <option value="1">Aktif</option>
                    <option value="0">Tidak Aktif</option>
                </select>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> Tambah Lokasi
                </button>
            </div>
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Lokasi</th>
                        <th>Nama Lokasi</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM lokasi_mapping ORDER BY kode_lokasi";
                    $result = $conn->query($sql);
                    $no = 1;

                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $no++ . "</td>";
                        echo "<td>" . htmlspecialchars($row['kode_lokasi']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['nama_lokasi']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['deskripsi']) . "</td>";
                        echo "<td>" . ($row['aktif'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Tidak Aktif</span>') . "</td>";
                        echo "<td>
                                <button type='button' class='btn btn-sm btn-warning edit-btn' 
                                    data-id='" . $row['id'] . "'
                                    data-kode='" . htmlspecialchars($row['kode_lokasi']) . "'
                                    data-nama='" . htmlspecialchars($row['nama_lokasi']) . "'
                                    data-deskripsi='" . htmlspecialchars($row['deskripsi']) . "'
                                    data-bs-toggle='modal' data-bs-target='#editModal'>
                                    <i class='fas fa-edit'></i>
                                </button>
                                <button type='button' class='btn btn-sm " . ($row['aktif'] ? 'btn-danger' : 'btn-success') . " toggle-btn'
                                    data-id='" . $row['id'] . "'
                                    data-status='" . $row['aktif'] . "'
                                    data-nama='" . htmlspecialchars($row['nama_lokasi']) . "'
                                    data-bs-toggle='modal' data-bs-target='#toggleModal'
                                    title='" . ($row['aktif'] ? 'Nonaktifkan' : 'Aktifkan') . " lokasi'>
                                    <i class='fas " . ($row['aktif'] ? 'fa-times' : 'fa-check') . "'></i>
                                </button>
                            </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Lokasi Mapping</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_lokasi.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="kode_lokasi" class="form-label">Kode Lokasi*</label>
                        <input type="text" class="form-control" id="kode_lokasi" name="kode_lokasi" required>
                    </div>
                    <div class="mb-3">
                        <label for="nama_lokasi" class="form-label">Nama Lokasi*</label>
                        <input type="text" class="form-control" id="nama_lokasi" name="nama_lokasi" required>
                    </div>
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Lokasi Mapping</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_lokasi.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_kode_lokasi" class="form-label">Kode Lokasi*</label>
                        <input type="text" class="form-control" name="kode_lokasi" id="edit_kode_lokasi" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama_lokasi" class="form-label">Nama Lokasi*</label>
                        <input type="text" class="form-control" name="nama_lokasi" id="edit_nama_lokasi" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Modal -->
<div class="modal fade" id="toggleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Toggle Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="toggle_message">Apakah Anda yakin ingin mengubah status lokasi mapping ini?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form action="process_lokasi.php" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" id="toggle_id">
                    <button type="submit" class="btn btn-primary" id="toggle_button">Toggle</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status filter functionality
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('#datatablesSimple tbody tr');
    
    statusFilter.addEventListener('change', function() {
        const selectedStatus = this.value;
        
        tableRows.forEach(row => {
            const statusCell = row.querySelector('td:nth-child(5)');
            const statusBadge = statusCell.querySelector('.badge');
            const isActive = statusBadge.classList.contains('bg-success');
            
            if (selectedStatus === '') {
                row.style.display = '';
            } else if (selectedStatus === '1' && isActive) {
                row.style.display = '';
            } else if (selectedStatus === '0' && !isActive) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Edit button click handler
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_kode_lokasi').value = this.dataset.kode;
            document.getElementById('edit_nama_lokasi').value = this.dataset.nama;
            document.getElementById('edit_deskripsi').value = this.dataset.deskripsi;
        });
    });

    // Toggle button click handler
    document.querySelectorAll('.toggle-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const status = this.dataset.status;
            const nama = this.dataset.nama;
            
            document.getElementById('toggle_id').value = id;
            
            const messageEl = document.getElementById('toggle_message');
            const buttonEl = document.getElementById('toggle_button');
            
            if (status == 1) {
                messageEl.textContent = `Apakah Anda yakin ingin menonaktifkan lokasi "${nama}"?`;
                buttonEl.textContent = 'Nonaktifkan';
                buttonEl.className = 'btn btn-danger';
            } else {
                messageEl.textContent = `Apakah Anda yakin ingin mengaktifkan lokasi "${nama}"?`;
                buttonEl.textContent = 'Aktifkan';
                buttonEl.className = 'btn btn-success';
            }
        });
    });
});
</script>

<?php include '../templates/footer.php'; ?>

<style>
.card-header .float-end {
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 768px) {
    .card-header .float-end {
        flex-direction: column;
        align-items: stretch;
        gap: 5px;
    }
    
    .card-header .float-end .form-select {
        width: 100% !important;
    }
    
    .card-header .float-end .btn {
        width: 100%;
    }
}

.toggle-btn {
    transition: all 0.3s ease;
}

.toggle-btn:hover {
    transform: scale(1.1);
}

.badge {
    font-size: 0.8em;
}

.table td {
    vertical-align: middle;
}

.btn-sm {
    margin: 0 2px;
}
</style> 