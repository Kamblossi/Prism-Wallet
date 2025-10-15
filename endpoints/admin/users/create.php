<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();
rate_limit($pdo, 'admin:create_user:' . (get_current_user_id($pdo) ?? 0), 60);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$username = trim((string)($data['username'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');
if ($username === '' || $email === '') { json_error('Missing username or email', 400, 'bad_request'); }

// Generate password if not provided (local auth); if Clerk, no password
$hash = null; $tempPassword = null;
if (!$password || strlen($password) < 8) { $tempPassword = bin2hex(random_bytes(8)); $password = $tempPassword; }
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, is_verified, is_active, created_at, updated_at, avatar, language, budget) VALUES (:u, :e, :p, 0, TRUE, NOW(), NOW(), :a, :l, 0) RETURNING id');
    $stmt->execute([':u'=>$username, ':e'=>$email, ':p'=>$hash, ':a'=>'images/avatars/0.svg', ':l'=>'en']);
    $newId = (int)$stmt->fetchColumn();

    // Create minimal settings row (idempotent if constraint exists)
    try { $pdo->prepare("INSERT INTO settings (user_id, dark_theme, color_theme, monthly_price, convert_currency, remove_background, hide_disabled, disabled_to_bottom, show_original_price, mobile_nav, show_subscription_progress) VALUES (:uid, 2, 'blue', TRUE, TRUE, FALSE, FALSE, FALSE, TRUE, FALSE, FALSE)")->execute([':uid'=>$newId]); } catch (Throwable $e) { }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error('Failed to create user', 400, 'insert_failed', ['error'=>$e->getMessage()]);
}

audit_admin_action($pdo, 'user.create', $newId, ['email'=>$email]);
$resp = ['id'=>$newId, 'email'=>$email, 'username'=>$username];
if ($tempPassword) { $resp['temporary_password'] = $tempPassword; }
json_success(translate('success', $i18n) ?: 'created', $resp, 201);
