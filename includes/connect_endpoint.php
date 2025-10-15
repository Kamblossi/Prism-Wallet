<?php
// Lightweight endpoint connector: reuse main Postgres PDO connection
require_once __DIR__ . '/connect.php';

// Load i18n for endpoints that return localized messages
try {
    require_once __DIR__ . '/i18n/languages.php';
    require_once __DIR__ . '/i18n/getlang.php';
    require_once __DIR__ . '/i18n/' . $lang . '.php';
} catch (Throwable $e) {
    // Endpoints should not fail if i18n is unavailable; they can still return plain strings
}

// JSON by default for endpoints
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Map session to commonly expected globals for legacy endpoint scripts
try {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (isset($session) && $session && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()) {
        // Provide $userId for queries
        if (!isset($userId) && method_exists($session, 'getUserId')) {
            $userId = $session->getUserId();
        }
        // Provide compatibility flag for scripts checking $_SESSION['loggedin']
        $_SESSION['loggedin'] = true;
    }
} catch (Throwable $e) {
    // endpoints can decide how to handle unauthenticated state
}

?>
