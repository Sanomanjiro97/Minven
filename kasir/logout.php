<?php
require_once __DIR__ . '/_init.php';
kasir_logout();
header('Location: ' . url_for('logout.php'));
exit();