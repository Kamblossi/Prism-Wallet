<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => translate('session_expired', $i18n)
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => translate('invalid_request_method', $i18n)
    ]);
    exit;
}

// Read JSON body; allow empty string to clear budget (set to 0)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !array_key_exists('budget', $data)) {
    echo json_encode([
        'success' => false,
        'message' => translate('fill_all_fields', $i18n)
    ]);
    exit;
}

$budgetInput = trim((string)$data['budget']);
$budget = ($budgetInput === '') ? 0.0 : (float)$budgetInput;

try {
    $stmt = $pdo->prepare('UPDATE users SET budget = :budget WHERE id = :userId');
    // NUMERIC can be passed as string; float is fine here
    $stmt->bindValue(':budget', $budget);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => translate('user_details_saved', $i18n)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => translate('error_updating_user_data', $i18n)
    ]);
}

?>
