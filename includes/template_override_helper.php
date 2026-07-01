<?php
/**
 * Helper override template upload:
 * - kategori 'laporan' dipakai untuk file download/excel
 * - kategori 'cetakan' dipakai untuk halaman print/pdf
 */

if (!function_exists('getActiveUploadedTemplate')) {
    function getActiveUploadedTemplate(mysqli $conn, string $kategori): ?array
    {
        static $checkedTable = false;
        static $tableExists = false;
        static $cache = [];

        if (!in_array($kategori, ['laporan', 'cetakan'], true)) {
            return null;
        }

        if (array_key_exists($kategori, $cache)) {
            return $cache[$kategori];
        }

        if (!$checkedTable) {
            $checkedTable = true;
            $res = $conn->query("SHOW TABLES LIKE 'setup_file_template'");
            $tableExists = $res && $res->num_rows > 0;
        }

        if (!$tableExists) {
            $cache[$kategori] = null;
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT file_path, nama_file_asli, file_ext
             FROM setup_file_template
             WHERE kategori = ? AND is_active = 1
             ORDER BY uploaded_at DESC, id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            $cache[$kategori] = null;
            return null;
        }

        $stmt->bind_param('s', $kategori);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['file_path'])) {
            $cache[$kategori] = null;
            return null;
        }

        $cache[$kategori] = $row;
        return $row;
    }
}

if (!function_exists('applyUploadedTemplateOverride')) {
    function applyUploadedTemplateOverride(mysqli $conn, string $kategori): bool
    {
        if (isset($_GET['force_default']) && $_GET['force_default'] === '1') {
            return false;
        }

        $template = getActiveUploadedTemplate($conn, $kategori);
        if (!$template) {
            return false;
        }

        if (headers_sent()) {
            return false;
        }

        $targetPath = ltrim((string)$template['file_path'], '/');
        header('Location: ' . url_for($targetPath));
        exit();
    }
}

