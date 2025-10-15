<?php
require_once __DIR__ . '/../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../includes/endpoint_helpers.php';

require_admin($pdo);

$lines = (int)($_GET['lines'] ?? 200);
if ($lines < 10) $lines = 10; if ($lines > 2000) $lines = 2000;

$logPath = ini_get('error_log');
if (!$logPath || !is_file($logPath)) {
    json_success('ok', ['log' => '[no error_log configured or file not found]']);
}

// Tail last N lines portable
$fp = @fopen($logPath, 'r');
if (!$fp) { json_error('Cannot open log file', 500, 'fs_error'); }
try {
    $buffer = '';
    $chunk = 4096; $pos = -1; $readLines = 0;
    fseek($fp, 0, SEEK_END);
    $pos = ftell($fp);
    while ($pos > 0 && $readLines <= $lines) {
        $seek = max(0, $pos - $chunk);
        $len = $pos - $seek;
        fseek($fp, $seek);
        $buf = fread($fp, $len);
        $buffer = $buf . $buffer;
        $readLines = substr_count($buffer, "\n");
        $pos = $seek;
    }
    $parts = explode("\n", $buffer);
    $out = implode("\n", array_slice($parts, -$lines));
} finally {
    fclose($fp);
}

json_success(translate('success', $i18n) ?: 'ok', ['log' => $out]);
