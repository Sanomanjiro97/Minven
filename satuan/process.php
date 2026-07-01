<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

/**
 * Helper function to redirect with message
 */
function redirect($url, $status, $message) {
    $_SESSION[$status] = $message;
    header("Location: $url");
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Proses Tambah Satuan
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_satuan = trim($_POST['kode_satuan'] ?? '');
    $nama_satuan = trim($_POST['nama_satuan'] ?? '');

    if (empty($kode_satuan) || empty($nama_satuan)) {
        redirect('index.php', 'error', 'Kode dan Nama Satuan wajib diisi!');
    }

    // Cek duplikasi kode satuan
    $check = $conn->prepare("SELECT id FROM satuan WHERE kode_satuan = ? OR nama_satuan = ?");
    $check->bind_param("ss", $kode_satuan, $nama_satuan);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        redirect('index.php', 'error', 'Kode atau Nama satuan sudah digunakan!');
    }

    $user_id = $_SESSION['user_id'];
    $sql = "INSERT INTO satuan (kode_satuan, nama_satuan, created_by, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $kode_satuan, $nama_satuan, $user_id);

    if ($stmt->execute()) {
        redirect('index.php', 'success', 'Satuan berhasil ditambahkan!');
    } else {
        redirect('index.php', 'error', 'Gagal menambahkan satuan: ' . $conn->error);
    }
}

// Proses Delete Satuan
if ($action === 'delete') {
    $id = $_POST['id'] ?? $_GET['id'] ?? 0;
    $id = (int)$id;

    if ($id <= 0) {
        redirect('index.php', 'error', 'ID tidak valid!');
    }

    // Cek penggunaan di tabel barang
    $check = $conn->prepare("SELECT id FROM barang WHERE satuan_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        redirect('index.php', 'error', 'Satuan ini sedang digunakan oleh beberapa barang!');
    }

    // Hapus satuan
    $sql = "DELETE FROM satuan WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        redirect('index.php', 'success', 'Satuan berhasil dihapus!');
    } else {
        redirect('index.php', 'error', 'Gagal menghapus satuan: ' . $conn->error);
    }
}

// Proses Edit Satuan
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $kode_satuan = trim($_POST['kode_satuan'] ?? '');
    $nama_satuan = trim($_POST['nama_satuan'] ?? '');

    if ($id <= 0 || empty($kode_satuan) || empty($nama_satuan)) {
        redirect('index.php', 'error', 'Data tidak lengkap!');
    }

    // Cek apakah kode/nama satuan sudah ada (kecuali untuk id yang sama)
    $check = $conn->prepare("SELECT id FROM satuan WHERE (kode_satuan = ? OR nama_satuan = ?) AND id != ?");
    $check->bind_param("ssi", $kode_satuan, $nama_satuan, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        redirect('index.php', 'error', 'Kode atau Nama satuan sudah digunakan!');
    }

    $sql = "UPDATE satuan SET kode_satuan = ?, nama_satuan = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $kode_satuan, $nama_satuan, $id);

    if ($stmt->execute()) {
        redirect('index.php', 'success', 'Satuan berhasil diupdate!');
    } else {
        redirect('index.php', 'error', 'Gagal mengupdate satuan: ' . $conn->error);
    }
}

// Jika tidak ada action yang sesuai
header("Location: index.php");
exit();
?>
