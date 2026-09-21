<?php
require_once __DIR__ . '/../_init.php';
bo_require_login();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'save') {
    $kode_hpp = trim($_POST['kode_hpp']);
    $nama_barang_hpp = trim($_POST['nama_barang_hpp']);
    $harga_jual = str_replace(['.', ','], ['', '.'], $_POST['harga_jual']);
    $harga_jual = floatval($harga_jual);

    $hpp_total = 0;
    $detail_bahan = isset($_POST['detail_bahan']) ? $_POST['detail_bahan'] : [];
    foreach ($detail_bahan as $bahan) {
        $total = str_replace(['.', ','], ['', '.'], $bahan['total']);
        $hpp_total += floatval($total);
    }

    $detail_biaya = isset($_POST['detail_biaya']) ? $_POST['detail_biaya'] : [];
    foreach ($detail_biaya as $biaya) {
        $nominal = str_replace(['.', ','], ['', '.'], $biaya['nominal']);
        $hpp_total += floatval($nominal);
    }

    $profit = $harga_jual - $hpp_total;
    $persentase_profit = $harga_jual > 0 ? ($profit / $harga_jual) * 100 : 0;

    $created_by = $_SESSION['user_id'] ?? null;
    $stmt = $boMainConn->prepare("INSERT INTO hpp (kode_hpp, nama_barang_hpp, harga_jual, hpp_total, profit, persentase_profit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddddi", $kode_hpp, $nama_barang_hpp, $harga_jual, $hpp_total, $profit, $persentase_profit, $created_by);
    
    if ($stmt->execute()) {
        $hpp_id = $boMainConn->insert_id;

        foreach ($detail_bahan as $bahan) {
            $barang_id = intval($bahan['barang_id']);
            $qty = str_replace(['.', ','], ['', '.'], $bahan['qty']);
            $qty = floatval($qty);
            $harga_satuan = str_replace(['.', ','], ['', '.'], $bahan['harga_satuan']);
            $harga_satuan = floatval($harga_satuan);
            $total = str_replace(['.', ','], ['', '.'], $bahan['total']);
            $total = floatval($total);
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
    $nama_barang_hpp = trim($_POST['nama_barang_hpp']);
    $harga_jual = str_replace(['.', ','], ['', '.'], $_POST['harga_jual']);
    $harga_jual = floatval($harga_jual);

    $hpp_total = 0;
    $detail_bahan = isset($_POST['detail_bahan']) ? $_POST['detail_bahan'] : [];
    foreach ($detail_bahan as $bahan) {
        $total = str_replace(['.', ','], ['', '.'], $bahan['total']);
        $hpp_total += floatval($total);
    }

    $detail_biaya = isset($_POST['detail_biaya']) ? $_POST['detail_biaya'] : [];
    foreach ($detail_biaya as $biaya) {
        $nominal = str_replace(['.', ','], ['', '.'], $biaya['nominal']);
        $hpp_total += floatval($nominal);
    }

    $profit = $harga_jual - $hpp_total;
    $persentase_profit = $harga_jual > 0 ? ($profit / $harga_jual) * 100 : 0;

    $stmt = $boMainConn->prepare("UPDATE hpp SET kode_hpp = ?, nama_barang_hpp = ?, harga_jual = ?, hpp_total = ?, profit = ?, persentase_profit = ? WHERE id = ?");
    $stmt->bind_param("ssddddi", $kode_hpp, $nama_barang_hpp, $harga_jual, $hpp_total, $profit, $persentase_profit, $id);
    
    if ($stmt->execute()) {
        $boMainConn->query("DELETE FROM hpp_detail_bahan WHERE hpp_id = $id");
        $boMainConn->query("DELETE FROM hpp_detail_biaya WHERE hpp_id = $id");

        foreach ($detail_bahan as $bahan) {
            $barang_id = intval($bahan['barang_id']);
            $qty = str_replace(['.', ','], ['', '.'], $bahan['qty']);
            $qty = floatval($qty);
            $harga_satuan = str_replace(['.', ','], ['', '.'], $bahan['harga_satuan']);
            $harga_satuan = floatval($harga_satuan);
            $total = str_replace(['.', ','], ['', '.'], $bahan['total']);
            $total = floatval($total);
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