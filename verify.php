<?php
require_once __DIR__ . '/includes/connect.php';

$token = $_GET['token'] ?? '';
if (!$token) {
  http_response_code(400);
  echo 'Missing token';
  exit;
}

try {
  $stmt = $pdo->prepare('SELECT id, token_expires_at FROM users WHERE verification_token = :t AND is_verified = 0');
  $stmt->execute(['t'=>$token]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) {
    http_response_code(400);
    echo 'Invalid or already used token.';
    exit;
  }
  $now = new DateTimeImmutable();
  $exp = $user['token_expires_at'] ? new DateTimeImmutable($user['token_expires_at']) : null;
  if ($exp && $now > $exp) {
    http_response_code(400);
    echo 'Token has expired. Please request a new verification email.';
    exit;
  }
  $upd = $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = :id');
  $upd->execute(['id'=>(int)$user['id']]);
  header('Location: /login.php?verified=1');
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Server error.';
  exit;
}

?>

