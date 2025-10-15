<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$userId = (int)($data['user_id'] ?? 0);
$newPassword = $data['new_password'] ?? null;
if ($userId <= 0) { json_error('Missing user_id', 400, 'bad_request'); }

if (!$newPassword || strlen($newPassword) < 8) {
    // generate a temp password
    $bytes = random_bytes(8); // 16 hex chars
    $newPassword = bin2hex($bytes);
    $generated = true;
} else {
    $generated = false;
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
$stmt->execute([':p' => $hash, ':id' => $userId]);

audit_admin_action($pdo, 'user.reset_password', $userId, ['generated' => $generated]);

$resp = ['user_id' => $userId, 'status' => 'ok'];
if ($generated) { $resp['temporary_password'] = $newPassword; }
json_success(translate('success', $i18n) ?: 'password updated', $resp);
