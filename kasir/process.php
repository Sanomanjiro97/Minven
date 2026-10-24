<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/product_catalog.php';
kasir_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . kasir_url_for('create.php'));
    exit();
}

if (!$kasirMainConn instanceof mysqli || $kasirMainConn->connect_error || !$kasirPosConn instanceof mysqli || $kasirPosConn->connect_error) {
    $_SESSION['kasir_error'] = 'Koneksi database kasir belum siap.';
    header('Location: ' . kasir_url_for('create.php'));
    exit();
}

// 1. Ambil & Sanitasi Data Form
$saleDate       = trim((string)($_POST['sale_date'] ?? ''));
$gudangId       = (int)($_POST['gudang_id'] ?? 1);
if ($gudangId <= 0) {
    $gudangId = 1;
}

$mejaId         = (int)($_POST['meja_id'] ?? 0);
$customerName   = trim((string)($_POST['customer_name'] ?? ''));
$paymentMethod  = trim((string)($_POST['payment_method'] ?? 'cash'));
$discountAmount = (float)($_POST['discount_amount'] ?? 0);
$taxAmount      = (float)($_POST['tax_amount'] ?? 0);
$amountPaid     = (float)($_POST['amount_paid'] ?? 0);
$notes          = trim((string)($_POST['notes'] ?? ''));
$itemsPayload   = (string)($_POST['items_payload'] ?? '[]');
$items          = json_decode($itemsPayload, true);
$allowedPaymentMethods = ['cash', 'transfer', 'qris', 'debit', 'ewallet'];
$shiftId        = (int)($_POST['shift_id'] ?? 0);

// 2. Validasi Shift Aktif
if ($shiftId > 0) {
    $stmt = $kasirPosConn->prepare("SELECT * FROM pos_shifts WHERE id = ? AND status = 'open' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $shiftId);
        $stmt->execute();
        $result = $stmt->get_result();
        $shift = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$shift) {
            $_SESSION['kasir_error'] = 'Shift sudah ditutup atau tidak valid. Silakan buka shift baru.';
            header('Location: ' . kasir_url_for('shift.php'));
            exit();
        }
    } else {
        $_SESSION['kasir_error'] = 'Gagal memeriksa status shift: ' . $kasirPosConn->error;
        header('Location: ' . kasir_url_for('shift.php'));
        exit();
    }
} else {
    $_SESSION['kasir_error'] = 'Silakan buka shift terlebih dahulu sebelum membuat transaksi.';
    header('Location: ' . kasir_url_for('shift.php'));
    exit();
}

// 3. Validasi Form Dasar
if ($saleDate === '' || !is_array($items) || $items === []) {
    $_SESSION['kasir_error'] = 'Data transaksi belum lengkap.';
    header('Location: ' . kasir_url_for('create.php'));
    exit();
}

if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    $paymentMethod = 'cash';
}

// 4. Normalisasi Items & Hitung Subtotal
$normalizedItems = [];
$subtotal = 0.0;
$productIds = [];
$gudangSet = [];
foreach ($items as $item) {
    $productId = (int)($item['product_id'] ?? 0);
    $qty = (int)($item['qty'] ?? 0);
    $unitPrice = (float)($item['unit_price'] ?? 0);
    $itemGudangId = (int)($item['gudang_id'] ?? 0);
    if ($productId <= 0 || $qty <= 0) {
        continue;
    }
    if ($itemGudangId <= 0) {
        $itemGudangId = $gudangId;
    }
    $gudangSet[$itemGudangId] = $itemGudangId;
    $lineTotal = $unitPrice * $qty;
    $subtotal += $lineTotal;
    $normalizedItems[] = [
        'product_id'   => $productId,
        'kode_product' => trim((string)($item['kode_product'] ?? '')),
        'nama_product' => trim((string)($item['nama_product'] ?? '')),
        'gudang_id'    => $itemGudangId,
        'hpp'          => (float)($item['hpp'] ?? 0),
        'qty'          => $qty,
        'unit_price'   => $unitPrice,
        'line_total'   => $lineTotal,
    ];
    $productIds[] = $productId;
}

if ($normalizedItems === []) {
    $_SESSION['kasir_error'] = 'Item transaksi tidak valid.';
    header('Location: ' . kasir_url_for('create.php'));
    exit();
}

$headerGudangId = (int)(current($normalizedItems)['gudang_id'] ?? $gudangId);
if ($headerGudangId <= 0) $headerGudangId = 1;

// =========================================================================
// 5. CEK KETERSEDIAAN STOK RESEP BAHAN BAKU (SIMPEL REALTIME CHECK)
// =========================================================================
$kurangMsg = [];
foreach ($normalizedItems as $item) {
    $productId   = (int)$item['product_id'];
    $qty         = (float)$item['qty'];
    $namaProduct = trim($item['nama_product'] ?: ('Produk ID ' . $productId));

    // Fetch resep/BOM produk
    $recipeDetails = product_catalog_fetch_details($kasirMainConn, $productId);

    if (empty($recipeDetails)) {
        $_SESSION['kasir_error'] = 'Produk "' . $namaProduct . '" belum memiliki konfigurasi resep bahan baku (BOM).';
        header('Location: ' . kasir_url_for('create.php'));
        exit();
    }

    foreach ($recipeDetails as $ing) {
        $barangId        = (int)$ing['barang_id'];
        $qtyResepPerUnit = (float)($ing['qty'] ?? 0);
        if ($qtyResepPerUnit <= 0) $qtyResepPerUnit = 0.0001;
        $totalDibutuhkan = $qtyResepPerUnit * $qty;

        // Cek total stok_sisa bahan baku di gudang_stok
        $stmtCek = $kasirMainConn->prepare("SELECT IFNULL(SUM(stok_sisa), 0) as total_sisa FROM gudang_stok WHERE barang_id = ?");
        $stmtCek->bind_param('i', $barangId);
        $stmtCek->execute();
        $resCek = $stmtCek->get_result()->fetch_assoc();
        $stmtCek->close();

        $stokSisa = (float)($resCek['total_sisa'] ?? 0);

        // Jika stok sisa aktual kurang dari yang dibutuhkan
        if (($stokSisa + 0.009) < $totalDibutuhkan) {
            $namaBahan  = trim($ing['nama_barang'] ?: ('Barang ID ' . $barangId));
            $kurangMsg[] = "• {$namaProduct} -> {$namaBahan}: butuh " . rtrim(rtrim(number_format($totalDibutuhkan, 2, ',', '.'), '0'), ',')
                         . " (stok sisa: " . rtrim(rtrim(number_format($stokSisa, 2, ',', '.'), '0'), ',') . ")";
        }
    }
}

if ($kurangMsg !== []) {
    $_SESSION['kasir_error'] = "Stok bahan baku TIDAK CUKUP untuk pesanan ini:\n" . implode("\n", $kurangMsg);
    header('Location: ' . kasir_url_for('create.php'));
    exit();
}

$grandTotal    = max(0, $subtotal - $discountAmount + $taxAmount);
$paymentStatus = ($amountPaid >= $grandTotal) ? 'paid' : 'partial';
$changeAmount  = max(0, $amountPaid - $grandTotal);
$user          = kasir_current_user();

// 6. Mulai Transaksi Database Dual-Connection
$kasirMainConn->begin_transaction();
$kasirPosConn->begin_transaction();

try {
    // A. Insert Header Transaksi Inventory Stok
    $stmtHeader = $kasirMainConn->prepare("INSERT INTO transaksi_stok (no_transaksi, tanggal, gudang_id, jenis_transaksi, keterangan, created_by) VALUES (?, ?, ?, 'keluar', ?, ?)");
    if (!$stmtHeader) {
        throw new Exception('Gagal menyiapkan query transaksi inventory: ' . $kasirMainConn->error);
    }
    
    $inventoryTxnNumber = 'TXN-KASIR-' . date('YmdHis') . '-' . rand(1000, 9999);
    $tanggalInventory   = date('Y-m-d', strtotime($saleDate));
    $inventoryNotes     = 'POS ' . ($customerName !== '' ? ' - ' . $customerName : '');
    $createdBy          = (int)$user['id'];

    $stmtHeader->bind_param('ssisi', $inventoryTxnNumber, $tanggalInventory, $headerGudangId, $inventoryNotes, $createdBy);
    if (!$stmtHeader->execute()) {
        throw new Exception('Gagal menyimpan header transaksi inventory: ' . $stmtHeader->error);
    }
    $inventoryTransactionId = (int)$kasirMainConn->insert_id;
    $stmtHeader->close();

    // B. Pengurangan Stok Bahan Baku Otomatis (BOM/Resep)
    foreach ($normalizedItems as $item) {
        $productId     = (int)$item['product_id'];
        $qtyProduct   = (float)$item['qty'];
        $recipeDetails = product_catalog_fetch_details($kasirMainConn, $productId);

        foreach ($recipeDetails as $ingredient) {
            $barangId       = (int)$ingredient['barang_id'];
            $qtyPerProduct  = (float)($ingredient['qty'] ?? 0);
            if ($qtyPerProduct <= 0) $qtyPerProduct = 0.0001;
            $totalPemakaian = $qtyPerProduct * $qtyProduct;
            $detailBarang   = (string)($ingredient['nama_barang'] ?? '');

            // Update Stok: stok_terpakai bertambah, stok_sisa berkurang otomatis
            $stmtUpdate = $kasirMainConn->prepare("
                UPDATE gudang_stok 
                SET stok_terpakai = stok_terpakai + ?, 
                    stok_sisa     = stok_awal - (stok_terpakai + ?), 
                    updated_at    = NOW(),
                    updated_by    = ?
                WHERE barang_id = ?
            ");
            if (!$stmtUpdate) {
                throw new Exception('Gagal menyiapkan query update stok: ' . $kasirMainConn->error);
            }
            $stmtUpdate->bind_param('ddii', $totalPemakaian, $totalPemakaian, $createdBy, $barangId);
            if (!$stmtUpdate->execute()) {
                throw new Exception('Gagal memotong stok bahan baku barang ID ' . $barangId . '.');
            }
            $stmtUpdate->close();

            // Catat Log Detail Transaksi Stok
            $stmtStockDetail = $kasirMainConn->prepare("INSERT INTO detail_transaksi_stok (transaksi_stok_id, barang_id, detail_barang, jumlah) VALUES (?, ?, ?, ?)");
            if (!$stmtStockDetail) {
                throw new Exception('Gagal menyiapkan query detail_transaksi_stok: ' . $kasirMainConn->error);
            }
            $jumlahDetailInt = (int)round($totalPemakaian);
            $stmtStockDetail->bind_param('iisi', $inventoryTransactionId, $barangId, $detailBarang, $jumlahDetailInt);
            if (!$stmtStockDetail->execute()) {
                throw new Exception('Gagal menyimpan detail stok bahan baku.');
            }
            $stmtStockDetail->close();
        }
    }

    // C. Update Status Meja Menjadi Terisi
    if ($mejaId > 0) {
        $stmtMeja = $kasirMainConn->prepare("UPDATE meja SET status = 'terisi' WHERE id = ?");
        if ($stmtMeja) {
            $stmtMeja->bind_param('i', $mejaId);
            $stmtMeja->execute();
            $stmtMeja->close();
        }
    }

    // D. Insert POS Sale Header
    $saleNumber = kasir_generate_sale_number($kasirPosConn);
    $stmtSale   = $kasirPosConn->prepare("INSERT INTO pos_sales (sale_number, sale_date, gudang_id, cashier_user_id, cashier_username, customer_name, payment_method, payment_status, subtotal, discount_amount, tax_amount, grand_total, amount_paid, change_amount, notes, inventory_transaction_id, shift_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmtSale) {
        throw new Exception('Gagal menyiapkan transaksi POS (pos_sales): ' . $kasirPosConn->error);
    }
    
    $cashierUsername = (string)$user['username'];
    $stmtSale->bind_param(
        'ssiissssddddddsii',
        $saleNumber,
        $saleDate,
        $headerGudangId,
        $createdBy,
        $cashierUsername,
        $customerName,
        $paymentMethod,
        $paymentStatus,
        $subtotal,
        $discountAmount,
        $taxAmount,
        $grandTotal,
        $amountPaid,
        $changeAmount,
        $notes,
        $inventoryTransactionId,
        $shiftId
    );

    if (!$stmtSale->execute()) {
        throw new Exception('Gagal menyimpan transaksi POS: ' . $stmtSale->error);
    }
    $saleId = (int)$kasirPosConn->insert_id;
    $stmtSale->close();

    // E. Insert POS Sale Items
    $stmtSaleItem = $kasirPosConn->prepare("INSERT INTO pos_sale_items (sale_id, product_id, kode_product, nama_product, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmtSaleItem) {
        throw new Exception('Gagal menyiapkan detail transaksi POS (pos_sale_items): ' . $kasirPosConn->error);
    }

    foreach ($normalizedItems as $item) {
        $productId   = (int)$item['product_id'];
        $kodeProduct = (string)$item['kode_product'];
        $namaProduct = (string)$item['nama_product'];
        $qty         = (int)$item['qty'];
        $unitPrice   = (float)$item['unit_price'];
        $lineTotal   = (float)$item['line_total'];

        $stmtSaleItem->bind_param('iissidd', $saleId, $productId, $kodeProduct, $namaProduct, $qty, $unitPrice, $lineTotal);
        if (!$stmtSaleItem->execute()) {
            throw new Exception('Gagal menyimpan item transaksi POS.');
        }
    }
    $stmtSaleItem->close();

    // Commit Kedua Database Jika Sukses
    $kasirMainConn->commit();
    $kasirPosConn->commit();

    $_SESSION['kasir_success'] = 'Transaksi POS berhasil disimpan dengan nomor ' . $saleNumber . '.';
    header('Location: ' . kasir_url_for('view.php?id=' . $saleId));
    exit();

} catch (Throwable $e) {
    $kasirMainConn->rollback();
    $kasirPosConn->rollback();
    $_SESSION['kasir_error'] = 'Transaksi gagal disimpan: ' . $e->getMessage();
    header('Location: ' . kasir_url_for('create.php'));
    exit();
}