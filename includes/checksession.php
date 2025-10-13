<?php
// Clerk-only authentication system
// No more sessions or cookies - everything goes through Clerk

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

// Set default avatar if empty
if (empty($userData['avatar'])) {
    $userData['avatar'] = "0";
}

// No more cookie or session handling - Clerk handles all authentication
?>
