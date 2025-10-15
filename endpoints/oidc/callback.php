<?php
require_once __DIR__ . '/../../includes/connect.php';
require_once __DIR__ . '/../../includes/oidc_auth0.php';

$auth0 = prism_get_auth0_instance($pdo);
if (!$auth0) {
    header('Location: /login.php');
    exit;
}

try {
    // Complete the code exchange and establish an SDK session
    $auth0->exchange();
    $user = $auth0->getUser();

    if (!$user) {
        throw new RuntimeException('No user returned by Auth0.');
    }

    $uid = prism_auth0_login_local($pdo, $user);
    if (!$uid) {
        // If auto-create is disabled or email missing, show a friendly message
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><html><head><title>Login Error</title></head><body style="font-family: system-ui, sans-serif">';
        echo '<h2>Sign-in not linked</h2>';
        echo '<p>Your Auth0 account could not be matched to an existing Prism Wallet user. Ask an admin to invite you or enable automatic user creation in Admin → OIDC.</p>';
        echo '<p><a href="/login.php">Back to login</a></p>';
        echo '</body></html>';
        exit;
    }

    // Success: go to the home page
    header('Location: /index.php');
    exit;
} catch (Throwable $e) {
    error_log('[oidc/callback] ' . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html><head><title>Login Error</title></head><body style="font-family: system-ui, sans-serif">';
    echo '<h2>Authentication failed</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/login.php">Back to login</a></p>';
    echo '</body></html>';
    exit;
}
?>

