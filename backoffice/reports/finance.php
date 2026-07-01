<?php
require_once __DIR__ . '/../_init.php';

$wantsJson = ((string)($_GET['format'] ?? '') === 'json')
    || ((string)($_GET['api'] ?? '') === '1')
    || (!empty($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

$jsonOut = function (array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
};

if ($wantsJson) {
    if (!bo_is_logged_in()) {
        $jsonOut(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    if (!checkAccess('backoffice', 'view') || !checkAccess('backoffice_reports_finance', 'view')) {
        $jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
    }
} else {
    bo_require_login();
    if (!checkAccess('backoffice_reports_finance', 'view')) {
        header('Location: ' . url_for('unauthorized.php'));
        exit();
    }
}

$start = (string)($_GET['start'] ?? '');
$end = (string)($_GET['end'] ?? '');
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$quarter = strtolower(trim((string)($_GET['quarter'] ?? '')));
$quarterYear = (int)($_GET['quarter_year'] ?? date('Y'));

if (!in_array($quarter, ['', 'q1', 'q2', 'q3', 'q4'], true)) {
    $quarter = '';
}
if ($quarterYear < 2000 || $quarterYear > 2100) {
    $quarterYear = (int)date('Y');
}

if ($start === '' || $end === '') {
    $start = date('Y-m-01');
    $end = date('Y-m-t');
}

if ($quarter !== '') {
    $quarterMap = [
        'q1' => ['start' => $quarterYear . '-01-01', 'end' => $quarterYear . '-03-31'],
        'q2' => ['start' => $quarterYear . '-04-01', 'end' => $quarterYear . '-06-30'],
        'q3' => ['start' => $quarterYear . '-07-01', 'end' => $quarterYear . '-09-30'],
        'q4' => ['start' => $quarterYear . '-10-01', 'end' => $quarterYear . '-12-31'],
    ];
    $start = $quarterMap[$quarter]['start'];
    $end = $quarterMap[$quarter]['end'];
}

$quarterOptions = [
    '' => 'Custom Tanggal',
    'q1' => 'Q1 (Jan - Mar)',
    'q2' => 'Q2 (Apr - Jun)',
    'q3' => 'Q3 (Jul - Sep)',
    'q4' => 'Q4 (Okt - Des)',
];
$quarterYearOptions = [];
for ($year = (int)date('Y') + 1; $year >= (int)date('Y') - 5; $year--) {
    $quarterYearOptions[] = $year;
}

$suppliers = [];
if ($boMainConn) {
    $res = $boMainConn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");
    if ($res) {
        while ($r = $res->fetch_assoc()) $suppliers[] = $r;
    }
}

$sum = [
    'pengeluaran' => 0.0,
    'po' => 0.0,
    'direct_purchase' => 0.0,
    'pemasukan_refund' => 0.0,
];

$pengeluaranRows = [];
$pemasukanRows = [];
$topSuppliers = [];
$quarterFinanceChart = [
    'quarter_label' => '',
    'range_start' => '',
    'range_end' => '',
    'months' => [],
    'max_value' => 1,
];

if ($boMainConn) {
    $types = 'ss';
    $params = [$start, $end];
    $supplierWhere = '';
    if ($supplierId > 0) {
        $supplierWhere = " AND supplier_id = ?";
        $types .= 'i';
        $params[] = $supplierId;
    }

    $stmt = $boMainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM pengeluaran WHERE tanggal BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $sum['pengeluaran'] = (float)($r['t'] ?? 0);
        $stmt->close();
    }

    $stmt = $boMainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM purchase_order WHERE tanggal BETWEEN ? AND ?" . ($supplierId > 0 ? " AND supplier_id = ?" : ""));
    if ($stmt) {
        if ($supplierId > 0) {
            $stmt->bind_param('ssi', $start, $end, $supplierId);
        } else {
            $stmt->bind_param('ss', $start, $end);
        }
        $stmt->execute(); 
        $r = $stmt->get_result()->fetch_assoc();
        $sum['po'] = (float)($r['t'] ?? 0);
        $stmt->close();
    }

    $stmt = $boMainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM direct_purchase WHERE tanggal BETWEEN ? AND ?" . ($supplierId > 0 ? " AND supplier_id = ?" : ""));
    if ($stmt) {
        if ($supplierId > 0) {
            $stmt->bind_param('ssi', $start, $end, $supplierId);
        } else {
            $stmt->bind_param('ss', $start, $end);
        }
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $sum['direct_purchase'] = (float)($r['t'] ?? 0);
        $stmt->close();
    }

    $res = $boMainConn->query("SHOW TABLES LIKE 'vendor_refund'");
    $hasRefund = $res && $res->num_rows > 0;
    if ($hasRefund) {
        $sqlRefundSum = "
            SELECT COALESCE(SUM(vrd.qty * COALESCE(pod.harga_satuan, b.harga_beli, 0)), 0) AS t
            FROM vendor_refund vr
            JOIN vendor_refund_detail vrd ON vrd.vendor_refund_id = vr.id
            LEFT JOIN (
                SELECT purchase_order_id, barang_id, AVG(harga_satuan) AS harga_satuan
                FROM detail_purchase_order
                GROUP BY purchase_order_id, barang_id
            ) pod ON pod.purchase_order_id = vr.purchase_order_id AND pod.barang_id = vrd.barang_id
            LEFT JOIN barang b ON b.id = vrd.barang_id
            WHERE vr.tanggal BETWEEN ? AND ?
        ";
        if ($supplierId > 0) {
            $sqlRefundSum .= " AND vr.supplier_id = ?";
        }
        $stmt = $boMainConn->prepare($sqlRefundSum);
        if ($stmt) {
            if ($supplierId > 0) {
                $stmt->bind_param('ssi', $start, $end, $supplierId);
            } else {
                $stmt->bind_param('ss', $start, $end);
            }
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $sum['pemasukan_refund'] = (float)($r['t'] ?? 0);
            $stmt->close();
        }
    }

    $sqlPengeluaran = "
        SELECT tanggal, tipe, nomor, pihak, total_harga
        FROM (
            SELECT p.tanggal AS tanggal, 'Pengeluaran' AS tipe, p.no_pengeluaran AS nomor, '' AS pihak, p.total_harga AS total_harga
            FROM pengeluaran p
            WHERE p.tanggal BETWEEN ? AND ?
            UNION ALL
            SELECT po.tanggal AS tanggal, 'PO' AS tipe, po.no_po AS nomor, s.nama_supplier AS pihak, po.total_harga AS total_harga
            FROM purchase_order po
            LEFT JOIN supplier s ON s.id = po.supplier_id
            WHERE po.tanggal BETWEEN ? AND ?
            " . ($supplierId > 0 ? " AND po.supplier_id = ?" : "") . "
            UNION ALL
            SELECT dp.tanggal AS tanggal, 'Pembelian Direct' AS tipe, dp.no_transaksi AS nomor, dp.nama_toko AS pihak, dp.total_harga AS total_harga
            FROM direct_purchase dp
            WHERE dp.tanggal BETWEEN ? AND ?
            " . ($supplierId > 0 ? " AND dp.supplier_id = ?" : "") . "
        ) x
        ORDER BY tanggal DESC
    ";

    $stmt = $boMainConn->prepare($sqlPengeluaran);
    if ($stmt) {
        if ($supplierId > 0) {
            $stmt->bind_param('ssssissi', $start, $end, $start, $end, $supplierId, $start, $end, $supplierId);
        } else {
            $stmt->bind_param('ssssss', $start, $end, $start, $end, $start, $end);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) $pengeluaranRows[] = $r;
        $stmt->close();
    }

    if ($hasRefund) {
        $sqlRefundList = "
            SELECT vr.id, vr.no_refund, vr.tanggal, COALESCE(s.nama_supplier,'') AS nama_supplier,
                   COALESCE(SUM(vrd.qty),0) AS total_qty,
                   COALESCE(SUM(vrd.qty * COALESCE(pod.harga_satuan, b.harga_beli, 0)), 0) AS total_nilai
            FROM vendor_refund vr
            LEFT JOIN supplier s ON s.id = vr.supplier_id
            LEFT JOIN vendor_refund_detail vrd ON vrd.vendor_refund_id = vr.id
            LEFT JOIN (
                SELECT purchase_order_id, barang_id, AVG(harga_satuan) AS harga_satuan
                FROM detail_purchase_order
                GROUP BY purchase_order_id, barang_id
            ) pod ON pod.purchase_order_id = vr.purchase_order_id AND pod.barang_id = vrd.barang_id
            LEFT JOIN barang b ON b.id = vrd.barang_id
            WHERE vr.tanggal BETWEEN ? AND ?
            " . ($supplierId > 0 ? " AND vr.supplier_id = ?" : "") . "
            GROUP BY vr.id
            ORDER BY vr.tanggal DESC, vr.id DESC
        ";
        $stmt = $boMainConn->prepare($sqlRefundList);
        if ($stmt) {
            if ($supplierId > 0) {
                $stmt->bind_param('ssi', $start, $end, $supplierId);
            } else {
                $stmt->bind_param('ss', $start, $end);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($r = $res->fetch_assoc())) $pemasukanRows[] = $r;
            $stmt->close();
        }
    }
}
$sqlTopSupplier = "
SELECT
    supplier_name,
    SUM(total_transaksi) AS total_transaksi,
    SUM(total_belanja) AS total_belanja
FROM (

    SELECT
        s.nama_supplier AS supplier_name,
        COUNT(*) AS total_transaksi,
        SUM(po.total_harga) AS total_belanja
    FROM purchase_order po
    LEFT JOIN supplier s ON s.id = po.supplier_id
    WHERE po.tanggal BETWEEN ? AND ?
    GROUP BY s.nama_supplier

    UNION ALL

    SELECT
        COALESCE(s.nama_supplier, dp.nama_toko) AS supplier_name,
        COUNT(*) AS total_transaksi,
        SUM(dp.total_harga) AS total_belanja
    FROM direct_purchase dp
    LEFT JOIN supplier s ON s.id = dp.supplier_id
    WHERE dp.tanggal BETWEEN ? AND ?
    GROUP BY COALESCE(s.nama_supplier, dp.nama_toko)

) x
GROUP BY supplier_name
ORDER BY total_belanja DESC
LIMIT 5
";

$stmtTop = $boMainConn->prepare($sqlTopSupplier);

if ($stmtTop) {

    $stmtTop->bind_param(
        'ssss',
        $start,
        $end,
        $start,
        $end
    );

    $stmtTop->execute();

    $resTop = $stmtTop->get_result();

    while ($row = $resTop->fetch_assoc()) {
        $topSuppliers[] = $row;
    }

    $stmtTop->close();
}

$monthShortNames = [
    1 => 'Jan',
    2 => 'Feb',
    3 => 'Mar',
    4 => 'Apr',
    5 => 'Mei',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Agu',
    9 => 'Sep',
    10 => 'Okt',
    11 => 'Nov',
    12 => 'Des',
];

try {
    $chartReferenceDate = new DateTimeImmutable($end !== '' ? $end : date('Y-m-d'));
} catch (Throwable $e) {
    $chartReferenceDate = new DateTimeImmutable(date('Y-m-d'));
}

$chartQuarterNumber = $quarter !== '' ? (int)substr($quarter, 1) : (int)ceil(((int)$chartReferenceDate->format('n')) / 3);
$chartQuarterYear = $quarter !== '' ? $quarterYear : (int)$chartReferenceDate->format('Y');
$chartQuarterStartMonth = (($chartQuarterNumber - 1) * 3) + 1;
$chartQuarterStartDate = new DateTimeImmutable(sprintf('%04d-%02d-01', $chartQuarterYear, $chartQuarterStartMonth));
$chartQuarterEndDate = $chartQuarterStartDate->modify('+2 months')->modify('last day of this month');
$quarterFinanceChart['quarter_label'] = 'Q' . $chartQuarterNumber . ' ' . $chartQuarterYear;
$quarterFinanceChart['range_start'] = $chartQuarterStartDate->format('Y-m-d');
$quarterFinanceChart['range_end'] = $chartQuarterEndDate->format('Y-m-d');

$quarterBuckets = [];
for ($i = 0; $i < 3; $i++) {
    $bucketDate = $chartQuarterStartDate->modify('+' . $i . ' months');
    $bucketKey = $bucketDate->format('Y-m');
    $quarterBuckets[$bucketKey] = [
        'key' => $bucketKey,
        'label' => ($monthShortNames[(int)$bucketDate->format('n')] ?? $bucketDate->format('M')) . ' ' . $bucketDate->format('Y'),
        'pengeluaran' => 0.0,
        'pemasukan' => 0.0,
        'saldo' => 0.0,
    ];
}

if ($boMainConn) {
    $chartStart = $quarterFinanceChart['range_start'];
    $chartEnd = $quarterFinanceChart['range_end'];

    $applyChartRows = function (string $sql, string $bindTypes, array $bindParams, string $targetKey) use ($boMainConn, &$quarterBuckets): void {
        $stmt = $boMainConn->prepare($sql);
        if (!$stmt) {
            return;
        }
        $bindArgs = [];
        $bindArgs[] = $bindTypes;
        foreach ($bindParams as $k => $v) {
            $bindArgs[] = $bindParams[$k];
        }
        $refs = [];
        foreach ($bindArgs as $k => $v) {
            $refs[$k] = &$bindArgs[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $bucketKey = (string)($row['periode_bulan'] ?? '');
            if ($bucketKey === '' || !isset($quarterBuckets[$bucketKey])) {
                continue;
            }
            $quarterBuckets[$bucketKey][$targetKey] += (float)($row['total_nilai'] ?? 0);
        }
        $stmt->close();
    };

    $applyChartRows(
        "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode_bulan, COALESCE(SUM(total_harga), 0) AS total_nilai
         FROM pengeluaran
         WHERE tanggal BETWEEN ? AND ?
         GROUP BY DATE_FORMAT(tanggal, '%Y-%m')",
        'ss',
        [$chartStart, $chartEnd],
        'pengeluaran'
    );

    $applyChartRows(
        "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode_bulan, COALESCE(SUM(total_harga), 0) AS total_nilai
         FROM purchase_order
         WHERE tanggal BETWEEN ? AND ?" . ($supplierId > 0 ? " AND supplier_id = ?" : "") . "
         GROUP BY DATE_FORMAT(tanggal, '%Y-%m')",
        $supplierId > 0 ? 'ssi' : 'ss',
        $supplierId > 0 ? [$chartStart, $chartEnd, $supplierId] : [$chartStart, $chartEnd],
        'pengeluaran'
    );

    $applyChartRows(
        "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode_bulan, COALESCE(SUM(total_harga), 0) AS total_nilai
         FROM direct_purchase
         WHERE tanggal BETWEEN ? AND ?" . ($supplierId > 0 ? " AND supplier_id = ?" : "") . "
         GROUP BY DATE_FORMAT(tanggal, '%Y-%m')",
        $supplierId > 0 ? 'ssi' : 'ss',
        $supplierId > 0 ? [$chartStart, $chartEnd, $supplierId] : [$chartStart, $chartEnd],
        'pengeluaran'
    );

    if (!empty($hasRefund)) {
        $applyChartRows(
            "SELECT DATE_FORMAT(vr.tanggal, '%Y-%m') AS periode_bulan,
                    COALESCE(SUM(vrd.qty * COALESCE(pod.harga_satuan, b.harga_beli, 0)), 0) AS total_nilai
             FROM vendor_refund vr
             JOIN vendor_refund_detail vrd ON vrd.vendor_refund_id = vr.id
             LEFT JOIN (
                 SELECT purchase_order_id, barang_id, AVG(harga_satuan) AS harga_satuan
                 FROM detail_purchase_order
                 GROUP BY purchase_order_id, barang_id
             ) pod ON pod.purchase_order_id = vr.purchase_order_id AND pod.barang_id = vrd.barang_id
             LEFT JOIN barang b ON b.id = vrd.barang_id
             WHERE vr.tanggal BETWEEN ? AND ?" . ($supplierId > 0 ? " AND vr.supplier_id = ?" : "") . "
             GROUP BY DATE_FORMAT(vr.tanggal, '%Y-%m')",
            $supplierId > 0 ? 'ssi' : 'ss',
            $supplierId > 0 ? [$chartStart, $chartEnd, $supplierId] : [$chartStart, $chartEnd],
            'pemasukan'
        );
    }
}

$quarterChartMax = 1.0;
foreach ($quarterBuckets as $bucketKey => $bucket) {
    $quarterBuckets[$bucketKey]['saldo'] = (float)$bucket['pemasukan'] - (float)$bucket['pengeluaran'];
    $quarterChartMax = max($quarterChartMax, (float)$quarterBuckets[$bucketKey]['pengeluaran'], (float)$quarterBuckets[$bucketKey]['pemasukan']);
}
$quarterFinanceChart['months'] = array_values($quarterBuckets);
$quarterFinanceChart['max_value'] = $quarterChartMax;
$totalPengeluaran = $sum['pengeluaran'] + $sum['po'] + $sum['direct_purchase'];
$totalPemasukan = $sum['pemasukan_refund'];
$saldo = $totalPemasukan - $totalPengeluaran;

if ($wantsJson) {
    $jsonOut([
        'success' => true,
        'data' => [
            'filters' => [
                'start' => $start,
                'end' => $end,
                'supplier_id' => $supplierId,
                'quarter' => $quarter,
                'quarter_year' => $quarterYear,
            ],
            'suppliers' => $suppliers,
            'summary' => [
                'pengeluaran' => $totalPengeluaran,
                'pemasukan_refund' => $totalPemasukan,
                'saldo' => $saldo,
                'breakdown' => $sum,
            ],
            'quarter_finance_chart' => $quarterFinanceChart,
            'pengeluaran_rows' => $pengeluaranRows,
            'pemasukan_rows' => $pemasukanRows,
        ],
    ]);
}

$export = (string)($_GET['export'] ?? '');
if ($export === 'xlsx') {
    $sheet = [];
    $sheet[] = ['Laporan Keuangan'];
    $sheet[] = ['Periode', $start, $end];
    $sheet[] = ['Triwulan', $quarter !== '' ? strtoupper($quarter) : 'Custom'];
    $sheet[] = ['Tahun Triwulan', (int)$quarterYear];
    $sheet[] = ['Supplier ID', $supplierId > 0 ? (int)$supplierId : 'Semua'];
    $sheet[] = [];
    $sheet[] = ['Ringkasan'];
    $sheet[] = ['Pengeluaran', (float)$totalPengeluaran];
    $sheet[] = ['Pemasukan (Refund Vendor)', (float)$totalPemasukan];
    $sheet[] = ['Saldo', (float)$saldo];
    $sheet[] = [];
    $sheet[] = ['Pengeluaran (Detail)'];
    $sheet[] = ['Tanggal', 'Tipe', 'No', 'Pihak', 'Total'];
    foreach ($pengeluaranRows as $r) {
        $sheet[] = [
            (string)($r['tanggal'] ?? ''),
            (string)($r['tipe'] ?? ''),
            (string)($r['nomor'] ?? ''),
            (string)($r['pihak'] ?? ''),
            (float)($r['total_harga'] ?? 0),
        ];
    }
    $sheet[] = [];
    $sheet[] = ['Pemasukan (Refund Vendor)'];
    $sheet[] = ['Tanggal', 'No Refund', 'Supplier', 'Total Qty', 'Total Nilai'];
    foreach ($pemasukanRows as $r) {
        $sheet[] = [
            (string)($r['tanggal'] ?? ''),
            (string)($r['no_refund'] ?? ''),
            (string)($r['nama_supplier'] ?? ''),
            (int)($r['total_qty'] ?? 0),
            (float)($r['total_nilai'] ?? 0),
        ];
    }
    bo_export_xlsx_download(bo_export_filename('Laporan_Keuangan', 'xlsx'), 'Laporan Keuangan', $sheet);
}
if ($export === 'pdf') {
    $sub = [
        'Periode: ' . $start . ' s/d ' . $end,
        'Triwulan: ' . ($quarter !== '' ? strtoupper($quarter) . ' ' . $quarterYear : 'Custom Tanggal'),
        'Supplier: ' . ($supplierId > 0 ? (string)$supplierId : 'Semua'),
        'Pengeluaran: Rp ' . number_format($totalPengeluaran, 0, ',', '.') . ' • Pemasukan: Rp ' . number_format($totalPemasukan, 0, ',', '.') . ' • Saldo: Rp ' . number_format($saldo, 0, ',', '.'),
    ];

    $colsOut = [
        ['key' => 'tanggal', 'label' => 'Tanggal', 'w' => 25, 'align' => 'L'],
        ['key' => 'tipe', 'label' => 'Tipe', 'w' => 35, 'align' => 'L'],
        ['key' => 'nomor', 'label' => 'No', 'w' => 45, 'align' => 'L'],
        ['key' => 'pihak', 'label' => 'Pihak', 'w' => 60, 'align' => 'L'],
        ['key' => 'total_harga', 'label' => 'Total', 'w' => 30, 'align' => 'R'],
    ];

    $pdf = bo_export_pdf_begin('P', 'Laporan Keuangan - Pengeluaran', $sub, $colsOut);
    $rowsOut = [];
    foreach ($pengeluaranRows as $r) {
        $rowsOut[] = [
            'tanggal' => (string)($r['tanggal'] ?? ''),
            'tipe' => (string)($r['tipe'] ?? ''),
            'nomor' => (string)($r['nomor'] ?? ''),
            'pihak' => (string)($r['pihak'] ?? '-'),
            'total_harga' => 'Rp ' . number_format((float)($r['total_harga'] ?? 0), 0, ',', '.'),
        ];
    }
    bo_pdf_draw_rows($pdf, $colsOut, $rowsOut);

    $colsIn = [
        ['key' => 'tanggal', 'label' => 'Tanggal', 'w' => 25, 'align' => 'L'],
        ['key' => 'no_refund', 'label' => 'No Refund', 'w' => 40, 'align' => 'L'],
        ['key' => 'nama_supplier', 'label' => 'Supplier', 'w' => 60, 'align' => 'L'],
        ['key' => 'total_qty', 'label' => 'Qty', 'w' => 20, 'align' => 'R'],
        ['key' => 'total_nilai', 'label' => 'Nilai', 'w' => 40, 'align' => 'R'],
    ];
    $pdf->boTitle = 'Laporan Keuangan - Pemasukan (Refund Vendor)';
    bo_export_pdf_set_table($pdf, $colsIn, true);
    $rowsIn = [];
    foreach ($pemasukanRows as $r) {
        $rowsIn[] = [
            'tanggal' => (string)($r['tanggal'] ?? ''),
            'no_refund' => (string)($r['no_refund'] ?? ''),
            'nama_supplier' => (string)($r['nama_supplier'] ?? '-'),
            'total_qty' => number_format((int)($r['total_qty'] ?? 0)),
            'total_nilai' => 'Rp ' . number_format((float)($r['total_nilai'] ?? 0), 0, ',', '.'),
        ];
    }
    bo_pdf_draw_rows($pdf, $colsIn, $rowsIn);
    bo_export_pdf_download($pdf, bo_export_filename('Laporan_Keuangan', 'pdf'));


}
?>
<?php
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Laporan Keuangan - Backoffice',
    'page_title' => 'Laporan Keuangan',
    'page_subtitle' => 'Pantau pengeluaran, refund vendor, dan saldo dengan layout yang seragam.',
    'active' => 'reports-finance',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter Keuangan</h3>
            <div class="bo-card-subtitle">Atur periode, triwulan, dan supplier untuk melihat ringkasan keuangan backoffice.</div>
        </div>
    </div>
    <form class="row g-3 align-items-end" method="get">
                <div class="col-md-3">
                    <label class="form-label">Filter Triwulan</label>
                    <select name="quarter" class="form-select">
                        <?php foreach ($quarterOptions as $quarterValue => $quarterLabel): ?>
                            <option value="<?= htmlspecialchars($quarterValue) ?>" <?= $quarter === $quarterValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($quarterLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tahun</label>
                    <select name="quarter_year" class="form-select">
                        <?php foreach ($quarterYearOptions as $year): ?>
                            <option value="<?= (int)$year ?>" <?= ((int)$year === $quarterYear) ? 'selected' : '' ?>>
                                <?= (int)$year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Supplier (opsional)</label>
                    <select name="supplier_id" class="form-select">
                        <option value="0">Semua Supplier</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === $supplierId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nama_supplier']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <div class="text-muted small">Jika triwulan dipilih, tanggal mulai dan akhir akan mengikuti quarter yang dipilih saat filter diterapkan.</div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                    <a class="btn btn-success" href="<?= htmlspecialchars(bo_url_for('reports/finance.php?' . http_build_query(array_merge($_GET, ['export' => 'xlsx'])))) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Export XLSX</a>
                    <a class="btn btn-danger" href="<?= htmlspecialchars(bo_url_for('reports/finance.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf'])))) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
                </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="bo-card p-4">
                    <div class="text-muted small">Pengeluaran (Pengeluaran + PO + Direct)</div>
                    <div class="fw-bold">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
                    <div class="text-muted small mt-1">
                        <!--Pengeluaran: Rp <?= number_format($sum['pengeluaran'], 0, ',', '.') ?> •-->
                        PO: Rp <?= number_format($sum['po'], 0, ',', '.') ?> •
                        Direct: Rp <?= number_format($sum['direct_purchase'], 0, ',', '.') ?>
                    </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bo-card p-4">
                    <div class="text-muted small">Pemasukan (Estimasi Refund Vendor)</div>
                    <div class="fw-bold">Rp <?= number_format($totalPemasukan, 0, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bo-card p-4">
                    <div class="text-muted small">Saldo (Pemasukan - Pengeluaran)</div>
                    <div class="fw-bold">Rp <?= number_format($saldo, 0, ',', '.') ?></div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mt-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active"
                data-bs-toggle="tab"
                data-bs-target="#tab-out"
                type="button"
                role="tab">
            Pengeluaran
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link"
                data-bs-toggle="tab"
                data-bs-target="#tab-in"
                type="button"
                role="tab">
            Pemasukan (Refund Vendor)
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link"
                data-bs-toggle="tab"
                data-bs-target="#tab-top-supplier"
                type="button"
                role="tab">
            Top 5 Supplier
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link"
                data-bs-toggle="tab"
                data-bs-target="#tab-finance-chart"
                type="button"
                role="tab">
            Diagram Laporan Keuangan
        </button>
    </li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-out" role="tabpanel">
        <div class="bo-card p-4 mt-3">
            <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Tipe</th>
                                    <th>No</th>
                                    <th>Pihak</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pengeluaranRows)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pengeluaranRows as $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)$r['tanggal']) ?></td>
                                            <td><?= htmlspecialchars((string)$r['tipe']) ?></td>
                                            <td><?= htmlspecialchars((string)$r['nomor']) ?></td>
                                            <td><?= htmlspecialchars((string)($r['pihak'] ?? '-')) ?></td>
                                            <td class="text-end">Rp <?= number_format((float)($r['total_harga'] ?? 0), 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-in" role="tabpanel">
        <div class="bo-card p-4 mt-3">
            <div class="text-muted small mb-2">Nilai pemasukan dihitung estimasi dari qty refund x harga beli atau harga PO bila tersedia.</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No Refund</th>
                            <th>Supplier</th>
                            <th class="text-end">Total Qty</th>
                            <th class="text-end">Total Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pemasukanRows)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pemasukanRows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$r['tanggal']) ?></td>
                                    <td><?= htmlspecialchars((string)$r['no_refund']) ?></td>
                                    <td><?= htmlspecialchars((string)($r['nama_supplier'] ?? '-')) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['total_qty'] ?? 0)) ?></td>
                                    <td class="text-end">Rp <?= number_format((float)($r['total_nilai'] ?? 0), 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-top-supplier" role="tabpanel">
        <div class="bo-card p-4 mt-3">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Top 5 Supplier</h3>
                    <div class="bo-card-subtitle">Supplier dengan total belanja tertinggi pada periode aktif.</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="80">Rank</th>
                            <th>Supplier</th>
                            <th class="text-end">Transaksi</th>
                            <th class="text-end">Total Belanja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topSuppliers)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada data supplier.</td></tr>
                        <?php else: ?>
                            <?php foreach ($topSuppliers as $i => $supplier): ?>
                                <tr>
                                    <td><?= (int)($i + 1) ?></td>
                                    <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                                    <td class="text-end"><?= number_format($supplier['total_transaksi']) ?></td>
                                    <td class="text-end fw-bold">Rp <?= number_format($supplier['total_belanja'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-finance-chart" role="tabpanel">
        <div class="bo-card p-4 mt-3">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Diagram Keuangan Triwulan</h3>
                    <div class="bo-card-subtitle">Perbandingan pengeluaran dan pemasukan per bulan pada <?= htmlspecialchars($quarterFinanceChart['quarter_label']) ?>.</div>
                </div>
                <div class="text-muted small"><?= htmlspecialchars($quarterFinanceChart['range_start']) ?> s/d <?= htmlspecialchars($quarterFinanceChart['range_end']) ?></div>
            </div>
            <div class="d-flex align-items-end gap-3" style="height: 240px;">
                <?php foreach ($quarterFinanceChart['months'] as $bucket): ?>
                    <?php
                    $expenseHeight = $quarterFinanceChart['max_value'] > 0 ? max(6, (($bucket['pengeluaran'] / $quarterFinanceChart['max_value']) * 100)) : 6;
                    $incomeHeight = $quarterFinanceChart['max_value'] > 0 ? max(6, (($bucket['pemasukan'] / $quarterFinanceChart['max_value']) * 100)) : 6;
                    ?>
                    <div class="flex-fill d-flex flex-column justify-content-end h-100">
                        <div class="d-flex align-items-end justify-content-center gap-2 flex-grow-1">
                            <div class="rounded-top" style="width: 26px; height: <?= number_format((float)$expenseHeight, 2, '.', '') ?>%; background: #ef4444;" title="Pengeluaran Rp <?= number_format((float)$bucket['pengeluaran'], 0, ',', '.') ?>"></div>
                            <div class="rounded-top" style="width: 26px; height: <?= number_format((float)$incomeHeight, 2, '.', '') ?>%; background: #22c55e;" title="Pemasukan Rp <?= number_format((float)$bucket['pemasukan'], 0, ',', '.') ?>"></div>
                        </div>
                        <div class="pt-3 text-center">
                            <div class="fw-semibold small"><?= htmlspecialchars((string)$bucket['label']) ?></div>
                            <div class="text-muted small">Saldo: Rp <?= number_format((float)$bucket['saldo'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex gap-3 flex-wrap mt-3 small">
                <span class="d-inline-flex align-items-center gap-2"><span class="rounded" style="width: 12px; height: 12px; background: #ef4444;"></span>Pengeluaran</span>
                <span class="d-inline-flex align-items-center gap-2"><span class="rounded" style="width: 12px; height: 12px; background: #22c55e;"></span>Pemasukan</span>
            </div>
            <div class="row g-2 mt-2">
                <?php foreach ($quarterFinanceChart['months'] as $bucket): ?>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-semibold small"><?= htmlspecialchars((string)$bucket['label']) ?></div>
                            <div class="text-muted small">Keluar: Rp <?= number_format((float)$bucket['pengeluaran'], 0, ',', '.') ?></div>
                            <div class="text-muted small">Masuk: Rp <?= number_format((float)$bucket['pemasukan'], 0, ',', '.') ?></div>
                            <div class="small fw-bold <?= ((float)$bucket['saldo'] < 0) ? 'text-danger' : 'text-success' ?>">Saldo: Rp <?= number_format((float)$bucket['saldo'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php bo_render_shell_end(); ?>
