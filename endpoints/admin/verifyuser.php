<?php
// Admin-only: mark a user verified by email
require_once __DIR__ . '/../../includes/connect_endpoint.php';

header('Content-Type: application/json');

try {
    if (!isset($session) || !method_exists($session, 'isAdmin') || !$session->isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($payload['email'] ?? '');
    if ($email === '') {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE email = :email');
    $stmt->execute(['email' => $email]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'User marked as verified.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No matching user or already verified.']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

?>

