<?php

$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage == 'index.php') {
    // Redirect to subscriptions page if no subscriptions exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = :userId");
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $subscriptionCount = (int)$stmt->fetchColumn();

    if ($subscriptionCount === 0) {
        header('Location: subscriptions.php');
        exit;
    }
}
