<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Check access untuk view barang
redirectIfNoAccess('barang', 'view', '../../dashboard.php');

$sql = "SELECT b.id, b.kode_barang, b.nama_barang, k.nama_kategori, s.nama_satuan
        FROM barang b
        LEFT JOIN kategori k ON b.kategori_id = k.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        ORDER BY b.kode_barang, b.id";
$result = $conn->query($sql);
$no = 1;

$barang_list = [];
$sql_all = "SELECT id, kode_barang, nama_barang FROM barang ORDER BY kode_barang, id";
$res_all = $conn->query($sql_all);
if ($res_all instanceof mysqli_result) {
    while ($r = $res_all->fetch_assoc()) {
        $barang_list[] = [
            'id' => (int)$r['id'],
            'kode_barang' => (string)($r['kode_barang'] ?? ''),
            'nama_barang' => (string)($r['nama_barang'] ?? '')
        ];
    }
    $res_all->free();
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- Tombol Add hanya muncul jika ada permission -->
        <?php if (hasAccess('barang', 'add')): ?>
        <a href="create.php" class="btn btn-primary mb-3">
            <i class='bx bx-plus'></i> Tambah Barang
        </a>
        <?php endif; ?>
        
        <!-- Table dengan conditional buttons -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                        <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                        <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                        <td><?= htmlspecialchars($row['nama_satuan']) ?></td>
                        <td>
                            <?php if (hasAccess('barang', 'setup_split')): ?>
                            <button type="button"
                                    class="btn btn-sm btn-info btn-setup-split"
                                    title="Setup Split"
                                    data-id="<?= (int)$row['id'] ?>"
                                    data-kode="<?= htmlspecialchars((string)$row['kode_barang']) ?>"
                                    data-nama="<?= htmlspecialchars((string)$row['nama_barang']) ?>">
                                <i class='bx bx-git-merge'></i>
                            </button>
                            <?php endif; ?>

                            <?php if (hasAccess('barang', 'edit')): ?>
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                                <i class='bx bx-edit'></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAccess('barang', 'delete')): ?>
                            <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $row['id'] ?>)">
                                <i class='bx bx-trash'></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if (hasAccess('barang', 'setup_split')): ?>
    <div class="modal fade" id="splitSetupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Setup Split Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="parent_barang_id" value="">
                    <div class="mb-2">
                        <div class="fw-semibold" id="parent_barang_label"></div>
                        <div class="text-muted small">Pilih barang yang menjadi hasil split untuk barang ini.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="split_barang_ids">Daftar Barang Split</label>
                        <label class="visually-hidden" for="split_barang_ids_search">Cari barang split</label>
                        <select id="split_barang_ids" class="form-select" multiple>
                            <?php foreach ($barang_list as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars((string)$b['kode_barang']) ?> - <?= htmlspecialchars((string)$b['nama_barang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-warning mb-0">
                        Jika daftar split dikosongkan, maka barang ini tidak akan muncul opsi split saat konfirmasi PO.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSaveSplitSetup">Simpan</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
        <div id="minvenToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="minvenToastBody"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    function confirmDelete(id) {
        if (confirm('Apakah Anda yakin ingin menghapus data ini?')) {
            window.location.href = 'process.php?action=delete&id=' + id;
        }
    }

    $(document).ready(function() {
        function showToast(message, variant) {
            const toastEl = document.getElementById('minvenToast');
            const toastBody = document.getElementById('minvenToastBody');
            if (!toastEl || !toastBody || typeof bootstrap === 'undefined' || !bootstrap.Toast) {
                alert(message);
                return;
            }
            const v = String(variant || 'success');
            toastEl.className = 'toast align-items-center border-0 text-bg-' + v;
            const closeBtn = toastEl.querySelector('button.btn-close');
            if (closeBtn) {
                closeBtn.className = 'btn-close btn-close-white me-2 m-auto';
            }
            toastBody.textContent = String(message || '');
            const t = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2200 });
            t.show();
        }

        <?php if (hasAccess('barang', 'setup_split')): ?>
        const modalEl = document.getElementById('splitSetupModal');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

        $('#split_barang_ids').select2({ theme: 'bootstrap-5', width: '100%' });
        $('#split_barang_ids').on('select2:open', function() {
            const input = document.querySelector('.select2-container--open .select2-search__field');
            if (!input) return;
            if (!input.id) input.id = 'split_barang_ids_search';
            if (!input.name) input.name = 'split_barang_ids_search';
            if (!input.getAttribute('aria-label')) input.setAttribute('aria-label', 'Cari barang split');
        });

        async function loadSetup(parentId) {
            const resp = await fetch(`process.php?action=get_split_setup&id=${encodeURIComponent(parentId)}`);
            const json = await resp.json();
            if (!resp.ok || !json || json.status !== 'success') {
                throw new Error((json && json.message) ? json.message : 'Gagal mengambil setup');
            }
            return json;
        }

        async function saveSetup(parentId, splitIds) {
            const form = new FormData();
            form.append('action', 'save_split_setup');
            form.append('parent_barang_id', String(parentId));
            for (const id of splitIds) {
                form.append('split_barang_ids[]', String(id));
            }
            const resp = await fetch('process.php', { method: 'POST', body: form });
            const json = await resp.json();
            if (!resp.ok || !json || json.status !== 'success') {
                throw new Error((json && json.message) ? json.message : 'Gagal menyimpan setup');
            }
            return json;
        }

        $(document).on('click', '.btn-setup-split', async function() {
            const parentId = String($(this).data('id') || '');
            const kode = String($(this).data('kode') || '');
            const nama = String($(this).data('nama') || '');
            if (!parentId) return;

            $('#parent_barang_id').val(parentId);
            $('#parent_barang_label').text(`${kode} - ${nama}`);

            const allOptions = $('#split_barang_ids option').toArray().map(o => String(o.value));
            $('#split_barang_ids').val([]).trigger('change');
            for (const opt of allOptions) {
                const $opt = $('#split_barang_ids option[value="' + opt.replace(/"/g, '\\"') + '"]');
                $opt.prop('disabled', opt === parentId);
            }

            try {
                const setup = await loadSetup(parentId);
                const selected = (setup.split_barang_ids || []).map(String).filter(id => id && id !== parentId);
                $('#split_barang_ids').val(selected).trigger('change');
            } catch (err) {
                showToast((err && err.message) ? err.message : 'Gagal mengambil setup', 'danger');
            }

            if (modal) modal.show();
        });

        $('#btnSaveSplitSetup').on('click', async function() {
            const parentId = $('#parent_barang_id').val();
            const selected = ($('#split_barang_ids').val() || []).map(String).filter(id => id && id !== parentId);
            try {
                await saveSetup(parentId, selected);
                if (modal) modal.hide();
                showToast('Setup split berhasil disimpan', 'success');
            } catch (err) {
                showToast((err && err.message) ? err.message : 'Gagal menyimpan setup', 'danger');
            }
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>
