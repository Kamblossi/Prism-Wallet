<?php
require_once __DIR__ . '/includes/connect.php';

// Determine auth provider
$auth_provider = $_ENV['AUTH_PROVIDER'] ?? getenv('AUTH_PROVIDER') ?? 'clerk';

if ($auth_provider === 'local') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    foreach (['theme','inUseTheme','user_locale'] as $c) {
        if (isset($_COOKIE[$c])) {
            setcookie($c, '', [ 'expires' => time()-3600, 'path' => '/' ]);
        }
    }
    header('Location: /login.php');
    exit;
}

// Clerk sign-out: clear session cookie and redirect to auth page
if (isset($_COOKIE['__session'])) {
    setcookie('__session', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
foreach (['theme','inUseTheme','user_locale'] as $c) {
    if (isset($_COOKIE[$c])) {
        setcookie($c, '', [ 'expires' => time()-3600, 'path' => '/' ]);
    }
}
header('Location: /clerk-auth.php');
exit;
?>

