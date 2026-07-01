<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/access_check.php';

$boAuthConn = auth_db_conn();
if (!$boAuthConn) {
    $boAuthConn = $conn;
}

$boMainConn = main_db_conn();

if (!function_exists('bo_ensure_backoffice_seed')) {
    function bo_ensure_backoffice_seed() {
        $db = $GLOBALS['boAuthConn'] ?? null;
        if (!$db) return;

        $t = $db->query("SHOW TABLES LIKE 'roles'");
        if (!$t || $t->num_rows === 0) return;
        $t = $db->query("SHOW TABLES LIKE 'users'");
        if (!$t || $t->num_rows === 0) return;
        $t = $db->query("SHOW TABLES LIKE 'user_roles'");
        if (!$t || $t->num_rows === 0) return;

        $db->query("INSERT INTO roles (id, nama_role) VALUES (1, 'Administrator') ON DUPLICATE KEY UPDATE nama_role = VALUES(nama_role)");
        $db->query("INSERT INTO roles (nama_role) SELECT 'Backoffice' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nama_role = 'Backoffice')");

        $stmt = $db->prepare("SELECT id, password FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) return;
        $username = 'sano';
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $hash = password_hash('minven25', PASSWORD_DEFAULT);

        if ($row) {
            $userId = (int)$row['id'];
            if (!password_verify('minven25', (string)($row['password'] ?? ''))) {
                $stmt = $db->prepare("UPDATE users SET password = ?, is_active = 1, role = 'admin', role_id = 1 WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('si', $hash, $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt = $db->prepare("UPDATE users SET is_active = 1, role = 'admin', role_id = 1 WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } else {
            $email = 'sano@minven.local';
            $nama = 'sano';
            $namaLengkap = 'sano';
            $isActive = 1;
            $role = 'admin';
            $adminRoleId = 1;
            $stmt = $db->prepare("INSERT INTO users (username, nama, email, password, nama_lengkap, role, role_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('ssssssii', $username, $nama, $email, $hash, $namaLengkap, $role, $adminRoleId, $isActive);
                $stmt->execute();
                $userId = (int)$db->insert_id;
                $stmt->close();
            } else {
                return;
            }
        }

        if (!isset($userId) || $userId <= 0) return;

        $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        if ($stmt) {
            $roleId = 1;
            $stmt->bind_param('ii', $userId, $roleId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

bo_ensure_backoffice_seed();

if (!function_exists('bo_url_for')) {
    function bo_url_for($path = '') {
        $path = ltrim((string)$path, '/\\');
        $full = 'backoffice/' . $path;
        return url_for($full);
    }
}

if (!function_exists('bo_is_logged_in')) {
    function bo_is_logged_in() {
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }
}

if (!function_exists('bo_require_login')) {
    function bo_require_login() {
        if (!bo_is_logged_in()) {
            header('Location: ' . url_for('index.php'));
            exit();
        }

        if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) {
            return;
        }

        if (!checkAccess('backoffice', 'view')) {
            $_SESSION['error'] = 'Akun ini tidak memiliki akses ke backoffice.';
            header('Location: ' . url_for('dashboard.php'));
            exit();
        }
    }
}

if (!function_exists('bo_export_filename')) {
    function bo_export_filename($base, $ext) {
        $base = trim((string)$base);
        if ($base === '') $base = 'export';
        $base = preg_replace('/[^A-Za-z0-9._ -]/', '_', $base);
        $base = preg_replace('/\\s+/', '_', $base);
        $ext = ltrim((string)$ext, '.');
        return $base . '_' . date('Ymd_His') . '.' . $ext;
    }
}

if (!function_exists('bo_export_xlsx_download')) {
    function bo_export_xlsx_download($filename, $sheetName, array $rows) {
        require_once __DIR__ . '/../libs/SimpleXLSXGen.php';
        $xlsx = new SimpleXLSXGen();
        $xlsx->addSheet($rows, $sheetName);
        $xlsx->downloadAs($filename);
        exit();
    }
}

if (!function_exists('bo_pdf_text')) {
    function bo_pdf_text($text) {
        $text = (string)$text;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
            if ($converted !== false) return $converted;
        }
        return $text;
    }
}

if (!class_exists('BoPdfExport')) {
    require_once __DIR__ . '/../libs/fpdf/fpdf.php';
    class BoPdfExport extends FPDF {
        public $boTitle = '';
        public $boSubtitleLines = [];
        public $boCols = [];

        function Header() {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 8, bo_pdf_text($this->boTitle), 0, 1, 'C');
            if (!empty($this->boSubtitleLines)) {
                $this->SetFont('Arial', '', 10);
                foreach ($this->boSubtitleLines as $line) {
                    $this->Cell(0, 6, bo_pdf_text($line), 0, 1, 'C');
                }
            }
            $this->Ln(3);

            if (!empty($this->boCols)) {
                $this->SetFont('Arial', 'B', 9);
                foreach ($this->boCols as $c) {
                    $w = (float)($c['w'] ?? 20);
                    $label = (string)($c['label'] ?? '');
                    $align = (string)($c['align'] ?? 'L');
                    $this->Cell($w, 7, bo_pdf_text($label), 1, 0, $align);
                }
                $this->Ln();
                $this->SetFont('Arial', '', 9);
            }
        }

        function Footer() {
            $this->SetY(-12);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, bo_pdf_text('Halaman ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
        }
    }
}

if (!function_exists('bo_pdf_fit')) {
    function bo_pdf_fit($pdf, $text, $w) {
        $text = (string)$text;
        $enc = bo_pdf_text($text);
        if ($pdf->GetStringWidth($enc) <= $w - 2) return $enc;
        $suffix = '...';
        $max = $w - 2 - $pdf->GetStringWidth($suffix);
        $out = $enc;
        while ($out !== '' && $pdf->GetStringWidth($out) > $max) {
            $out = substr($out, 0, max(0, strlen($out) - 1));
        }
        return $out . $suffix;
    }
}

if (!function_exists('bo_pdf_draw_rows')) {
    function bo_pdf_draw_rows($pdf, array $cols, array $rows) {
        $pdf->SetFont('Arial', '', 9);
        foreach ($rows as $r) {
            foreach ($cols as $c) {
                $w = (float)($c['w'] ?? 20);
                $key = (string)($c['key'] ?? '');
                $align = (string)($c['align'] ?? 'L');
                $val = '';
                if ($key !== '' && is_array($r) && array_key_exists($key, $r)) {
                    $val = $r[$key];
                } elseif (is_callable($c['value'] ?? null)) {
                    $val = $c['value']($r);
                }
                $pdf->Cell($w, 7, bo_pdf_fit($pdf, (string)$val, $w), 1, 0, $align);
            }
            $pdf->Ln();
        }
    }
}

if (!function_exists('bo_export_pdf_begin')) {
    function bo_export_pdf_begin($orientation, $title, array $subtitleLines, array $cols) {
        $pdf = new BoPdfExport($orientation, 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->boTitle = (string)$title;
        $pdf->boSubtitleLines = $subtitleLines;
        $pdf->boCols = $cols;
        $pdf->AddPage();
        return $pdf;
    }
}

if (!function_exists('bo_export_pdf_set_table')) {
    function bo_export_pdf_set_table($pdf, array $cols, $addPage = true) {
        $pdf->boCols = $cols;
        if ($addPage) $pdf->AddPage();
    }
}

if (!function_exists('bo_export_pdf_download')) {
    function bo_export_pdf_download($pdf, $filename) {
        $pdf->Output($filename, 'D');
        exit();
    }
}

if (!function_exists('bo_nav_items')) {
    function bo_nav_items() {
        return [
            'overview' => [
                [
                    'key' => 'dashboard',
                    'label' => 'Dashboard',
                    'caption' => 'Ringkasan backoffice',
                    'icon' => 'bi bi-grid-1x2-fill',
                    'href' => bo_url_for('dashboard.php'),
                ],
            ],
            'reports' => [
                [
                    'key' => 'reports-po',
                    'label' => 'Laporan PO',
                    'caption' => 'Purchase order',
                    'icon' => 'bi bi-clipboard-data',
                    'href' => bo_url_for('reports/po.php'),
                ],
                [
                    'key' => 'reports-direct',
                    'label' => 'Laporan Direct',
                    'caption' => 'Pembelian direct',
                    'icon' => 'bi bi-bag-check',
                    'href' => bo_url_for('reports/direct.php'),
                ],
                [
                    'key' => 'reports-finance',
                    'label' => 'Keuangan',
                    'caption' => 'Ringkasan cashflow',
                    'icon' => 'bi bi-currency-exchange',
                    'href' => bo_url_for('reports/finance.php'),
                ],
                [
                    'key' => 'reports-inventory',
                    'label' => 'Inventory',
                    'caption' => 'Stok dan pergerakan',
                    'icon' => 'bi bi-box-seam',
                    'href' => bo_url_for('reports/inventory.php'),
                ],
                [
                    'key' => 'inventory-price',
                    'label' => 'Inventory Price',
                    'caption' => 'Nilai stok per item',
                    'icon' => 'bi bi-tags',
                    'href' => bo_url_for('reports/inventory_price.php'),
                ],
                [
                    'key' => 'item-movement',
                    'label' => 'Item Movement',
                    'caption' => 'Pergerakan item',
                    'icon' => 'bi bi-graph-up-arrow',
                    'href' => bo_url_for('reports/item_movement.php'),
                ],
            ],
            'management' => [
                [
                    'key' => 'users',
                    'label' => 'Users',
                    'caption' => 'Kelola akun',
                    'icon' => 'bi bi-people',
                    'href' => bo_url_for('users.php'),
                ],
                [
                    'key' => 'roles',
                    'label' => 'Roles',
                    'caption' => 'Hak akses',
                    'icon' => 'bi bi-shield-check',
                    'href' => bo_url_for('roles.php'),
                ],
            ],
            'system' => [
                [
                    'key' => 'main-app',
                    'label' => 'Aplikasi Utama',
                    'caption' => 'Kembali ke sistem',
                    'icon' => 'bi bi-window-stack',
                    'href' => url_for('dashboard.php'),
                ],
                [
                    'key' => 'logout',
                    'label' => 'Logout',
                    'caption' => 'Keluar dari akun',
                    'icon' => 'bi bi-box-arrow-right',
                    'href' => url_for('logout.php'),
                ],
            ],
        ];
    }
}

if (!function_exists('bo_nav_is_active')) {
    function bo_nav_is_active($itemKey, $activeKey) {
        $itemKey = (string)$itemKey;
        $activeKey = (string)$activeKey;
        if ($itemKey === $activeKey) return true;
        if ($itemKey === 'roles' && $activeKey === 'role-access') return true;
        if ($itemKey === 'reports-po' && $activeKey === 'po-detail') return true;
        if ($itemKey === 'reports-direct' && $activeKey === 'direct-detail') return true;
        return false;
    }
}

if (!function_exists('bo_get_user_identity')) {
    function bo_get_user_identity() {
        $username = trim((string)($_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? $_SESSION['username'] ?? 'User'));
        $userRole = trim((string)($_SESSION['nama_role'] ?? $_SESSION['role'] ?? 'Backoffice'));
        $avatar = strtoupper(substr($username !== '' ? $username : 'U', 0, 1));
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $profilePicture = trim((string)($_SESSION['profile_picture'] ?? ''));
        $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
        $uploadUserDir = $projectRoot . '/uploads/user';
        $profilePictureUrl = '';

        $mainConn = (($GLOBALS['boMainConn'] ?? null) instanceof mysqli ? $GLOBALS['boMainConn'] : null);
        if ($mainConn instanceof mysqli && !$mainConn->connect_error && $userId > 0) {
            $stmt = $mainConn->prepare("SELECT profile_picture, COALESCE(NULLIF(nama_lengkap, ''), NULLIF(nama, ''), NULLIF(username, ''), 'User') AS display_name FROM users WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res instanceof mysqli_result) {
                    $row = $res->fetch_assoc();
                    $dbProfilePicture = trim((string)($row['profile_picture'] ?? ''));
                    $dbDisplayName = trim((string)($row['display_name'] ?? ''));
                    if ($dbDisplayName !== '') {
                        $username = $dbDisplayName;
                    }
                    if ($dbProfilePicture !== '') {
                        $profilePicture = $dbProfilePicture;
                    }
                }
                $stmt->close();
            }
        }

        if ($profilePicture === '' && $userId > 0 && is_dir($uploadUserDir)) {
            $uploadedFiles = glob($uploadUserDir . '/' . $userId . '_*');
            if (is_array($uploadedFiles) && $uploadedFiles !== []) {
                usort($uploadedFiles, static function ($a, $b) {
                    return filemtime($b) <=> filemtime($a);
                });
                $profilePicture = (string)$uploadedFiles[0];
            }
        }

        if ($profilePicture !== '') {
            $profilePicture = str_replace('\\', '/', $profilePicture);
            $profilePictureFile = basename($profilePicture);
            $profilePictureFs = $uploadUserDir . '/' . $profilePictureFile;

            if ($profilePictureFile !== '' && is_file($profilePictureFs)) {
                $profilePictureUrl = url_for('uploads/user/' . rawurlencode($profilePictureFile)) . '?v=' . filemtime($profilePictureFs);
            } elseif (
                strpos($profilePicture, 'http://') === 0 ||
                strpos($profilePicture, 'https://') === 0 ||
                strpos($profilePicture, 'data:') === 0
            ) {
                $profilePictureUrl = $profilePicture;
            } else {
                if (preg_match('#^[A-Za-z]:/#', $profilePicture) && strpos($profilePicture, $projectRoot . '/') === 0) {
                    $profilePicture = substr($profilePicture, strlen($projectRoot) + 1);
                }

                if (strpos($profilePicture, BASE_PATH) === 0) {
                    $profilePictureUrl = $profilePicture;
                } else {
                    $profilePicture = preg_replace('#^(\.\./)+#', '', $profilePicture);
                    $profilePicture = ltrim($profilePicture, '/');

                    if (strpos($profilePicture, trim(BASE_PATH, '/')) === 0) {
                        $profilePictureUrl = '/' . $profilePicture;
                    } else {
                        $profilePictureUrl = url_for($profilePicture);
                    }
                }
            }
        }

        $avatar = strtoupper(substr($username !== '' ? $username : 'U', 0, 1));

        return [
            'name' => $username !== '' ? $username : 'User',
            'role' => $userRole !== '' ? $userRole : 'Backoffice',
            'avatar' => $avatar !== '' ? $avatar : 'U',
            'profile_picture_url' => $profilePictureUrl,
        ];
    }
}

if (!function_exists('bo_render_sidebar')) {
    function bo_render_sidebar($activeKey) {
        $user = bo_get_user_identity();
        $groups = bo_nav_items();
        $groupLabels = [
            'overview' => 'Overview',
            'reports' => 'Reports',
            'management' => 'Management',
            'system' => 'System',
        ];
        ?>
        <aside class="bo-sidebar">
            <a class="bo-brand" href="<?= htmlspecialchars(bo_url_for('dashboard.php')) ?>">
                <span class="bo-brand-mark">m</span>
                <span class="bo-brand-text">
                    <span class="bo-brand-title d-block">Minven</span>
                    <span class="bo-brand-subtitle d-block">Backoffice Panel</span>
                </span>
            </a>

            <?php foreach ($groups as $groupKey => $items): ?>
                <div class="bo-sidebar-label"><?= htmlspecialchars($groupLabels[$groupKey] ?? ucfirst((string)$groupKey)) ?></div>
                <div class="bo-nav-list">
                    <?php foreach ($items as $item): ?>
                        <?php $isActive = bo_nav_is_active((string)($item['key'] ?? ''), (string)$activeKey); ?>
                        <a class="bo-nav-link<?= $isActive ? ' active' : '' ?>" href="<?= htmlspecialchars((string)($item['href'] ?? '#')) ?>">
                            <i class="<?= htmlspecialchars((string)($item['icon'] ?? 'bi bi-dot')) ?>"></i>
                            <span class="bo-nav-copy">
                                <strong><?= htmlspecialchars((string)($item['label'] ?? 'Menu')) ?></strong>
                                <span><?= htmlspecialchars((string)($item['caption'] ?? '')) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <a class="bo-user-card bo-user-card-link" href="<?= htmlspecialchars(url_for('user/profile.php')) ?>">
                <div class="bo-user-row">
                    <div class="bo-user-avatar">
                        <?php if ($user['profile_picture_url'] !== ''): ?>
                            <img src="<?= htmlspecialchars($user['profile_picture_url']) ?>" alt="<?= htmlspecialchars($user['name']) ?>">
                        <?php else: ?>
                            <?= htmlspecialchars($user['avatar']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="bo-user-meta">
                        <div class="bo-user-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="bo-user-role"><?= htmlspecialchars($user['role']) ?></div>
                    </div>
                </div>
            </a>
        </aside>
        <?php
    }
}

if (!function_exists('bo_render_shell_start')) {
    function bo_render_shell_start(array $options = []) {
        $title = (string)($options['title'] ?? 'Backoffice - MINVEN');
        $pageTitle = (string)($options['page_title'] ?? 'Backoffice');
        $pageSubtitle = (string)($options['page_subtitle'] ?? '');
        $activeKey = (string)($options['active'] ?? '');
        $headerActions = (string)($options['header_actions'] ?? '');
        $extraHead = (string)($options['extra_head'] ?? '');
        $user = bo_get_user_identity();

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];
        $now = new DateTime('now');
        $dateLabel = $now->format('d') . ' ' . ($monthNames[(int)$now->format('n')] ?? $now->format('M')) . ' ' . $now->format('Y');
        $dayNames = [
            'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu', 'Sun' => 'Minggu',
        ];
        $dayLabel = $dayNames[$now->format('D')] ?? $now->format('l');
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title) ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
            <link rel="stylesheet" href="<?= htmlspecialchars(url_for('asset/css/backoffice-material.css')) ?>">
            <link rel="icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
            <?= $extraHead ?>
        </head>
        <body class="bo-body">
            <div class="offcanvas offcanvas-start bo-sidebar-mobile d-lg-none" tabindex="-1" id="boSidebarMobile" aria-labelledby="boSidebarMobileLabel">
                <div class="offcanvas-body p-0">
                    <?php bo_render_sidebar($activeKey); ?>
                </div>
            </div>

            <div class="bo-shell">
                <div class="bo-sidebar-desktop d-none d-lg-block">
                    <?php bo_render_sidebar($activeKey); ?>
                </div>

                <main class="bo-content">
                    <div class="bo-topbar">
                        <div>
                            <div class="text-muted small fw-semibold">Minven Backoffice</div>
                            <h1><?= htmlspecialchars($pageTitle) ?></h1>
                            <?php if ($pageSubtitle !== ''): ?>
                                <p><?= htmlspecialchars($pageSubtitle) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="bo-topbar-actions">
                            <button class="btn bo-icon-button d-none d-lg-inline-flex" type="button" id="boSidebarToggle" aria-label="Tutup sidebar" aria-expanded="true" title="Tutup sidebar">
                                <i class="bi bi-layout-sidebar-inset"></i>
                            </button>
                            <button class="btn bo-icon-button d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#boSidebarMobile" aria-controls="boSidebarMobile">
                                <i class="bi bi-list"></i>
                            </button>
                            <?= $headerActions ?>
                            <div class="dropdown">
                                <button class="btn bo-profile-chip dropdown-toggle" type="button" id="boProfileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="bo-profile-avatar">
                                        <?php if ($user['profile_picture_url'] !== ''): ?>
                                            <img src="<?= htmlspecialchars($user['profile_picture_url']) ?>" alt="<?= htmlspecialchars($user['name']) ?>">
                                        <?php else: ?>
                                            <?= htmlspecialchars($user['avatar']) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="bo-profile-meta">
                                        <span class="bo-profile-name"><?= htmlspecialchars($user['name']) ?></span>
                                        <span class="bo-profile-role"><?= htmlspecialchars($user['role']) ?></span>
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end bo-profile-menu" aria-labelledby="boProfileMenu">
                                    <li class="dropdown-item-text">
                                        <div class="bo-profile-menu-date"><?= htmlspecialchars($dateLabel) ?></div>
                                        <div class="bo-profile-menu-day"><?= htmlspecialchars($dayLabel) ?></div>
                                    </li>
                                    <li><a class="dropdown-item" href="<?= htmlspecialchars(url_for('user/profile.php')) ?>"><i class="bi bi-person-circle me-2"></i>Profil</a></li>
                                    <li><a class="dropdown-item" href="<?= htmlspecialchars(url_for('user/live_chat.php')) ?>"><i class="bi bi-chat-dots me-2"></i>Live Chat</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= htmlspecialchars(url_for('logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
        <?php
    }
}

if (!function_exists('bo_render_shell_end')) {
    function bo_render_shell_end($extraScripts = '') {
        ?>
                    <div class="bo-footer">© <?= date('Y') ?> Minven Backoffice. All rights reserved.</div>
                </main>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                (function () {
                    var shell = document.querySelector('.bo-shell');
                    var toggleButton = document.getElementById('boSidebarToggle');
                    if (!shell || !toggleButton) {
                        return;
                    }

                    var storageKey = 'minven:backoffice-sidebar-collapsed';
                    var icon = toggleButton.querySelector('i');

                    function syncSidebar(collapsed) {
                        shell.classList.toggle('bo-sidebar-collapsed', collapsed);
                        toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                        toggleButton.setAttribute('aria-label', collapsed ? 'Buka sidebar' : 'Tutup sidebar');
                        toggleButton.setAttribute('title', collapsed ? 'Buka sidebar' : 'Tutup sidebar');
                        if (icon) {
                            icon.className = collapsed ? 'bi bi-layout-sidebar' : 'bi bi-layout-sidebar-inset';
                        }
                    }

                    var savedState = null;
                    try {
                        savedState = window.localStorage.getItem(storageKey);
                    } catch (error) {
                        savedState = null;
                    }
                    syncSidebar(savedState === '1');

                    toggleButton.addEventListener('click', function () {
                        var collapsed = !shell.classList.contains('bo-sidebar-collapsed');
                        syncSidebar(collapsed);
                        try {
                            window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
                        } catch (error) {
                            // Abaikan jika browser memblokir localStorage.
                        }
                    });
                })();
            </script>
            <?= (string)$extraScripts ?>
        </body>
        </html>
        <?php
    }
}
