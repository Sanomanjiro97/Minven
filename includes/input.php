<?php
if (!function_exists('clean_input')) {
    function clean_input($data) {
        global $conn;
        $data = trim((string)$data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        if (isset($conn) && $conn instanceof mysqli) {
            return $conn->real_escape_string($data);
        }
        return $data;
    }
}

