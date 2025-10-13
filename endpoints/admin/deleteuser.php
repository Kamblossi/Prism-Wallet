<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

// Check that user is an admin
if ($userId !== 1) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    $userId = $data['userId'];

    if ($userId == 1) {
        die(json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]));
    } else {
        // Delete user
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete subscriptions
        $stmt = $pdo->prepare('DELETE FROM subscriptions WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete settings
        $stmt = $pdo->prepare('DELETE FROM settings WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete fixer
        $stmt = $pdo->prepare('DELETE FROM fixer WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete custom colors
        $stmt = $pdo->prepare('DELETE FROM custom_colors WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete currencies
        $stmt = $pdo->prepare('DELETE FROM currencies WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete categories
        $stmt = $pdo->prepare('DELETE FROM categories WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete household
        $stmt = $pdo->prepare('DELETE FROM household WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete payment methods
        $stmt = $pdo->prepare('DELETE FROM payment_methods WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete email notifications
        $stmt = $pdo->prepare('DELETE FROM email_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete telegram notifications
        $stmt = $pdo->prepare('DELETE FROM telegram_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete webhook notifications
        $stmt = $pdo->prepare('DELETE FROM webhook_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete gotify notifications
        $stmt = $pdo->prepare('DELETE FROM gotify_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete pushover notifications
        $stmt = $pdo->prepare('DELETE FROM pushover_notifications WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Dele notification settings
        $stmt = $pdo->prepare('DELETE FROM notification_settings WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete last exchange update
        $stmt = $pdo->prepare('DELETE FROM last_exchange_update WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete email verification
        $stmt = $pdo->prepare('DELETE FROM email_verification WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete totp
        $stmt = $pdo->prepare('DELETE FROM totp WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete total yearly cost
        $stmt = $pdo->prepare('DELETE FROM total_yearly_cost WHERE user_id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        die(json_encode([
            "success" => true,
            "message" => translate('success', $i18n)
        ]));

    }

} else {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

?>