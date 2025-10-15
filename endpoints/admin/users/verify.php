<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();
rate_limit($pdo, 'admin:verify_user:' . (get_current_user_id($pdo) ?? 0), 120);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$email = trim((string)($data['email'] ?? ''));
$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($email === '' && $id <= 0) { json_error('Missing email or id', 400, 'bad_request'); }

if ($id > 0) {
    $stmt = $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = :id');
    $stmt->execute([':id'=>$id]);
    audit_admin_action($pdo, 'user.verify', $id);
    json_success(translate('success', $i18n) ?: 'verified', ['id'=>$id]);
}

$stmt = $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE lower(email) = lower(:e) RETURNING id');
$stmt->execute([':e'=>$email]);
$uid = $stmt->fetchColumn();
if (!$uid) { json_error('User not found', 404, 'not_found'); }
audit_admin_action($pdo, 'user.verify', (int)$uid);
json_success(translate('success', $i18n) ?: 'verified', ['id'=>(int)$uid]);
