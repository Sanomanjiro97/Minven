<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Cek method dan action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// LOGIKA TAMBAH KATEGORI
if ($method === 'POST' && $action === 'add') {
    $kode_kategori = clean_input($_POST['kode_kategori']);
    $nama_kategori = clean_input($_POST['nama_kategori']);
    $parent_id = !empty($_POST['parent_id']) ? (int)clean_input($_POST['parent_id']) : null;

    // Cek kode kategori unik
    $check = $conn->query("SELECT id FROM kategori WHERE kode_kategori = '$kode_kategori'");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Kode kategori sudah digunakan!";
        header("Location: index.php");
        exit();
    }

    if ($parent_id === null) {
        $sql = "INSERT INTO kategori (kode_kategori, nama_kategori) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $kode_kategori, $nama_kategori);
    } else {
        $sql = "INSERT INTO kategori (kode_kategori, nama_kategori, parent_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $kode_kategori, $nama_kategori, $parent_id);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Kategori berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan kategori!";
    }

    header("Location: index.php");
    exit();
}

// LOGIKA EDIT KATEGORI
if ($method === 'POST' && $action === 'edit') {
    $id = isset($_POST['id']) ? (int)clean_input($_POST['id']) : 0;
    $kode_kategori = clean_input($_POST['kode_kategori']);
    $nama_kategori = clean_input($_POST['nama_kategori']);
    $parent_id = !empty($_POST['parent_id']) ? (int)clean_input($_POST['parent_id']) : null;

    if ($id <= 0) {
        $_SESSION['error'] = "ID kategori tidak valid!";
        header("Location: index.php");
        exit();
    }

    // Cek kategori ada
    $check_id = $conn->query("SELECT id FROM kategori WHERE id = $id");
    if ($check_id->num_rows == 0) {
        $_SESSION['error'] = "Kategori tidak ditemukan!";
        header("Location: index.php");
        exit();
    }

    // Cek kode kategori unik kecuali sendiri
    $check = $conn->query("SELECT id FROM kategori WHERE kode_kategori = '$kode_kategori' AND id != $id");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Kode kategori sudah digunakan!";
        header("Location: index.php");
        exit();
    }

    // Cek parent_id bukan diri sendiri
    if ($parent_id === $id) {
        $_SESSION['error'] = "Kategori tidak bisa menjadi induk dari dirinya sendiri!";
        header("Location: index.php");
        exit();
    }

    if ($parent_id === null) {
        $sql = "UPDATE kategori SET kode_kategori = ?, nama_kategori = ?, parent_id = NULL WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $kode_kategori, $nama_kategori, $id);
    } else {
        $sql = "UPDATE kategori SET kode_kategori = ?, nama_kategori = ?, parent_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $kode_kategori, $nama_kategori, $parent_id, $id);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Kategori berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate kategori: " . $stmt->error;
    }

    header("Location: index.php");
    exit();
}

// LOGIKA HAPUS KATEGORI
if ($method === 'GET' && $action === 'delete') {
    $id = isset($_GET['id']) ? (int)clean_input($_GET['id']) : 0;

    if ($id <= 0) {
        $_SESSION['error'] = "ID kategori tidak valid!";
        header("Location: index.php");
        exit();
    }

    // Cek ada subkategori
    $check = $conn->query("SELECT id FROM kategori WHERE parent_id = $id");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Kategori ini memiliki sub-kategori. Hapus sub-kategori terlebih dahulu!";
        header("Location: index.php");
        exit();
    }

    // Cek digunakan di barang
    $check = $conn->query("SELECT id FROM barang WHERE kategori_id = $id");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Kategori ini sedang digunakan oleh beberapa barang!";
        header("Location: index.php");
        exit();
    }

    $sql = "DELETE FROM kategori WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Kategori berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus kategori!";
    }

    header("Location: index.php");
    exit();
}

// Jika tidak ada action yang valid, kembali ke index.php
header("Location: index.php");
exit();
?>
