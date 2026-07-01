<?php
require_once __DIR__ . '/_init.php';

if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    header('Location: ' . bo_url_for('dashboard.php'));
    exit();
}

header('Location: ' . url_for('index.php'));
exit();
