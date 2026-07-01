<?php
session_start();
require_once '../config.php';
require_once '../includes/page_access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access to menu_access management
if (!hasAccess('menu_access', 'view')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk mengelola Menu Access";
    header("Location: ../dashboard.php");
    exit();
}

// Get all menu access records with role and menu information
$sql = "SELECT ma.*, r.nama_role 
        FROM menu_access ma 
        INNER JOIN roles r ON ma.role_id = r.id 
        ORDER BY r.nama_role, ma.menu_name";
if (!isset($conn) || !$conn) {
    $_SESSION['error'] = "Database connection error";
    header("Location: ../dashboard.php");
    exit();
}
$result = $conn->query($sql);

// Get all roles for filter
$roles_sql = "SELECT id, nama_role FROM roles ORDER BY nama_role";
$roles_result = $conn->query($roles_sql);
$roles = [];
while ($role = $roles_result->fetch_assoc()) {
    $roles[$role['id']] = $role['nama_role'];
}

// Get all available menus
$menus_sql = "SELECT DISTINCT menu_name FROM menu_access ORDER BY menu_name";
$menus_result = $conn->query($menus_sql);
$menus = [];
while ($menu = $menus_result->fetch_assoc()) {
    $menus[] = $menu['menu_name'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Access Management - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        
        .card-header {
            background: #0008f9;
            color: white;
            border-radius: 0.75rem 0.75rem 0 0 !important;
            padding: 15px 20px;
        }
        
        .table thead th {
            background: #0008f9;
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        
        .permission-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .permission-yes {
            background-color: #d4edda;
            color: #155724;
        }
        .permission-no {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-shield-check me-2"></i>
                            Menu Access Management
                        </h5>
                        <?php if (hasAccess('menu_access', 'add')): ?>
                        <a href="create.php" class="btn btn-light btn-sm">
                            <i class="bi bi-plus-circle me-1"></i>
                            Tambah Menu Access
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Filter Section -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="filter_role" class="form-label">Filter Role:</label>
                                <select id="filter_role" class="form-select">
                                    <option value="">Semua Role</option>
                                    <?php foreach ($roles as $id => $nama): ?>
                                    <option value="<?= $id ?>"><?= htmlspecialchars($nama) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_menu" class="form-label">Filter Menu:</label>
                                <select id="filter_menu" class="form-select">
                                    <option value="">Semua Menu</option>
                                    <?php foreach ($menus as $menu): ?>
                                    <option value="<?= $menu ?>"><?= htmlspecialchars($menu) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_permission" class="form-label">Filter Permission:</label>
                                <select id="filter_permission" class="form-select">
                                    <option value="">Semua Permission</option>
                                    <option value="view">View</option>
                                    <option value="add">Add</option>
                                    <option value="edit">Edit</option>
                                    <option value="delete">Delete</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button id="clear_filters" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-x-circle me-1"></i>
                                    Clear Filters
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="menuAccessTable" class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Role</th>
                                        <th>Menu</th>
                                        <th>View</th>
                                        <th>Add</th>
                                        <th>Edit</th>
                                        <th>Delete</th>
                                        <th>WA</th>
                                        <th>Approve</th>
                                        <th>Export</th>
                                        <th>Import</th>
                                        <th>Created</th>
                                        <th>Updated</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($row['nama_role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['menu_name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge permission-badge <?= $row['can_view'] ? 'permission-yes' : 'permission-no' ?>">
                                                <?= $row['can_view'] ? '✓' : '✗' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge permission-badge <?= $row['can_add'] ? 'permission-yes' : 'permission-no' ?>">
                                                <?= $row['can_add'] ? '✓' : '✗' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge permission-badge <?= $row['can_edit'] ? 'permission-yes' : 'permission-no' ?>">
                                                <?= $row['can_edit'] ? '✓' : '✗' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge permission-badge <?= $row['can_delete'] ? 'permission-yes' : 'permission-no' ?>">
                                                <?= $row['can_delete'] ? '✓' : '✗' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge permission-badge <?= isset($row['can_send_wa']) && $row['can_send_wa'] ? 'permission-yes' : 'permission-no' ?>">
                                                <?= isset($row['can_send_wa']) && $row['can_send_wa'] ? '✓' : '✗' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge permission-badge <?= $row['can_approve'] ? 'permission-yes' : 'permission-no' ?>">
                                                <?= $row['can_approve'] ? '✓' : '✗' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge permission-badge <?= $row['can_export'] ? 'permission-yes' : 'permission-no' ?>">
                                                <?= $row['can_export'] ? '✓' : '✗' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge permission-badge <?= $row['can_import'] ? 'permission-yes' : 'permission-no' ?>">
                                                <?= $row['can_import'] ? '✓' : '✗' ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if (hasAccess('menu_access', 'view')): ?>
                                                <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-info" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if (hasAccess('menu_access', 'edit')): ?>
                                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if (hasAccess('menu_access', 'delete')): ?>
                                                <button type="button" class="btn btn-danger" title="Delete" 
                                                        onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_role']) ?>', '<?= htmlspecialchars($row['menu_name']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus menu access untuk:</p>
                    <ul>
                        <li><strong>Role:</strong> <span id="deleteRole"></span></li>
                        <li><strong>Menu:</strong> <span id="deleteMenu"></span></li>
                    </ul>
                    <p class="text-danger"><strong>Perhatian:</strong> Tindakan ini tidak dapat dibatalkan!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form id="deleteForm" method="POST" action="delete.php" style="display: inline;">
                        <input type="hidden" id="deleteId" name="id" value="">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#menuAccessTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
                },
                pageLength: 25,
                order: [[1, 'asc'], [2, 'asc']]
            });

            // Filter functionality
            $('#filter_role').on('change', function() {
                var roleId = $(this).val();
                if (roleId) {
                    table.column(1).search($(this).find('option:selected').text()).draw();
                } else {
                    table.column(1).search('').draw();
                }
            });

            $('#filter_menu').on('change', function() {
                var menuName = $(this).val();
                if (menuName) {
                    table.column(2).search(menuName).draw();
                } else {
                    table.column(2).search('').draw();
                }
            });

            $('#filter_permission').on('change', function() {
                var permission = $(this).val();
                if (permission) {
                    // Search in the specific permission column
                    var columnIndex = 0;
                    switch(permission) {
                        case 'view': columnIndex = 3; break;
                        case 'add': columnIndex = 4; break;
                        case 'edit': columnIndex = 5; break;
                        case 'delete': columnIndex = 6; break;
                    }
                    table.column(columnIndex).search('✓').draw();
                } else {
                    table.search('').columns().search('').draw();
                }
            });

            $('#clear_filters').on('click', function() {
                $('#filter_role, #filter_menu, #filter_permission').val('');
                table.search('').columns().search('').draw();
            });
        });

        function confirmDelete(id, role, menu) {
            $('#deleteId').val(id);
            $('#deleteRole').text(role);
            $('#deleteMenu').text(menu);
            $('#deleteModal').modal('show');
        }
    </script>
</body>
</html> 