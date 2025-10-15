<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();
rate_limit($pdo, 'admin:export_user:' . (get_current_user_id($pdo) ?? 0), 20);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$userId = (int)($data['user_id'] ?? 0);
if ($userId <= 0) { json_error('Missing user_id', 400, 'bad_request'); }

// Collect data
$u = $pdo->prepare('SELECT id, username, email, is_admin, is_verified, is_active, created_at, updated_at, last_login, language, main_currency, avatar, budget FROM users WHERE id = :id');
$u->execute([':id'=>$userId]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if (!$user) { json_error('User not found', 404, 'not_found'); }

$tables = [
  'settings' => 'SELECT * FROM settings WHERE user_id = :id',
  'subscriptions' => 'SELECT * FROM subscriptions WHERE user_id = :id',
  'payment_methods' => 'SELECT * FROM payment_methods WHERE user_id = :id',
  'categories' => 'SELECT * FROM categories WHERE user_id = :id',
  'currencies' => 'SELECT * FROM currencies WHERE user_id = :id',
  'household' => 'SELECT * FROM household WHERE user_id = :id'
];
$export = ['user'=>$user];
foreach ($tables as $name => $sql) {
    $s = $pdo->prepare($sql);
    $s->execute([':id'=>$userId]);
    $export[$name] = $s->fetchAll(PDO::FETCH_ASSOC);
}

audit_admin_action($pdo, 'user.export', $userId);
// Return as JSON blob
json_success(translate('success', $i18n) ?: 'ok', ['export'=>$export]);
