<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();
rate_limit($pdo, 'admin:force_logout:' . (get_current_user_id($pdo) ?? 0), 60);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$userId = (int)($data['user_id'] ?? 0);
if ($userId <= 0) { json_error('Missing user_id', 400, 'bad_request'); }

$stmt = $pdo->prepare('UPDATE users SET session_version = COALESCE(session_version,0) + 1 WHERE id = :id');
$stmt->execute([':id'=>$userId]);

audit_admin_action($pdo, 'user.force_logout', $userId);
json_success(translate('success', $i18n) ?: 'revoked sessions', ['user_id'=>$userId]);
