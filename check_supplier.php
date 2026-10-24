<?php
require_once 'config.php';
$result = $conn->query("SHOW COLUMNS FROM supplier");
if ($result && $result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\n";
    }
}
?>