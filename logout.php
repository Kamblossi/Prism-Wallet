<?php
require_once __DIR__ . '/includes/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Read OIDC logout before clearing session
$logoutUrl = null;
try {
    $row = $pdo->query('SELECT oidc_oauth_enabled, logout_url FROM oauth_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if ($row && (int)($row['oidc_oauth_enabled'] ?? 0) === 1) {
        $logoutUrl = $row['logout_url'] ?? null;
    }
} catch (Throwable $e) { $logoutUrl = null; }

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
foreach (['theme','inUseTheme','user_locale','__session'] as $c) {
    if (isset($_COOKIE[$c])) {
        setcookie($c, '', [ 'expires' => time()-3600, 'path' => '/' ]);
    }
}

if ($logoutUrl) {
    $base = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? 'http://localhost:8081');
    $redir = rtrim($base, '/') . '/login.php';
    $glue = (strpos($logoutUrl, '?') === false) ? '?' : '&';
    header('Location: ' . $logoutUrl . $glue . 'post_logout_redirect_uri=' . urlencode($redir));
    exit;
}

header('Location: /login.php');
exit;
?>
