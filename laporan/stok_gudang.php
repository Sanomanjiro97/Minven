<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$gudang_id = isset($_GET['gudang_id']) ? $_GET['gudang_id'] : '';
$jenis_perubahan = isset($_GET['jenis_perubahan']) ? $_GET['jenis_perubahan'] : '';
$view_type = isset($_GET['view_type']) ? $_GET['view_type'] : 'current'; // current atau history

if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
    $tmp = $start_date;
    $start_date = $end_date;
    $end_date = $tmp;
}

$start_dt = $start_date . ' 00:00:00';
$end_dt_excl = date('Y-m-d', strtotime($end_date . ' +1 day')) . ' 00:00:00';

// Query untuk dropdown gudang
$gudang_sql = "SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang";
$gudang_options = $conn->query($gudang_sql);

// Query untuk dropdown jenis perubahan
$jenis_perubahan_options = array(
    '' => 'Semua Jenis',
    'masuk' => 'Stok Masuk',
    'keluar' => 'Stok Keluar',
    'transfer_in' => 'Transfer Masuk',
    'transfer_out' => 'Transfer Keluar',
    'reset' => 'Reset Stok',
    'update' => 'Update Data'
);

if ($view_type == 'history') {
    $period_expr = "DATE(gsh.created_at)";
    if ($period === 'weekly') {
        $period_expr = "CONCAT(YEAR(gsh.created_at), '-W', LPAD(WEEK(gsh.created_at, 1), 2, '0'))";
    } elseif ($period === 'monthly') {
        $period_expr = "DATE_FORMAT(gsh.created_at, '%Y-%m')";
    }

    $sql = "SELECT
                grp.period_label,
                g.nama_gudang,
                b.kode_barang,
                b.nama_barang,
                k.nama_kategori,
                s.nama_satuan,
                gsh_first.stok_sisa_sebelum AS saldo_awal,
                grp.masuk,
                grp.keluar,
                gsh_last.stok_sisa_sesudah AS saldo_akhir
            FROM (
                SELECT
                    $period_expr AS period_label,
                    gsh.gudang_id,
                    gsh.barang_id,
                    MIN(gsh.id) AS first_id,
                    MAX(gsh.id) AS last_id,
                    COALESCE(SUM(CASE WHEN gsh.jenis_perubahan IN ('masuk', 'transfer_in') THEN gsh.jumlah_perubahan ELSE 0 END), 0) AS masuk,
                    COALESCE(SUM(CASE WHEN gsh.jenis_perubahan IN ('keluar', 'transfer_out') THEN ABS(gsh.jumlah_perubahan) ELSE 0 END), 0) AS keluar
                FROM gudang_stok_history gsh
                WHERE 1=1";

    $params = array();
    $types = "";

    // Filter berdasarkan gudang
    if (!empty($gudang_id)) {
        $sql .= " AND gsh.gudang_id = ?";
        $params[] = $gudang_id;
        $types .= "i";
    }

    // Filter berdasarkan jenis perubahan
    if (!empty($jenis_perubahan)) {
        $sql .= " AND gsh.jenis_perubahan = ?";
        $params[] = $jenis_perubahan;
        $types .= "s";
    }

    // Filter berdasarkan tanggal
    if (!empty($start_date) && !empty($end_date)) {
        $sql .= " AND gsh.created_at >= ? AND gsh.created_at < ?";
        $params[] = $start_dt;
        $params[] = $end_dt_excl;
        $types .= "ss";
    }

    $sql .= " GROUP BY $period_expr, gsh.gudang_id, gsh.barang_id
            ) grp
            LEFT JOIN gudang_stok_history gsh_first ON gsh_first.id = grp.first_id
            LEFT JOIN gudang_stok_history gsh_last ON gsh_last.id = grp.last_id
            LEFT JOIN barang b ON grp.barang_id = b.id
            LEFT JOIN kategori k ON b.kategori_id = k.id
            LEFT JOIN satuan s ON b.satuan_id = s.id
            LEFT JOIN gudang g ON grp.gudang_id = g.id
            ORDER BY grp.period_label DESC, b.nama_barang, g.nama_gudang";

} else {
    // Query untuk mengambil data stok saat ini (existing query)
    $sql = "SELECT 
                gs.id,
                b.kode_barang,
                b.nama_barang,
                k.nama_kategori,
                s.nama_satuan,
                gs.stok_awal,
                gs.stok_terpakai,
                (gs.stok_awal - gs.stok_terpakai) as stok_akhir,
                gs.stok_minimum,
                gs.expire_date,
                gs.updated_at,
                u.nama as updated_by,
                g.nama_gudang
            FROM gudang_stok gs
            LEFT JOIN barang b ON gs.barang_id = b.id
            LEFT JOIN kategori k ON b.kategori_id = k.id
            LEFT JOIN satuan s ON b.satuan_id = s.id
            LEFT JOIN users u ON gs.modified_by = u.id
            LEFT JOIN gudang g ON gs.gudang_id = g.id
            WHERE 1=1";

    $params = array();
    $types = "";

    // Filter berdasarkan gudang
    if (!empty($gudang_id)) {
        $sql .= " AND gs.gudang_id = ?";
        $params[] = $gudang_id;
        $types .= "i";
    } else {
        // Jika tidak ada filter gudang, tampilkan semua gudang
        $sql .= " AND gs.gudang_id IN (SELECT id FROM gudang)";
    }

    // Filter berdasarkan tanggal
    if (!empty($start_date) && !empty($end_date)) {
        $sql .= " AND DATE(gs.updated_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";
    }

    $sql .= " ORDER BY b.nama_barang, g.nama_gudang";
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

// Bind parameters jika ada
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok Gudang - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 0.75rem 0.75rem 0 0 !important;
            padding: 15px 20px;
        }
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
        }
        .btn i {
            margin-right: 0.5rem;
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
        .table tbody tr:hover {
            background-color: #f1f3f5;
        }
        .status-aman {
            color: #198754;
            font-weight: 500;
        }
        .status-minimum {
            color: #ffc107;
            font-weight: 500;
        }
        .badge.bg-success-light {
            background-color: rgba(25, 135, 84, 0.15);
            color: #198754;
        }
        .badge.bg-warning-light {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        .badge.bg-danger-light {
            background-color: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        .badge.bg-info-light {
            background-color: rgba(13, 202, 240, 0.15);
            color: #0dcaf0;
        }
        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .page-header img {
            height: 40px;
            margin-right: 1rem;
        }
        .page-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
        }
        .nav-pills .nav-link {
            border-radius: 0.5rem;
            margin-right: 0.5rem;
        }
        .nav-pills .nav-link.active {
            background-color: #0008f9;
            color: white;
        }
        .change-positive {
            color: #198754;
            font-weight: 500;
        }
        .change-negative {
            color: #dc3545;
            font-weight: 500;
        }
        .change-neutral {
            color: #6c757d;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="page-header">
            <img src="../asset/cjawilnew.png" alt="Logo">
            <h2>Laporan Stok Gudang</h2>
        </div>

        <!-- Navigation Pills -->
        <ul class="nav nav-pills mb-4" id="viewTypeTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $view_type == 'current' ? 'active' : '' ?>" 
                        id="current-tab" data-bs-toggle="pill" data-bs-target="#current" 
                        type="button" role="tab" onclick="changeViewType('current')">
                    <i class='bx bx-package'></i> Stok Saat Ini
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $view_type == 'history' ? 'active' : '' ?>" 
                        id="history-tab" data-bs-toggle="pill" data-bs-target="#history" 
                        type="button" role="tab" onclick="changeViewType('history')">
                    <i class='bx bx-history'></i> History Perubahan
                </button>
            </li>
        </ul>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="view_type" value="<?= $view_type ?>">
                    
                    <div class="col-md-2">
                        <label class="form-label">Periode</label>
                        <select name="period" class="form-select">
                            <option value="daily" <?= $period == 'daily' ? 'selected' : '' ?>>Harian</option>
                            <option value="weekly" <?= $period == 'weekly' ? 'selected' : '' ?>>Mingguan</option>
                            <option value="monthly" <?= $period == 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Gudang</label>
                        <select name="gudang_id" class="form-select">
                            <option value="">Semua Gudang</option>
                            <?php 
                            mysqli_data_seek($gudang_options, 0);
                            while($gudang = $gudang_options->fetch_assoc()): 
                            ?>
                                <option value="<?= $gudang['id'] ?>" <?= $gudang_id == $gudang['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($gudang['nama_gudang']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if ($view_type == 'history'): ?>
                    <div class="col-md-2">
                        <label class="form-label">Jenis Perubahan</label>
                        <select name="jenis_perubahan" class="form-select">
                            <?php foreach ($jenis_perubahan_options as $key => $value): ?>
                                <option value="<?= $key ?>" <?= $jenis_perubahan == $key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Awal</label>
                        <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" class="btn btn-primary"><i class='bx bx-filter-alt'></i>Filter</button>
                            <a href="stok_gudang.php" class="btn btn-outline-secondary"><i class='bx bx-reset'></i>Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <?= $view_type == 'history' ? 'History Perubahan Stok Barang' : 'Daftar Stok Barang' ?>
            </div>
            <div class="card-body">
                <?php 
                $total_records = $result->num_rows;
                $selected_gudang = '';
                if (!empty($gudang_id)) {
                    mysqli_data_seek($gudang_options, 0);
                    while($gudang = $gudang_options->fetch_assoc()) {
                        if ($gudang['id'] == $gudang_id) {
                            $selected_gudang = $gudang['nama_gudang'];
                            break;
                        }
                    }
                }
                ?>
                <div class="alert alert-info mb-3">
                    <strong>Filter Aktif:</strong><br>
                    • Tampilan: <?= $view_type == 'history' ? 'History Perubahan' : 'Stok Saat Ini' ?><br>
                    • Periode: <?= ucfirst($period) ?><br>
                    • Tanggal: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?><br>
                    • Gudang: <?= !empty($selected_gudang) ? $selected_gudang : 'Semua Gudang' ?><br>
                    <?php if ($view_type == 'history' && !empty($jenis_perubahan)): ?>
                    • Jenis Perubahan: <?= $jenis_perubahan_options[$jenis_perubahan] ?><br>
                    <?php endif; ?>
                    • Total Data: <?= number_format($total_records) ?> record(s)
                </div>

                <div class="table-responsive">
                    <?php if ($view_type == 'history'): ?>
                    <!-- History Table -->
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Periode</th>
                                <th>Gudang</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th>Saldo Awal</th>
                                <th>Masuk</th>
                                <th>Keluar</th>
                                <th>Saldo Akhir</th>
                                <th>Perubahan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while($row = $result->fetch_assoc()): 
                                $saldo_awal = (float)($row['saldo_awal'] ?? 0);
                                $masuk = (float)($row['masuk'] ?? 0);
                                $keluar = (float)($row['keluar'] ?? 0);
                                $saldo_akhir = (float)($row['saldo_akhir'] ?? 0);
                                $perubahan = $saldo_akhir - $saldo_awal;
                                $periode_label = $row['period_label'] ?? '';
                                $periode_tampil = $periode_label;
                                if ($period === 'daily' && !empty($periode_label)) {
                                    $periode_tampil = date('d/m/Y', strtotime($periode_label));
                                }
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($periode_tampil) ?></td>
                                <td><?= htmlspecialchars($row['nama_gudang']) ?></td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                <td><?= htmlspecialchars($row['nama_satuan'] ?? '-') ?></td>
                                <td class="text-end"><?= number_format($saldo_awal) ?></td>
                                <td class="text-end"><?= number_format($masuk) ?></td>
                                <td class="text-end"><?= number_format($keluar) ?></td>
                                <td class="text-end fw-bold"><?= number_format($saldo_akhir) ?></td>
                                <td class="text-end">
                                    <?php if ($perubahan > 0): ?>
                                        <span class="change-positive">+<?= number_format($perubahan) ?></span>
                                    <?php elseif ($perubahan < 0): ?>
                                        <span class="change-negative"><?= number_format($perubahan) ?></span>
                                    <?php else: ?>
                                        <span class="change-neutral">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <!-- Current Stock Table -->
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Gudang</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th>Stok Awal</th>
                                <th>Stok Terpakai</th>
                                <th>Stok Akhir</th>
                                <th>Stok Minimum</th>
                                <th>Updated by</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while($row = $result->fetch_assoc()): 
                                $status = $row['stok_akhir'] <= $row['stok_minimum'] ? 'Stok Minimum' : 'Stok Aman';
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['updated_at'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_gudang']) ?></td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                <td><?= htmlspecialchars($row['nama_satuan']) ?></td>
                                <td class="text-begin"><?= number_format($row['stok_awal']) ?></td>
                                <td class="text-begin"><?= number_format($row['stok_terpakai']) ?></td>
                                <td class="text-begin"><?= number_format($row['stok_akhir']) ?></td>
                                <td class="text-begin"><?= number_format($row['stok_minimum']) ?></td>
                                <td><?= !empty($row['updated_by']) ? htmlspecialchars($row['updated_by']) : 'N/A' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function changeViewType(type) {
    const url = new URL(window.location);
    url.searchParams.set('view_type', type);
    window.location.href = url.toString();
}

function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    
    // Get filter values
    const period = document.querySelector('[name="period"]').value;
    const startDate = document.querySelector('[name="start_date"]').value;
    const endDate = document.querySelector('[name="end_date"]').value;
    const gudangSelect = document.querySelector('[name="gudang_id"]');
    const selectedGudang = gudangSelect.options[gudangSelect.selectedIndex].text;
    const viewType = document.querySelector('[name="view_type"]').value;
    const jenisPerubahan = document.querySelector('[name="jenis_perubahan"]') ? 
                          document.querySelector('[name="jenis_perubahan"]').options[document.querySelector('[name="jenis_perubahan"]').selectedIndex].text : '';
    
    // Header styling
    const header = `
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="../asset/cjawilnew.png" alt="Logo" style="height: 80px; margin-bottom: 10px;"/>
            <div style="font-size: 24px; font-weight: bold; margin-bottom: 5px;">
                LAPORAN STOK GUDANG
            </div>
            <div style="font-size: 14px; margin-bottom: 5px;">
                Tampilan: ${viewType === 'history' ? 'History Perubahan' : 'Stok Saat Ini'}
            </div>
            <div style="font-size: 14px; margin-bottom: 5px;">
                Periode: ${period.charAt(0).toUpperCase() + period.slice(1)} - ${startDate} s/d ${endDate}
            </div>
            <div style="font-size: 14px; margin-bottom: 10px;">
                Gudang: ${selectedGudang}
            </div>
            ${jenisPerubahan ? `<div style="font-size: 14px; margin-bottom: 10px;">Jenis Perubahan: ${jenisPerubahan}</div>` : ''}
        </div>
    `;
    
    // Clone table with its container
    const tableContainer = document.querySelector('.table-responsive').parentNode.cloneNode(true);
    
    // Create PDF container
    const pdfContainer = document.createElement('div');
    pdfContainer.style.width = '100%';
    pdfContainer.style.padding = '10px';
    pdfContainer.innerHTML = header;
    pdfContainer.appendChild(tableContainer);
    document.body.appendChild(pdfContainer);
    
    // Generate PDF with auto-pagination
    html2canvas(pdfContainer, {
        scale: 0.8,
        logging: false,
        useCORS: true,
        windowWidth: pdfContainer.scrollWidth,
        windowHeight: pdfContainer.scrollHeight
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 190; // A4 width - margins
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        
        let heightLeft = imgHeight;
        let position = 10; // Top margin
        const pageHeight = 277; // A4 height - margins
        
        // First page
        doc.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        // Add new pages if needed
        while (heightLeft >= 0) {
            position = heightLeft - imgHeight + 10;
            doc.addPage();
            doc.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        doc.save('Laporan_Stok_Gudang.pdf');
        document.body.removeChild(pdfContainer);
    });
}
</script>
</body>
</html>
