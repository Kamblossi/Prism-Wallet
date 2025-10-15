<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$userId = (int)($data['user_id'] ?? 0);
$isActive = isset($data['is_active']) ? (bool)$data['is_active'] : null;
if ($userId <= 0 || $isActive === null) { json_error('Missing user_id or is_active', 400, 'bad_request'); }

$actor = get_current_user_id($pdo);

$stmt = $pdo->prepare('UPDATE users SET is_active = :active WHERE id = :id');
$stmt->execute([':active' => $isActive, ':id' => $userId]);

audit_admin_action($pdo, $isActive ? 'user.activate' : 'user.deactivate', $userId, ['actor' => $actor]);
json_success(translate('success', $i18n) ?: 'updated', ['user_id' => $userId, 'is_active' => $isActive]);
