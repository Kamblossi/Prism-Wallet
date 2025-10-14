<?php
require_once '../includes/connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['clerk_id']) || !isset($input['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: clerk_id and email']);
    exit;
}

try {
    // Check if user already exists
    $stmt = $pdo->prepare('SELECT id, clerk_id FROM users WHERE clerk_id = ? OR email = ?');
    $stmt->execute([$input['clerk_id'], $input['email']]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        // Update existing user with Clerk ID if needed
        if (empty($existingUser['clerk_id'])) {
            $updateStmt = $pdo->prepare('UPDATE users SET clerk_id = ?, firstname = ?, lastname = ? WHERE id = ?');
            $updateStmt->execute([
                $input['clerk_id'],
                $input['firstName'] ?? '',
                $input['lastName'] ?? '',
                $existingUser['id']
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'User updated',
            'user_id' => $existingUser['id']
        ]);
    } else {
        // Create new user
        $insertStmt = $pdo->prepare('
            INSERT INTO users (clerk_id, email, firstname, lastname, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ');
        $insertStmt->execute([
            $input['clerk_id'],
            $input['email'],
            $input['firstName'] ?? '',
            $input['lastName'] ?? ''
        ]);

        $userId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'User created',
            'user_id' => $userId
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

