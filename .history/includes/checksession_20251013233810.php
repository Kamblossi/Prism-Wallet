<?php
// Clerk-only authentication system
// No more sessions or cookies - everything goes through Clerk

// Development override: allow bypassing Clerk when DISABLE_AUTH=1
$disableAuth = getenv('DISABLE_AUTH');
if ($disableAuth && (int)$disableAuth === 1) {
    // Ensure at least one user exists; create a placeholder if DB is empty
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        // Minimal bootstrap user to allow the app to run
        $pdo->exec("INSERT INTO users (username, email, is_admin) VALUES ('dev', 'dev@example.com', TRUE)");
    }
    $userData = $pdo->query('SELECT * FROM users ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $userId = (int)$userData['id'];
    $username = $userData['email'];
    $main_currency = $userData['main_currency'] ?? 'USD';
} else {
    // Use Clerk authentication instead of old session system  
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

// Set default avatar if empty
if (empty($userData['avatar'])) {
    $userData['avatar'] = "0";
}

// No more cookie or session handling - Clerk handles all authentication
?>
