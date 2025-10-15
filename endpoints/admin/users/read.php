<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw ?: 'null', true);
$id = null;
if (isset($_GET['id'])) { $id = (int)$_GET['id']; }
if (!$id && is_array($input) && isset($input['id'])) { $id = (int)$input['id']; }
if (!$id) { json_error('Missing user id', 400, 'bad_request'); }

$stmt = $pdo->prepare('SELECT id, username, email, is_admin, is_verified, is_active, created_at, updated_at, last_login, language, main_currency, avatar, budget FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) { json_error('User not found', 404, 'not_found'); }

$stats = [
    'subscription_count' => (int)$pdo->prepare('SELECT COUNT(*) FROM subscriptions WHERE user_id = :id')->execute([':id' => $id]) ?: 0,
];
// Because PDO::prepare(...)->execute(...) returns bool, we need a second fetch for counts
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM subscriptions WHERE user_id = :id');
$countStmt->execute([':id' => $id]);
$stats['subscription_count'] = (int)$countStmt->fetchColumn();

$pmStmt = $pdo->prepare('SELECT COUNT(*) FROM payment_methods WHERE user_id = :id');
$pmStmt->execute([':id' => $id]);
$stats['payment_methods_count'] = (int)$pmStmt->fetchColumn();

$catStmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE user_id = :id');
$catStmt->execute([':id' => $id]);
$stats['categories_count'] = (int)$catStmt->fetchColumn();

$curStmt = $pdo->prepare('SELECT COUNT(*) FROM currencies WHERE user_id = :id');
$curStmt->execute([':id' => $id]);
$stats['currencies_count'] = (int)$curStmt->fetchColumn();

json_success(translate('success', $i18n) ?: 'ok', ['user' => $u, 'stats' => $stats]);
