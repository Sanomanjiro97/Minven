<?php
require_once __DIR__ . '/../config.php';

if (!function_exists('product_catalog_ensure_schema')) {
    function product_catalog_ensure_schema($conn) {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!$conn instanceof mysqli || $conn->connect_error) {
            return;
        }

        $conn->query("CREATE TABLE IF NOT EXISTS `hpp` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `kode_hpp` varchar(50) NOT NULL,
            `gudang_id` int(11) NOT NULL DEFAULT 1,
            `nama_barang_hpp` varchar(255) NOT NULL,
            `harga_jual` decimal(15,2) NOT NULL DEFAULT 0.00,
            `hpp_total` decimal(15,2) NOT NULL DEFAULT 0.00,
            `profit` decimal(15,2) NOT NULL DEFAULT 0.00,
            `persentase_profit` decimal(5,2) NOT NULL DEFAULT 0.00,
            `status` enum('active','inactive') NOT NULL DEFAULT 'active',
            `keterangan` text DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `updated_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `kode_hpp` (`kode_hpp`),
            KEY `idx_hpp_gudang_id` (`gudang_id`),
            KEY `idx_hpp_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $conn->query("CREATE TABLE IF NOT EXISTS `hpp_detail_bahan` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `hpp_id` int(11) NOT NULL,
            `barang_id` int(11) NOT NULL,
            `qty` decimal(10,2) NOT NULL DEFAULT 0.00,
            `harga_satuan` decimal(15,2) NOT NULL DEFAULT 0.00,
            `satuan` varchar(50) DEFAULT NULL,
            `total` decimal(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `hpp_id` (`hpp_id`),
            KEY `barang_id` (`barang_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}

if (!function_exists('product_catalog_generate_code')) {
    function product_catalog_generate_code($conn) {
        $prefix = 'HPP-' . date('Ymd');
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM hpp WHERE kode_hpp LIKE CONCAT(?, '%')");
        if (!$stmt) {
            return $prefix . '-001';
        }
        $stmt->bind_param('s', $prefix);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $next = ((int)($row['total'] ?? 0)) + 1;
        return $prefix . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('product_catalog_fetch_one')) {
    function product_catalog_fetch_one($conn, $productId) {
        product_catalog_ensure_schema($conn);

        $productId = (int)$productId;
        if (!$conn instanceof mysqli || $conn->connect_error || $productId <= 0) {
            return null;
        }

        $stmt = $conn->prepare("SELECT
                h.id AS product_id,
                h.id,
                h.kode_hpp AS kode_product,
                h.nama_barang_hpp AS nama_product,
                h.gudang_id,
                g.nama_gudang,
                h.hpp_total AS hpp,
                h.harga_jual,
                h.status,
                h.keterangan,
                h.updated_at,
                h.created_at
            FROM hpp h
            LEFT JOIN gudang g ON g.id = h.gudang_id
            WHERE h.id = ?
            LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('product_catalog_fetch_details')) {
    function product_catalog_fetch_details($conn, $productId) {
        product_catalog_ensure_schema($conn);

        $productId = (int)$productId;
        if (!$conn instanceof mysqli || $conn->connect_error || $productId <= 0) {
            return [];
        }

        $rows = [];
        $stmt = $conn->prepare("SELECT
                d.id,
                d.hpp_id AS product_id,
                d.barang_id,
                d.qty AS qty,
                b.kode_barang,
                b.nama_barang,
                COALESCE(d.satuan, b.satuan, s.nama_satuan, '') AS satuan,
                COALESCE(d.satuan, b.satuan, s.nama_satuan, '') AS nama_satuan
            FROM hpp_detail_bahan d
            LEFT JOIN barang b ON b.id = d.barang_id
            LEFT JOIN satuan s ON s.id = b.satuan_id
            WHERE d.hpp_id = ?
            ORDER BY b.nama_barang ASC, d.id ASC");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('product_catalog_fetch_recipe_stock_map')) {
    function product_catalog_fetch_recipe_stock_map($conn, array $productIds) {
        product_catalog_ensure_schema($conn);

        if (!$conn instanceof mysqli || $conn->connect_error || $productIds === []) {
            return [];
        }

        $cleanIds = [];
        foreach ($productIds as $productId) {
            $productId = (int)$productId;
            if ($productId > 0) {
                $cleanIds[] = $productId;
            }
        }
        $cleanIds = array_values(array_unique($cleanIds));
        if ($cleanIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $types = str_repeat('i', count($cleanIds));
        $sql = "SELECT
                h.id AS product_id,
                h.gudang_id,
                d.barang_id,
                d.qty AS recipe_qty,
                b.kode_barang,
                b.nama_barang,
                COALESCE(d.satuan, b.satuan, s.nama_satuan, '') AS nama_satuan,
                COALESCE(SUM(gs.stok_sisa), 0) AS stok_tersedia
            FROM hpp h
            INNER JOIN hpp_detail_bahan d ON d.hpp_id = h.id
            LEFT JOIN barang b ON b.id = d.barang_id
            LEFT JOIN satuan s ON s.id = b.satuan_id
            LEFT JOIN gudang_stok gs ON gs.gudang_id = h.gudang_id AND gs.barang_id = d.barang_id
            WHERE h.id IN ({$placeholders})
            GROUP BY
                h.id, h.gudang_id, d.barang_id, d.qty, b.kode_barang, b.nama_barang, b.satuan, s.nama_satuan";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $bind = [$types];
        foreach ($cleanIds as $index => $value) {
            $bind[] = &$cleanIds[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $res = $stmt->get_result();

        $map = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $productId = (int)$row['product_id'];
            $recipeQty = max(0.0001, (float)($row['recipe_qty'] ?? 0));
            $stokTersedia = max(0, (float)($row['stok_tersedia'] ?? 0));
            $units = (int)floor($stokTersedia / $recipeQty);

            if (!isset($map[$productId])) {
                $map[$productId] = [
                    'available_units' => null,
                    'ingredients' => [],
                ];
            }

            if ($map[$productId]['available_units'] === null) {
                $map[$productId]['available_units'] = $units;
            } else {
                $map[$productId]['available_units'] = min($map[$productId]['available_units'], $units);
            }

            $map[$productId]['ingredients'][] = [
                'barang_id' => (int)$row['barang_id'],
                'kode_barang' => (string)($row['kode_barang'] ?? ''),
                'nama_barang' => (string)($row['nama_barang'] ?? ''),
                'nama_satuan' => (string)($row['nama_satuan'] ?? ''),
                'recipe_qty' => $recipeQty,
                'stok_tersedia' => $stokTersedia,
            ];
        }
        $stmt->close();

        foreach ($map as $productId => $info) {
            if ($info['available_units'] === null) {
                $map[$productId]['available_units'] = 0;
            }
        }

        return $map;
    }
}

if (!function_exists('product_catalog_fetch_active_products_for_sync')) {
    function product_catalog_fetch_active_products_for_sync($conn) {
        product_catalog_ensure_schema($conn);

        if (!$conn instanceof mysqli || $conn->connect_error) {
            return [];
        }

        $rows = [];
        $sql = "SELECT
                h.id AS product_id,
                h.id,
                h.kode_hpp AS kode_product,
                h.nama_barang_hpp AS nama_product,
                h.gudang_id,
                g.nama_gudang,
                h.hpp_total AS hpp,
                h.harga_jual,
                h.status,
                h.keterangan,
                h.updated_at,
                h.created_at,
                COUNT(d.id) AS total_bahan,
                COALESCE(SUM(CAST(d.qty AS SIGNED)), 0) AS total_qty_resep
            FROM hpp h
            LEFT JOIN gudang g ON g.id = h.gudang_id
            LEFT JOIN hpp_detail_bahan d ON d.hpp_id = h.id
            WHERE h.status = 'active'
            GROUP BY
                h.id, h.kode_hpp, h.nama_barang_hpp, h.gudang_id, g.nama_gudang,
                h.hpp_total, h.harga_jual, h.status, h.keterangan, h.updated_at, h.created_at
            ORDER BY h.nama_barang_hpp ASC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('pos_sync_to_pendapatan_manual')) {
    function pos_sync_to_pendapatan_manual($mainConn, $posConn, $tanggal = null) {
        if (!$mainConn instanceof mysqli || $mainConn->connect_error || !$posConn instanceof mysqli || $posConn->connect_error) {
            return ['success' => false, 'message' => 'Koneksi database tidak tersedia.'];
        }
        $targetTanggal = $tanggal ? date('Y-m-d', strtotime($tanggal)) : date('Y-m-d');
        $sumQuery = "SELECT
            COALESCE(SUM(grand_total), 0) AS total_omset_hari,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN grand_total ELSE 0 END), 0) AS total_cash,
            COALESCE(SUM(CASE WHEN payment_method = 'transfer' THEN grand_total ELSE 0 END), 0) AS total_transfer,
            COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN grand_total ELSE 0 END), 0) AS total_qris,
            COALESCE(SUM(CASE WHEN payment_method = 'debit' THEN grand_total ELSE 0 END), 0) AS total_debit,
            COALESCE(SUM(CASE WHEN payment_method = 'ewallet' THEN grand_total ELSE 0 END), 0) AS total_ewallet
        FROM pos_sales
        WHERE DATE(sale_date) = ? AND settlement_id IS NULL";
        $stmtSum = $posConn->prepare($sumQuery);
        if (!$stmtSum) return ['success' => false, 'message' => 'Gagal menyiapkan query rangkuman POS.'];
        $stmtSum->bind_param('s', $targetTanggal);
        $stmtSum->execute();
        $resSum = $stmtSum->get_result();
        $rowSum = $resSum ? $resSum->fetch_assoc() : null;
        $stmtSum->close();
        if (!$rowSum) {
            return ['success' => false, 'message' => 'Gagal mendapatkan rangkuman omset.'];
        }
        $totalOmset = (float)($rowSum['total_omset_hari'] ?? 0);
        if ($totalOmset <= 0) {
            return ['success' => true, 'message' => 'Tidak ada omset untuk disinkronkan.'];
        }
        if (!function_exists('bo_upsert_pendapatan_manual')) {
            require_once __DIR__ . '/../backoffice/_init.php';
        }
        $upsertResult = bo_upsert_pendapatan_manual(
            $targetTanggal,
            $totalOmset,
            0,
            0,
            0,
            (float)($rowSum['total_cash'] ?? 0),
            (float)($rowSum['total_transfer'] ?? 0),
            (float)($rowSum['total_qris'] ?? 0),
            (float)($rowSum['total_debit'] ?? 0),
            (float)($rowSum['total_ewallet'] ?? 0)
        );
        return $upsertResult;
    }
}
