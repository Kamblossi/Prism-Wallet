<?php
use Auth0\SDK\Auth0;

/**
 * Small helper to bootstrap the Auth0 SDK using values saved in the DB
 * via the Admin -> OIDC settings screen (oauth_settings id=1).
 */
function prism_get_auth0_instance(PDO $pdo): ?Auth0 {
    try {
        $row = $pdo->query('SELECT * FROM oauth_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)($row['oidc_oauth_enabled'] ?? 0) !== 1) {
            return null; // OIDC disabled
        }

        $clientId = trim((string)($row['client_id'] ?? ''));
        $clientSecret = trim((string)($row['client_secret'] ?? ''));
        $authorizationUrl = trim((string)($row['authorization_url'] ?? ''));
        $redirectUrl = trim((string)($row['redirect_url'] ?? ''));
        $scopes = trim((string)($row['scopes'] ?? 'openid profile email'));

        if ($authorizationUrl === '' || $clientId === '' || $clientSecret === '') {
            return null;
        }

        // Extract Auth0 domain from the authorization URL
        $parts = parse_url($authorizationUrl);
        $domain = $parts['host'] ?? '';
        if ($domain === '') {
            return null;
        }

        // Determine redirect URI if not explicitly configured
        if ($redirectUrl === '') {
            $base = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? 'http://localhost:8081');
            $redirectUrl = rtrim($base, '/') . '/endpoints/oidc/callback.php';
        }

        $cookieSecret = getenv('AUTH0_COOKIE_SECRET') ?: ($_ENV['AUTH0_COOKIE_SECRET'] ?? null);
        if (!$cookieSecret) {
            // Derive a stable secret from the client secret as a fallback
            $cookieSecret = hash('sha256', 'prismwallet:' . $clientSecret);
        }

        // Build SDK configuration.
        return new Auth0([
            'domain' => $domain,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'cookieSecret' => $cookieSecret,
            'redirectUri' => $redirectUrl,
            'scope' => $scopes,
        ]);
    } catch (Throwable $e) {
        error_log('[oidc] Failed to initialize Auth0 SDK: ' . $e->getMessage());
        return null;
    }
}

/**
 * Maps an Auth0 user profile (array) to a local users row and logs them in.
 * - If a user with the same email exists, reuse it.
 * - If not and auto_create_user=1, create a new local user (verified).
 * - Otherwise, return null and let caller show a helpful error.
 */
function prism_auth0_login_local(PDO $pdo, array $profile): ?int {
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }

    // Read admin-configured options
    $row = $pdo->query('SELECT auto_create_user FROM oauth_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    $autoCreate = (int)($row['auto_create_user'] ?? 0) === 1;

    $email = trim((string)($profile['email'] ?? ''));
    $name = trim((string)($profile['name'] ?? ''));
    $nickname = trim((string)($profile['nickname'] ?? ''));
    $username = $name ?: ($nickname ?: ($email !== '' ? preg_replace('/@.*/','', $email) : 'user'));

    if ($email === '') {
        return null; // Require an email to map accounts safely
    }

    // Try to find existing user (case-insensitive email)
    $stmt = $pdo->prepare('SELECT * FROM users WHERE lower(email) = lower(:email) AND deleted_at IS NULL');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        if (!$autoCreate) {
            return null;
        }
        // Create a minimal local user, marked verified.
        $insert = $pdo->prepare('INSERT INTO users (username, email, firstname, lastname, avatar, is_admin, language, budget, is_verified, created_at) VALUES (:u, :e, :fn, :ln, :av, :ad, :lang, :bud, 1, NOW()) RETURNING id');
        $parts = explode(' ', $name);
        $fn = $parts[0] ?? $username;
        $ln = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
        $insert->execute([
            ':u' => $username,
            ':e' => $email,
            ':fn' => $fn,
            ':ln' => $ln,
            ':av' => 'images/avatars/0.svg',
            ':ad' => 0,
            ':lang' => 'en',
            ':bud' => 0,
        ]);
        $uid = (int)$insert->fetchColumn();
    } else {
        $uid = (int)$user['id'];
    }

    // Update last_login timestamp for visibility
    try {
        $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute([':id' => $uid]);
    } catch (Throwable $e) { /* non-fatal */ }

    // Establish local session
    $_SESSION['user_id'] = $uid;
    $_SESSION['session_version'] = isset($user['session_version']) ? (int)$user['session_version'] : 0;

    return $uid;
}

// Intentionally no closing PHP tag to avoid accidental output before headers
