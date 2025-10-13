<?php
// Cron/CLI connector for Postgres PDO
require_once __DIR__ . '/connect.php';

// Set timezone if provided via env
if ($tz = (getenv('TZ') ?: ($_ENV['TZ'] ?? null))) {
    date_default_timezone_set($tz);
}

?>

