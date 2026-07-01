<?php
require_once 'config.php';
$result = $conn->query("SHOW COLUMNS FROM supplier");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>