<?php
require_once __DIR__ . '/../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../includes/endpoint_helpers.php';

require_admin($pdo);

$checks = [];

// PHP and app details
$checks['php_version'] = PHP_VERSION;
try { require __DIR__ . '/../../includes/version.php'; $checks['app_version'] = $version ?? null; } catch (Throwable $e) { $checks['app_version'] = null; }

// DB connectivity and counts
try {
    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $checks['db_ok'] = true;
    $checks['users'] = $userCount;
} catch (Throwable $e) {
    $checks['db_ok'] = false; $checks['db_error'] = $e->getMessage();
}

// Disk space
try {
    $checks['disk_free_mb'] = function_exists('disk_free_space') ? round(disk_free_space('.') / 1048576) : null;
} catch (Throwable $e) { $checks['disk_free_mb'] = null; }

// Cronjob last runs if present
try {
    $row = $pdo->query('SELECT date FROM last_update_next_payment_date ORDER BY id DESC LIMIT 1')->fetchColumn();
    $checks['last_update_next_payment'] = $row ?: null;
} catch (Throwable $e) { $checks['last_update_next_payment'] = null; }

json_success(translate('success', $i18n) ?: 'ok', ['health' => $checks]);
