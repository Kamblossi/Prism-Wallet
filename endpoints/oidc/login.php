<?php
require_once __DIR__ . '/../../includes/connect.php';

session_start();

$row = $pdo->query('SELECT * FROM oauth_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['oidc_oauth_enabled'])) {
    header('Location: /login.php');
    exit;
}

$clientId = $row['client_id'] ?? '';
$authUrl = $row['authorization_url'] ?? '';
$redirectUrl = $row['redirect_url'] ?? (rtrim(getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? ''), '/') . '/endpoints/oidc/callback.php');
$scopes = $row['scopes'] ?? 'openid email profile';

if (!$clientId || !$authUrl || !$redirectUrl) {
    header('Location: /login.php');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['oidc_state'] = $state;

$params = [
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUrl,
    'scope' => $scopes,
    'state' => $state
];
$qs = http_build_query($params);
header('Location: ' . $authUrl . (strpos($authUrl, '?') === false ? '?' : '&') . $qs);
exit;

