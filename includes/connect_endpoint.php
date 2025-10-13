<?php
// Lightweight endpoint connector: reuse main Postgres PDO connection
require_once __DIR__ . '/connect.php';

// JSON by default for endpoints
if (!headers_sent()) {
    header('Content-Type: application/json');
}

?>

