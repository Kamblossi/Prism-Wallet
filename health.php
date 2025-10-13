<?php
require_once __DIR__ . '/includes/connect.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query('SELECT 1');
    $val = (int)$stmt->fetchColumn();
    if ($val === 1) {
        echo json_encode(['status' => 'ok', 'db' => 'postgres', 'time' => time()]);
        exit;
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unexpected DB response']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

?>

