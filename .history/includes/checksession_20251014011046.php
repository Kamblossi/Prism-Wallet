<?php
// Authentication middleware supporting Clerk or Local provider
// Provider is selected via AUTH_PROVIDER env ('clerk' by default)
// Local provider uses PHP sessions; Clerk uses JWT via Clerk API

// Development override: allow bypassing Clerk when DISABLE_AUTH=1
$disableAuth = getenv('DISABLE_AUTH');
if ($disableAuth && (int)$disableAuth === 1) {
    // Ensure at least one user exists with a dummy clerk_id to satisfy NOT NULL constraint
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $pdo->beginTransaction();
        try {
            $pdo->exec("INSERT INTO users (clerk_id, username, email, is_admin, avatar, language, budget) VALUES ('dev-user-1', 'dev', 'dev@example.com', TRUE, 'user.svg', 'en', 0)");
            $userIdNew = (int)$pdo->lastInsertId();

            // Create settings row with safe defaults
            $stmt = $pdo->prepare("INSERT INTO settings (user_id, dark_theme, color_theme, monthly_price, convert_currency, remove_background, hide_disabled, disabled_to_bottom, show_original_price, mobile_nav, show_subscription_progress) VALUES (:uid, 2, 'blue', TRUE, TRUE, FALSE, FALSE, FALSE, TRUE, FALSE, FALSE)");
            $stmt->execute(['uid' => $userIdNew]);

            // Ensure a USD currency exists and set as main_currency
            $stmt = $pdo->prepare("INSERT INTO currencies (user_id, name, symbol, code, rate) VALUES (:uid, 'US Dollar', '$', 'USD', 1) RETURNING id");
            $stmt->execute(['uid' => $userIdNew]);
            $usdId = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare('UPDATE users SET main_currency = :cur WHERE id = :uid');
            $stmt->execute(['cur' => $usdId, 'uid' => $userIdNew]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // Best-effort: continue; downstream may still work without defaults
        }
    }
    $userData = $pdo->query('SELECT * FROM users ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $userId = (int)$userData['id'];
    $username = $userData['email'];
    $main_currency = $userData['main_currency'] ?? 'USD';
} else {
    // Choose behavior based on provider
    $provider = $_ENV['AUTH_PROVIDER'] ?? getenv('AUTH_PROVIDER') ?? 'clerk';

    if ($provider === 'local') {
        if (!$session->isLoggedIn()) {
            header('Location: /login.php');
            exit();
        }
        $userData = $session->getUser();
        if (!$userData) {
            header('Location: /login.php');
            exit();
        }
        $userId = (int)$userData['id'];
        $username = $userData['email'];
        $main_currency = $userData['main_currency'] ?? 'USD';
    } else {
        // Clerk authentication
        if (!$session->isLoggedIn()) {
            header('Location: /clerk-auth.php');
            exit();
        }

        // Get current Clerk user (identity from Clerk)
        $clerkUser = $session->getClerkUser();
        if (!$clerkUser) {
            header('Location: /clerk-auth.php');
            exit();
        }

        // Get user data from database using Clerk ID
        $stmt = $pdo->prepare('SELECT * FROM users WHERE clerk_id = ?');
        $stmt->execute([$clerkUser['id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            // User exists in Clerk but not in database - sync them
            error_log('User exists in Clerk but not in database, redirecting to sync');
            header('Location: /clerk-auth.php');
            exit();
        }

        // Set variables for application use
        $userId = $userData['id'];
        $username = $userData['email']; // Use email as username
        $main_currency = $userData['main_currency'] ?? 'USD';
    }
}

// Set default avatar if empty
if (empty($userData['avatar'])) {
    $userData['avatar'] = "0";
}

// No more cookie or session handling - Clerk handles all authentication
?>
