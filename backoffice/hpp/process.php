<?php
require_once __DIR__ . '/../_init.php';
bo_require_login();

$action = isset($_GET['action']) ? $_GET['action'] : '';

function hpp_parse_number($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return 0.0;
    }

    if (strpos($value, '.') !== false && strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } else {
        $value = str_replace(',', '.', $value);
    }

    return (float)$value;
}

function hpp_get_master_price($conn, $barang_id) {
    $stmt = $conn->prepare("SELECT harga_beli FROM barang WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $barang_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return (float)($row['harga_beli'] ?? 0);
}

if ($action == 'save') {
    $kode_hpp = trim($_POST['kode_hpp']);
    $gudang_id = (int)($_POST['gudang_id'] ?? 1);
    if ($gudang_id <= 0) $gudang_id = 1;
    $nama_barang_hpp = trim($_POST['nama_barang_hpp']);
    $status = trim($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';
    $harga_jual = str_replace(['.', ','], ['', '.'], $_POST['harga_jual']);
    $harga_jual = floatval($harga_jual);

    $hpp_total = 0;
    $detail_bahan = isset($_POST['detail_bahan']) ? $_POST['detail_bahan'] : [];
    foreach ($detail_bahan as $bahan) {
        $barang_id = (int)($bahan['barang_id'] ?? 0);
        $qty = hpp_parse_number($bahan['qty'] ?? 0);
        $harga_satuan = hpp_get_master_price($boMainConn, $barang_id);
        $hpp_total += $qty * $harga_satuan;
    }

    $detail_biaya = isset($_POST['detail_biaya']) ? $_POST['detail_biaya'] : [];
    foreach ($detail_biaya as $biaya) {
        $nominal = str_replace(['.', ','], ['', '.'], $biaya['nominal']);
        $hpp_total += floatval($nominal);
    }

    $profit = $harga_jual - $hpp_total;
    $persentase_profit = $harga_jual > 0 ? ($profit / $harga_jual) * 100 : 0;

    $created_by = $_SESSION['user_id'] ?? null;
    $updated_by = $created_by;
    $stmt = $boMainConn->prepare("INSERT INTO hpp (kode_hpp, gudang_id, nama_barang_hpp, harga_jual, hpp_total, profit, persentase_profit, status, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissddddsi", $kode_hpp, $gudang_id, $nama_barang_hpp, $harga_jual, $hpp_total, $profit, $persentase_profit, $status, $created_by, $updated_by);
    
    if ($stmt->execute()) {
        $hpp_id = $boMainConn->insert_id;

        foreach ($detail_bahan as $bahan) {
            $barang_id = intval($bahan['barang_id']);
            $qty = hpp_parse_number($bahan['qty'] ?? 0);
            $harga_satuan = hpp_get_master_price($boMainConn, $barang_id);
            $total = $qty * $harga_satuan;
            $satuan = trim($bahan['satuan']);

            $stmt_detail = $boMainConn->prepare("INSERT INTO hpp_detail_bahan (hpp_id, barang_id, qty, harga_satuan, satuan, total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_detail->bind_param("iiddsd", $hpp_id, $barang_id, $qty, $harga_satuan, $satuan, $total);
            $stmt_detail->execute();
        }

        foreach ($detail_biaya as $biaya) {
            $nama_biaya = trim($biaya['nama_biaya']);
            $nominal = str_replace(['.', ','], ['', '.'], $biaya['nominal']);
            $nominal = floatval($nominal);

            $stmt_biaya = $boMainConn->prepare("INSERT INTO hpp_detail_biaya (hpp_id, nama_biaya, nominal) VALUES (?, ?, ?)");
            $stmt_biaya->bind_param("isd", $hpp_id, $nama_biaya, $nominal);
            $stmt_biaya->execute();
        }
    }

    header('Location: ' . bo_url_for('hpp/index.php'));
    exit;
}

if ($action == 'update') {
    $id = intval($_POST['id']);
    $kode_hpp = trim($_POST['kode_hpp']);
    $gudang_id = (int)($_POST['gudang_id'] ?? 1);
    if ($gudang_id <= 0) $gudang_id = 1;
    $nama_barang_hpp = trim($_POST['nama_barang_hpp']);
    $status = trim($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';
    $harga_jual = str_replace(['.', ','], ['', '.'], $_POST['harga_jual']);
    $harga_jual = floatval($harga_jual);

    $hpp_total = 0;
    $detail_bahan = isset($_POST['detail_bahan']) ? $_POST['detail_bahan'] : [];
    $existing_prices = [];
    $existing_result = $boMainConn->query("SELECT barang_id, harga_satuan FROM hpp_detail_bahan WHERE hpp_id = $id");
    if ($existing_result) {
        while ($existing = $existing_result->fetch_assoc()) {
            $existing_prices[(int)$existing['barang_id']] = (float)$existing['harga_satuan'];
        }
    }
    foreach ($detail_bahan as $bahan) {
        $barang_id = (int)($bahan['barang_id'] ?? 0);
        $qty = hpp_parse_number($bahan['qty'] ?? 0);
        $harga_satuan = array_key_exists($barang_id, $existing_prices)
            ? $existing_prices[$barang_id]
            : hpp_get_master_price($boMainConn, $barang_id);
        $hpp_total += $qty * $harga_satuan;
    }

    $detail_biaya = isset($_POST['detail_biaya']) ? $_POST['detail_biaya'] : [];
    foreach ($detail_biaya as $biaya) {
        $nominal = str_replace(['.', ','], ['', '.'], $biaya['nominal']);
        $hpp_total += floatval($nominal);
    }

    $profit = $harga_jual - $hpp_total;
    $persentase_profit = $harga_jual > 0 ? ($profit / $harga_jual) * 100 : 0;

    $updated_by = $_SESSION['user_id'] ?? null;
    $stmt = $boMainConn->prepare("UPDATE hpp SET kode_hpp = ?, gudang_id = ?, nama_barang_hpp = ?, harga_jual = ?, hpp_total = ?, profit = ?, persentase_profit = ?, status = ?, updated_by = ? WHERE id = ?");
    $stmt->bind_param("sissddddsi", $kode_hpp, $gudang_id, $nama_barang_hpp, $harga_jual, $hpp_total, $profit, $persentase_profit, $status, $updated_by, $id);
    
    if ($stmt->execute()) {
        $boMainConn->query("DELETE FROM hpp_detail_bahan WHERE hpp_id = $id");
        $boMainConn->query("DELETE FROM hpp_detail_biaya WHERE hpp_id = $id");

        foreach ($detail_bahan as $bahan) {
            $barang_id = intval($bahan['barang_id']);
            $qty = hpp_parse_number($bahan['qty'] ?? 0);
            $harga_satuan = array_key_exists($barang_id, $existing_prices)
                ? $existing_prices[$barang_id]
                : hpp_get_master_price($boMainConn, $barang_id);
            $total = $qty * $harga_satuan;
            $satuan = trim($bahan['satuan']);

            $stmt_detail = $boMainConn->prepare("INSERT INTO hpp_detail_bahan (hpp_id, barang_id, qty, harga_satuan, satuan, total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_detail->bind_param("iiddsd", $id, $barang_id, $qty, $harga_satuan, $satuan, $total);
            $stmt_detail->execute();
        }

        foreach ($detail_biaya as $biaya) {
            $nama_biaya = trim($biaya['nama_biaya']);
            $nominal = str_replace(['.', ','], ['', '.'], $biaya['nominal']);
            $nominal = floatval($nominal);

            $stmt_biaya = $boMainConn->prepare("INSERT INTO hpp_detail_biaya (hpp_id, nama_biaya, nominal) VALUES (?, ?, ?)");
            $stmt_biaya->bind_param("isd", $id, $nama_biaya, $nominal);
            $stmt_biaya->execute();
        }
    }

    header('Location: ' . bo_url_for('hpp/index.php'));
    exit;
}

if ($action == 'delete') {
    $id = intval($_GET['id']);
    
    $boMainConn->query("DELETE FROM hpp_detail_bahan WHERE hpp_id = $id");
    $boMainConn->query("DELETE FROM hpp_detail_biaya WHERE hpp_id = $id");
    
    $boMainConn->query("DELETE FROM hpp WHERE id = $id");

    header('Location: ' . bo_url_for('hpp/index.php'));
    exit;
}

header('Location: ' . bo_url_for('hpp/index.php'));
exit;
?>