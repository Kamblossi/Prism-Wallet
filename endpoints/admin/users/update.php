<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();
rate_limit($pdo, 'admin:update_user:' . (get_current_user_id($pdo) ?? 0), 120);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$id = (int)($data['id'] ?? 0);
if ($id <= 0) { json_error('Missing id', 400, 'bad_request'); }

$fields = [];
$params = [':id'=>$id];
foreach (['username','email','language','avatar'] as $f) {
    if (array_key_exists($f, $data)) { $fields[] = "$f = :$f"; $params[":$f"] = $data[$f]; }
}
if (array_key_exists('budget', $data)) { $fields[] = 'budget = :budget'; $params[':budget'] = (float)$data['budget']; }
if (array_key_exists('is_verified', $data)) { $fields[] = 'is_verified = :is_verified'; $params[':is_verified'] = (int)$data['is_verified']; }
if (array_key_exists('is_active', $data)) { $fields[] = 'is_active = :is_active'; $params[':is_active'] = (bool)$data['is_active']; }
if (!$fields) { json_error('No fields to update', 400, 'nothing_to_update'); }

$sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

audit_admin_action($pdo, 'user.update', $id, ['fields'=>array_keys($data)]);
json_success(translate('success', $i18n) ?: 'updated', ['id'=>$id]);
