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
    'pendapatan_omset' => 0.0,
];

$pengeluaranRows = [];
$pendapatanRows = [];
$refundRows = [];
$topSuppliers = [];
$quarterFinanceChart = [
    'quarter_label' => '',
    'range_start' => '',
    'range_end' => '',
    'months' => [],
    'max_value' => 1,
];

$detailStatusExists = false;
$poLineTotalExpr = "COALESCE(NULLIF(pod.total_harga, 0), (pod.jumlah * pod.harga_satuan))";
$poDetailNotRejected = "1=1";

if ($boMainConn) {
    // Samakan rumus total PO dengan po.php / po_detail.php:
    // COALESCE(NULLIF(total_harga, 0), (jumlah * harga_satuan)), skip baris rejected.
    $detailStatusExists = function_exists('db_has_column')
        ? db_has_column($boMainConn, 'detail_purchase_order', 'status')
        : true;
    $poDetailNotRejected = $detailStatusExists
        ? "(pod.status IS NULL OR pod.status != 'rejected')"
        : "1=1";

    // 1. Nominal Pengeluaran Kas
    $stmt = $boMainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM pengeluaran WHERE tanggal BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $sum['pengeluaran'] = (float)($r['t'] ?? 0);
        $stmt->close();
    }

    // 2. Nominal PO non-draft (Menyingkirkan status 'rejected' dari total pengeluaran atas)
    $sqlPoSum = "
        SELECT COALESCE(SUM($poLineTotalExpr), 0) AS t
        FROM purchase_order po
        JOIN detail_purchase_order pod ON pod.purchase_order_id = po.id
        WHERE po.tanggal BETWEEN ? AND ?
          AND po.status NOT IN ('menunggu', 'draft', 'rejected')
          AND $poDetailNotRejected
    ";
    if ($supplierId > 0) {
        $sqlPoSum .= " AND po.supplier_id = ?";
    }
    $stmt = $boMainConn->prepare($sqlPoSum);
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

    // 3. Nominal Direct Purchase
    $stmt = $boMainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM direct_purchase WHERE tanggal BETWEEN ? AND ? AND status != 'menunggu'" . ($supplierId > 0 ? " AND supplier_id = ?" : ""));
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

    // 4. Nominal Pendapatan / Omset
    $resPendapatan = $boMainConn->query("SHOW TABLES LIKE 'pendapatan'");
    $hasPendapatan = $resPendapatan && $resPendapatan->num_rows > 0;
    $resPendapatanManual = $boMainConn->query("SHOW TABLES LIKE 'pendapatan_manual'");
    $hasPendapatanManual = $resPendapatanManual && $resPendapatanManual->num_rows > 0;
    if ($hasPendapatan || $hasPendapatanManual) {
        $sum['pendapatan_omset'] = 0.0;
        if ($hasPendapatan) {
            $stmt = $boMainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM pendapatan WHERE tanggal BETWEEN ? AND ?");
            if ($stmt) {
                $stmt->bind_param('ss', $start, $end);
                $stmt->execute();
                $r = $stmt->get_result()->fetch_assoc();
                $sum['pendapatan_omset'] += (float)($r['t'] ?? 0);
                $stmt->close();
            }
        }
        if ($hasPendapatanManual) {
            $stmt = $boMainConn->prepare("SELECT COALESCE(SUM(total_omset_hari),0) AS t FROM pendapatan_manual WHERE tanggal BETWEEN ? AND ?");
            if ($stmt) {
                $stmt->bind_param('ss', $start, $end);
                $stmt->execute();
                $r = $stmt->get_result()->fetch_assoc();
                $sum['pendapatan_omset'] += (float)($r['t'] ?? 0);
                $stmt->close();
            }
        }
    }

    // 5. Nominal Vendor Refund
    $resRefund = $boMainConn->query("SHOW TABLES LIKE 'vendor_refund'");
    $hasRefund = $resRefund && $resRefund->num_rows > 0;
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

    // QUERY LIST DATA TABEL PENGELUARAN (Menyingkirkan po.status = 'rejected' agar tidak tampil di tabel)
    $sqlPengeluaran = "
        SELECT tanggal, tipe, nomor, pihak, total_harga, keterangan, payment_method
        FROM (
            SELECT p.tanggal AS tanggal, 'Pengeluaran' AS tipe, p.no_pengeluaran AS nomor, '' AS pihak, p.total_harga AS total_harga, COALESCE(p.keterangan, '') AS keterangan, p.payment_method AS payment_method
            FROM pengeluaran p
            WHERE p.tanggal BETWEEN ? AND ?
            UNION ALL
            SELECT 
                po.tanggal AS tanggal, 
                'PO' AS tipe, 
                po.no_po AS nomor, 
                s.nama_supplier AS pihak, 
                COALESCE((
                    SELECT SUM($poLineTotalExpr)
                    FROM detail_purchase_order pod
                    WHERE pod.purchase_order_id = po.id AND $poDetailNotRejected
                ), 0) AS total_harga, 
                COALESCE(po.keterangan, '') AS keterangan, 
                po.payment_method AS payment_method
            FROM purchase_order po
            LEFT JOIN supplier s ON s.id = po.supplier_id
            WHERE po.tanggal BETWEEN ? AND ? AND po.status NOT IN ('menunggu', 'draft', 'rejected')
            " . ($supplierId > 0 ? " AND po.supplier_id = ?" : "") . "
            UNION ALL
            SELECT dp.tanggal AS tanggal, 'Pembelian Direct' AS tipe, dp.no_transaksi AS nomor, dp.nama_toko AS pihak, dp.total_harga AS total_harga, COALESCE(dp.keterangan, '') AS keterangan, dp.payment_method AS payment_method
            FROM direct_purchase dp
            WHERE dp.tanggal BETWEEN ? AND ? AND dp.status != 'menunggu'
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

    // QUERY LIST DATA PENDAPATAN / OMSET
    if ($hasPendapatan) {
        $stmtPen = $boMainConn->prepare("SELECT tanggal, total_harga, COALESCE(keterangan, '') AS keterangan FROM pendapatan WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal DESC");
        if ($stmtPen) {
            $stmtPen->bind_param('ss', $start, $end);
            $stmtPen->execute();
            $resPen = $stmtPen->get_result();
            while ($resPen && ($r = $resPen->fetch_assoc())) {
                $pendapatanRows[] = $r;
            }
            $stmtPen->close();
        }
    }
    if ($hasPendapatanManual) {
        $stmtPenManual = $boMainConn->prepare("SELECT tanggal, total_omset_hari AS total_harga, 'Pendapatan Omset Harian' AS keterangan FROM pendapatan_manual WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal DESC");
        if ($stmtPenManual) {
            $stmtPenManual->bind_param('ss', $start, $end);
            $stmtPenManual->execute();
            $resPenManual = $stmtPenManual->get_result();
            while ($resPenManual && ($r = $resPenManual->fetch_assoc())) {
                $pendapatanRows[] = $r;
            }
            $stmtPenManual->close();
        }
        usort($pendapatanRows, function($a, $b) {
            return strcmp($b['tanggal'], $a['tanggal']);
        });
    }

    // QUERY LIST DATA VENDOR REFUND
    if ($hasRefund) {
        $sqlRefundList = "
            SELECT vr.id, vr.no_refund, vr.tanggal, COALESCE(s.nama_supplier,'') AS nama_supplier,
                   COALESCE(SUM(vrd.qty),0) AS total_qty,
                   COALESCE(SUM(vrd.qty * COALESCE(pod.harga_satuan, b.harga_beli, 0)), 0) AS total_nilai,
                   COALESCE(vr.keterangan, '') AS keterangan
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
        $stmtRef = $boMainConn->prepare($sqlRefundList);
        if ($stmtRef) {
            if ($supplierId > 0) {
                $stmtRef->bind_param('ssi', $start, $end, $supplierId);
            } else {
                $stmtRef->bind_param('ss', $start, $end);
            }
            $stmtRef->execute();
            $resRef = $stmtRef->get_result();
            while ($resRef && ($r = $resRef->fetch_assoc())) {
                $refundRows[] = $r;
            }
            $stmtRef->close();
        }
    }
}

// Data Top 5 Supplier (Menyingkirkan po.status = 'rejected')
$sqlTopSupplier = "
SELECT
    supplier_name,
    SUM(total_transaksi) AS total_transaksi,
    SUM(total_belanja) AS total_belanja
FROM (
    SELECT
        s.nama_supplier AS supplier_name,
        COUNT(DISTINCT po.id) AS total_transaksi,
        SUM($poLineTotalExpr) AS total_belanja
    FROM purchase_order po
    LEFT JOIN supplier s ON s.id = po.supplier_id
    JOIN detail_purchase_order pod ON pod.purchase_order_id = po.id
    WHERE po.tanggal BETWEEN ? AND ? 
      AND po.status NOT IN ('menunggu', 'draft', 'rejected')
      AND $poDetailNotRejected
    GROUP BY s.nama_supplier
    UNION ALL
    SELECT
        COALESCE(s.nama_supplier, dp.nama_toko) AS supplier_name,
        COUNT(*) AS total_transaksi,
        SUM(dp.total_harga) AS total_belanja
    FROM direct_purchase dp
    LEFT JOIN supplier s ON s.id = dp.supplier_id
    WHERE dp.tanggal BETWEEN ? AND ? AND dp.status != 'menunggu'
    GROUP BY COALESCE(s.nama_supplier, dp.nama_toko)
) x
GROUP BY supplier_name
ORDER BY total_belanja DESC
LIMIT 5
";

$stmtTop = $boMainConn->prepare($sqlTopSupplier);
if ($stmtTop) {
    $stmtTop->bind_param('ssss', $start, $end, $start, $end);
    $stmtTop->execute();
    $resTop = $stmtTop->get_result();
    while ($row = $resTop->fetch_assoc()) {
        $topSuppliers[] = $row;
    }
    $stmtTop->close();
}

$monthShortNames = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
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

    // Diagram Bulanan (Menyingkirkan po.status = 'rejected')
    $applyChartRows(
        "SELECT DATE_FORMAT(po.tanggal, '%Y-%m') AS periode_bulan, 
                COALESCE(SUM($poLineTotalExpr), 0) AS total_nilai
         FROM purchase_order po
         JOIN detail_purchase_order pod ON pod.purchase_order_id = po.id
         WHERE po.tanggal BETWEEN ? AND ? 
           AND po.status NOT IN ('menunggu', 'draft', 'rejected')
           AND $poDetailNotRejected" . ($supplierId > 0 ? " AND po.supplier_id = ?" : "") . "
         GROUP BY DATE_FORMAT(po.tanggal, '%Y-%m')",
        $supplierId > 0 ? 'ssi' : 'ss',
        $supplierId > 0 ? [$chartStart, $chartEnd, $supplierId] : [$chartStart, $chartEnd],
        'pengeluaran'
    );

    $applyChartRows(
        "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode_bulan, COALESCE(SUM(total_harga), 0) AS total_nilai
         FROM direct_purchase
         WHERE tanggal BETWEEN ? AND ? AND status != 'menunggu'" . ($supplierId > 0 ? " AND supplier_id = ?" : "") . "
         GROUP BY DATE_FORMAT(tanggal, '%Y-%m')",
        $supplierId > 0 ? 'ssi' : 'ss',
        $supplierId > 0 ? [$chartStart, $chartEnd, $supplierId] : [$chartStart, $chartEnd],
        'pengeluaran'
    );

    if ($hasPendapatan) {
        $applyChartRows(
            "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode_bulan, COALESCE(SUM(total_harga), 0) AS total_nilai
             FROM pendapatan
             WHERE tanggal BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(tanggal, '%Y-%m')",
            'ss',
            [$chartStart, $chartEnd],
            'pemasukan'
        );
    }
    if ($hasPendapatanManual) {
        $applyChartRows(
            "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode_bulan, COALESCE(SUM(total_omset_hari), 0) AS total_nilai
             FROM pendapatan_manual
             WHERE tanggal BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(tanggal, '%Y-%m')",
            'ss',
            [$chartStart, $chartEnd],
            'pemasukan'
        );
    }

    if ($hasRefund) {
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

// Calculate sisa cash and sisa saldo
$cash_pendapatan = 0;
$saldo_pendapatan = 0;
$cash_pengeluaran = 0;
$saldo_pengeluaran = 0;

if ($boMainConn) {
    // Pendapatan manual
    $resPendapatanManualCheck = $boMainConn->query("SHOW TABLES LIKE 'pendapatan_manual'");
    $hasPendapatanManual = $resPendapatanManualCheck && $resPendapatanManualCheck->num_rows > 0;
    if ($hasPendapatanManual) {
        $stmtPendapatanCashSaldo = $boMainConn->prepare("
            SELECT 
                COALESCE(SUM(total_cash_sales), 0) as total_cash,
                COALESCE(SUM(total_qr_gopay + total_edc + total_online_payment + total_transfers + total_shopeefood + total_gofood_gopay + total_ovo), 0) as total_saldo
            FROM pendapatan_manual
            WHERE tanggal BETWEEN ? AND ?
        ");
        if ($stmtPendapatanCashSaldo) {
            $stmtPendapatanCashSaldo->bind_param('ss', $start, $end);
            $stmtPendapatanCashSaldo->execute();
            $r = $stmtPendapatanCashSaldo->get_result()->fetch_assoc();
            $cash_pendapatan = (float)($r['total_cash'] ?? 0);
            $saldo_pendapatan = (float)($r['total_saldo'] ?? 0);
            $stmtPendapatanCashSaldo->close();
        }
    }

    // Pendapatan reguler
    $resPendapatanCheck = $boMainConn->query("SHOW TABLES LIKE 'pendapatan'");
    $hasPendapatan = $resPendapatanCheck && $resPendapatanCheck->num_rows > 0;
    if ($hasPendapatan) {
        $resPendapatanColumns = $boMainConn->query("SHOW COLUMNS FROM pendapatan LIKE 'payment_method'");
        $hasPaymentMethod = $resPendapatanColumns && $resPendapatanColumns->num_rows > 0;
        if ($hasPaymentMethod) {
            $stmtPendapatanCashSaldo2 = $boMainConn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_harga ELSE 0 END), 0) as total_cash,
                    COALESCE(SUM(CASE WHEN payment_method = 'saldo' THEN total_harga ELSE 0 END), 0) as total_saldo
                FROM pendapatan
                WHERE tanggal BETWEEN ? AND ?
            ");
            if ($stmtPendapatanCashSaldo2) {
                $stmtPendapatanCashSaldo2->bind_param('ss', $start, $end);
                $stmtPendapatanCashSaldo2->execute();
                $r = $stmtPendapatanCashSaldo2->get_result()->fetch_assoc();
                $cash_pendapatan += (float)($r['total_cash'] ?? 0);
                $saldo_pendapatan += (float)($r['total_saldo'] ?? 0);
                $stmtPendapatanCashSaldo2->close();
            }
        }
    }

    // Pengeluaran kas
    $stmtPengeluaranCashSaldo = $boMainConn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_harga ELSE 0 END), 0) as total_cash,
            COALESCE(SUM(CASE WHEN payment_method = 'saldo' THEN total_harga ELSE 0 END), 0) as total_saldo
        FROM pengeluaran
        WHERE tanggal BETWEEN ? AND ?
    ");
    if ($stmtPengeluaranCashSaldo) {
        $stmtPengeluaranCashSaldo->bind_param('ss', $start, $end);
        $stmtPengeluaranCashSaldo->execute();
        $r = $stmtPengeluaranCashSaldo->get_result()->fetch_assoc();
        $cash_pengeluaran += (float)($r['total_cash'] ?? 0);
        $saldo_pengeluaran += (float)($r['total_saldo'] ?? 0);
        $stmtPengeluaranCashSaldo->close();
    }
    
    // Perhitungan sisa Cash/Saldo (Menyingkirkan po.status = 'rejected')
    $sqlPOCashSaldo = "
        SELECT 
            COALESCE(SUM(CASE WHEN po.payment_method = 'cash' THEN $poLineTotalExpr ELSE 0 END), 0) as total_cash,
            COALESCE(SUM(CASE WHEN po.payment_method = 'saldo' THEN $poLineTotalExpr ELSE 0 END), 0) as total_saldo
        FROM purchase_order po
        JOIN detail_purchase_order pod ON pod.purchase_order_id = po.id
        WHERE po.tanggal BETWEEN ? AND ? 
          AND po.status NOT IN ('menunggu', 'draft', 'rejected')
          AND $poDetailNotRejected
    ";
    if ($supplierId > 0) {
        $sqlPOCashSaldo .= " AND po.supplier_id = ?";
    }
    $stmtPOCashSaldo = $boMainConn->prepare($sqlPOCashSaldo);
    if ($stmtPOCashSaldo) {
        if ($supplierId > 0) {
            $stmtPOCashSaldo->bind_param('ssi', $start, $end, $supplierId);
        } else {
            $stmtPOCashSaldo->bind_param('ss', $start, $end);
        }
        $stmtPOCashSaldo->execute();
        $r = $stmtPOCashSaldo->get_result()->fetch_assoc();
        $cash_pengeluaran += (float)($r['total_cash'] ?? 0);
        $saldo_pengeluaran += (float)($r['total_saldo'] ?? 0);
        $stmtPOCashSaldo->close();
    }
    
    // Direct purchase
    $stmtDirectCashSaldo = $boMainConn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_harga ELSE 0 END), 0) as total_cash,
            COALESCE(SUM(CASE WHEN payment_method = 'saldo' THEN total_harga ELSE 0 END), 0) as total_saldo
        FROM direct_purchase
        WHERE tanggal BETWEEN ? AND ? AND status != 'menunggu'
        " . ($supplierId > 0 ? " AND supplier_id = ?" : "") . "
    ");
    if ($stmtDirectCashSaldo) {
        if ($supplierId > 0) {
            $stmtDirectCashSaldo->bind_param('ssi', $start, $end, $supplierId);
        } else {
            $stmtDirectCashSaldo->bind_param('ss', $start, $end);
        }
        $stmtDirectCashSaldo->execute();
        $r = $stmtDirectCashSaldo->get_result()->fetch_assoc();
        $cash_pengeluaran += (float)($r['total_cash'] ?? 0);
        $saldo_pengeluaran += (float)($r['total_saldo'] ?? 0);
        $stmtDirectCashSaldo->close();
    }
}

$sisa_cash = $cash_pendapatan - $cash_pengeluaran;
$sisa_saldo = $saldo_pendapatan - $saldo_pengeluaran;

$totalPengeluaran = $sum['pengeluaran'] + $sum['po'] + $sum['direct_purchase'];
$totalPemasukan = $sum['pendapatan_omset'] + $sum['pemasukan_refund'];
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
                'pemasukan' => $totalPemasukan,
                'saldo' => $saldo,
                'breakdown' => $sum,
                'sisa_cash' => $sisa_cash,
                'sisa_saldo' => $sisa_saldo,
            ],
            'quarter_finance_chart' => $quarterFinanceChart,
            'pengeluaran_rows' => $pengeluaranRows,
            'pendapatan_rows' => $pendapatanRows,
            'refund_rows' => $refundRows,
        ],
    ]);
}

$export = (string)($_GET['export'] ?? '');
if ($export === 'xlsx') {
    $sheet = [];
    $sheet[] = ['Laporan Keuangan Backoffice'];
    $sheet[] = ['Periode', $start, $end];
    $sheet[] = [];
    $sheet[] = ['Ringkasan Keuangan'];
    $sheet[] = ['Total Pengeluaran', (float)$totalPengeluaran];
    $sheet[] = ['Total Pendapatan / Omset', (float)$totalPemasukan];
    $sheet[] = ['Saldo Bersih Berjalan', (float)$saldo];
    $sheet[] = ['Sisa Cash', (float)$sisa_cash];
    $sheet[] = ['Sisa Saldo', (float)$sisa_saldo];
    $sheet[] = [];
    $sheet[] = ['Pengeluaran (Detail)'];
    $sheet[] = ['Tanggal', 'Tipe', 'No', 'Pihak', 'Metode Pembayaran', 'Total', 'Keterangan'];
    foreach ($pengeluaranRows as $r) {
        $sheet[] = [$r['tanggal'], $r['tipe'], $r['nomor'], $r['pihak'], $r['payment_method'], (float)$r['total_harga'], $r['keterangan']];
    }
    $sheet[] = [];
    $sheet[] = ['Pendapatan / Omset'];
    $sheet[] = ['Tanggal', 'Total Nilai', 'Keterangan / Deskripsi'];
    foreach ($pendapatanRows as $r) {
        $sheet[] = [$r['tanggal'], (float)$r['total_harga'], $r['keterangan']];
    }
    $sheet[] = [];
    $sheet[] = ['Vendor Refund'];
    $sheet[] = ['Tanggal', 'No Refund', 'Supplier', 'Qty', 'Total Nilai', 'Keterangan'];
    foreach ($refundRows as $r) {
        $sheet[] = [$r['tanggal'], $r['no_refund'], $r['nama_supplier'], (int)$r['total_qty'], (float)$r['total_nilai'], $r['keterangan']];
    }
    bo_export_xlsx_download(bo_export_filename('Laporan_Keuangan', 'xlsx'), 'Laporan Keuangan', $sheet);
}
if ($export === 'pdf') {
    $sub = [
                'Periode: ' . $start . ' s/d ' . $end,
                'Pengeluaran: Rp ' . number_format($totalPengeluaran, 0, ',', '.') . ' • Pendapatan/Omset: Rp ' . number_format($totalPemasukan, 0, ',', '.') . ' • Saldo: Rp ' . number_format($saldo, 0, ',', '.'),
                'Sisa Cash: Rp ' . number_format($sisa_cash, 0, ',', '.') . ' • Sisa Saldo: Rp ' . number_format($sisa_saldo, 0, ',', '.'),
            ];

    $colsOut = [
        ['key' => 'tanggal', 'label' => 'Tanggal', 'w' => 20, 'align' => 'L'],
        ['key' => 'tipe', 'label' => 'Tipe', 'w' => 25, 'align' => 'L'],
        ['key' => 'nomor', 'label' => 'No', 'w' => 35, 'align' => 'L'],
        ['key' => 'pihak', 'label' => 'Pihak', 'w' => 35, 'align' => 'L'],
        ['key' => 'payment_method', 'label' => 'Metode Pembayaran', 'w' => 30, 'align' => 'L'],
        ['key' => 'total_harga', 'label' => 'Total', 'w' => 25, 'align' => 'R'],
        ['key' => 'keterangan', 'label' => 'Keterangan', 'w' => 50, 'align' => 'L'],
    ];
    $pdf = bo_export_pdf_begin('P', 'Laporan Keuangan - Pengeluaran', $sub, $colsOut);
    $rowsOut = [];
    foreach ($pengeluaranRows as $r) {
        $rowsOut[] = [
            'tanggal' => $r['tanggal'], 'tipe' => $r['tipe'], 'nomor' => $r['nomor'], 'pihak' => $r['pihak'] ?: '-',
            'payment_method' => $r['payment_method'] ?: '-',
            'total_harga' => 'Rp ' . number_format((float)$r['total_harga'], 0, ',', '.'), 'keterangan' => $r['keterangan'],
        ];
    }
    bo_pdf_draw_rows($pdf, $colsOut, $rowsOut);

    $colsPen = [
        ['key' => 'tanggal', 'label' => 'Tanggal', 'w' => 30, 'align' => 'L'],
        ['key' => 'total_harga', 'label' => 'Total Omset', 'w' => 40, 'align' => 'R'],
        ['key' => 'keterangan', 'label' => 'Keterangan / Deskripsi', 'w' => 120, 'align' => 'L'],
    ];
    $pdf->boTitle = 'Laporan Keuangan - Pendapatan / Omset';
    bo_export_pdf_set_table($pdf, $colsPen, true);
    $rowsPen = [];
    foreach ($pendapatanRows as $r) {
        $rowsPen[] = [
            'tanggal' => $r['tanggal'],
            'total_harga' => 'Rp ' . number_format((float)$r['total_harga'], 0, ',', '.'),
            'keterangan' => $r['keterangan'],
        ];
    }
    bo_pdf_draw_rows($pdf, $colsPen, $rowsPen);

    $colsRef = [
        ['key' => 'tanggal', 'label' => 'Tanggal', 'w' => 20, 'align' => 'L'],
        ['key' => 'no_refund', 'label' => 'No Refund', 'w' => 30, 'align' => 'L'],
        ['key' => 'nama_supplier', 'label' => 'Supplier', 'w' => 40, 'align' => 'L'],
        ['key' => 'total_qty', 'label' => 'Qty', 'w' => 15, 'align' => 'R'],
        ['key' => 'total_nilai', 'label' => 'Nilai', 'w' => 30, 'align' => 'R'],
        ['key' => 'keterangan', 'label' => 'Keterangan', 'w' => 55, 'align' => 'L'],
    ];
    $pdf->boTitle = 'Laporan Keuangan - Vendor Refund';
    bo_export_pdf_set_table($pdf, $colsRef, true);
    $rowsRef = [];
    foreach ($refundRows as $r) {
        $rowsRef[] = [
            'tanggal' => $r['tanggal'], 'no_refund' => $r['no_refund'], 'nama_supplier' => $r['nama_supplier'] ?: '-',
            'total_qty' => number_format($r['total_qty']), 'total_nilai' => 'Rp ' . number_format((float)$r['total_nilai'], 0, ',', '.'),
            'keterangan' => $r['keterangan'],
        ];
    }
    bo_pdf_draw_rows($pdf, $colsRef, $rowsRef);

    bo_export_pdf_download($pdf, bo_export_filename('Laporan_Keuangan', 'pdf'));
}
?>
<?php
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Laporan Keuangan - Backoffice',
    'page_title' => 'Laporan Keuangan',
    'page_subtitle' => 'Pantau pengeluaran, omset masuk, refund vendor, dan saldo kas berjalan.',
    'active' => 'reports-finance',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter Analisis Keuangan</h3>
            <div class="bo-card-subtitle">Gunakan penyaringan triwulan atau tanggal kustom secara berkala.</div>
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
            <div class="text-muted small">Data tanggal otomatis mengikuti filter triwulan yang Anda pilih saat tombol terapkan diklik.</div>
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
        <div class="bo-card p-4 bg-light-subtle">
            <div class="text-muted small uppercase fw-bold">Total Pengeluaran</div>
            <div class="fw-bold text-danger fs-4 mt-1">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></div>
            <div class="text-muted small mt-2 border-top pt-2">
                PO: Rp <?= number_format($sum['po'], 0, ',', '.') ?><br>
                Direct: Rp <?= number_format($sum['direct_purchase'], 0, ',', '.') ?><br>
                Lainnya: Rp <?= number_format($sum['pengeluaran'], 0, ',', '.') ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bo-card p-4 bg-light-subtle">
            <div class="text-muted small uppercase fw-bold">Total Pendapatan & Omset</div>
            <div class="fw-bold text-success fs-4 mt-1">Rp <?= number_format($totalPemasukan, 0, ',', '.') ?></div>
            <div class="text-muted small mt-2 border-top pt-2">
                Pendapatan/Omset: Rp <?= number_format($sum['pendapatan_omset'], 0, ',', '.') ?><br>
                Vendor Refund: Rp <?= number_format($sum['pemasukan_refund'], 0, ',', '.') ?><br>
                <span class="text-white-50">-</span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bo-card p-4 bg-light-subtle">
            <div class="text-muted small uppercase fw-bold">Margin</div>
            <div class="fw-bold fs-4 mt-1 <?= ($saldo < 0) ? 'text-danger' : 'text-primary' ?>">Rp <?= number_format($saldo, 0, ',', '.') ?></div>
            <div class="text-muted small mt-2 border-top pt-2">
                Status Operasional:<br>
                <strong><?= ($saldo < 0) ? 'Defisit Anggaran' : 'Surplus Anggaran' ?></strong>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mt-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-out" type="button" role="tab">Pengeluaran</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pendapatan" type="button" role="tab">Pendapatan / Omset</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-refund" type="button" role="tab">Vendor Refund</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-top-supplier" type="button" role="tab">Top 5 Supplier</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-finance-chart" type="button" role="tab">Diagram Laporan Keuangan</button>
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
                            <th>Metode Pembayaran</th>
                            <th class="text-end">Total</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pengeluaranRows)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Data pengeluaran tidak ditemukan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pengeluaranRows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$r['tanggal']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars((string)$r['tipe']) ?></span></td>
                                    <td><?= htmlspecialchars((string)$r['nomor']) ?></td>
                                    <td><?= htmlspecialchars((string)($r['pihak'] ?: '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($r['payment_method'] ?: '-')) ?></td>
                                    <td class="text-end fw-semibold">Rp <?= number_format((float)$r['total_harga'], 0, ',', '.') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars((string)$r['keterangan']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-pendapatan" role="tabpanel">
        <div class="bo-card p-4 mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-end">Total Pendapatan / Omset</th>
                            <th>Keterangan / Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendapatanRows)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Data Pendapatan / Omset tidak ditemukan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendapatanRows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$r['tanggal']) ?></td>
                                    <td class="text-end text-success fw-semibold">Rp <?= number_format((float)$r['total_harga'], 0, ',', '.') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars((string)$r['keterangan']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-refund" role="tabpanel">
        <div class="bo-card p-4 mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No Refund</th>
                            <th>Supplier</th>
                            <th class="text-end">Total Qty</th>
                            <th class="text-end">Total Nilai</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($refundRows)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Data vendor refund tidak ditemukan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($refundRows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$r['tanggal']) ?></td>
                                    <td><?= htmlspecialchars((string)$r['no_refund']) ?></td>
                                    <td><?= htmlspecialchars((string)($r['nama_supplier'] ?: '-')) ?></td>
                                    <td class="text-end"><?= number_format((int)$r['total_qty']) ?></td>
                                    <td class="text-end text-success fw-semibold">Rp <?= number_format((float)$r['total_nilai'], 0, ',', '.') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars((string)$r['keterangan']) ?></td>
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
            <div class="bo-card-header mb-3">
                <div>
                    <h3 class="bo-card-title">Diagram Keuangan Berjalan</h3>
                    <div class="bo-card-subtitle">Komparasi data pengeluaran dan total pemasukan per periode aktif di <?= htmlspecialchars($quarterFinanceChart['quarter_label']) ?>.</div>
                </div>
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
                            <div class="text-muted small">Margin: Rp <?= number_format((float)$bucket['saldo'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex gap-3 flex-wrap mt-3 small">
                <span class="d-inline-flex align-items-center gap-2"><span class="rounded" style="width: 12px; height: 12px; background: #ef4444;"></span>Total Pengeluaran</span>
                <span class="d-inline-flex align-items-center gap-2"><span class="rounded" style="width: 12px; height: 12px; background: #22c55e;"></span>Total Pendapatan & Omset</span>
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

<div class="row g-3 mt-4">
    <div class="col-md-6">
        <div class="bo-card p-4 bg-light-subtle">
            <div class="text-muted small uppercase fw-bold">Sisa Cash</div>
            <div class="fw-bold text-primary fs-4 mt-1">Rp <?= number_format($sisa_cash, 0, ',', '.') ?></div>
            <div class="text-muted small mt-2 border-top pt-2">
                Pendapatan Cash: Rp <?= number_format($cash_pendapatan, 0, ',', '.') ?><br>
                Pengeluaran Cash: Rp <?= number_format($cash_pengeluaran, 0, ',', '.') ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="bo-card p-4 bg-light-subtle">
            <div class="text-muted small uppercase fw-bold">Sisa Saldo</div>
            <div class="fw-bold text-success fs-4 mt-1">Rp <?= number_format($sisa_saldo, 0, ',', '.') ?></div>
            <div class="text-muted small mt-2 border-top pt-2">
                Pendapatan Saldo: Rp <?= number_format($saldo_pendapatan, 0, ',', '.') ?><br>
                Pengeluaran Saldo: Rp <?= number_format($saldo_pengeluaran, 0, ',', '.') ?>
            </div>
        </div>
    </div>
</div>

<?php bo_render_shell_end(); ?>