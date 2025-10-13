<?php
require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    if (
        !isset($data["bot_token"]) || $data["bot_token"] == "" ||
        !isset($data["chat_id"]) || $data["chat_id"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        $enabled = $data["enabled"];
        $bot_token = $data["bot_token"];
        $chat_id = $data["chat_id"];

        $query = "SELECT COUNT(*) FROM telegram_notifications WHERE user_id = :userId";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":userId", $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($result === false) {
            $response = [
                "success" => false,
                "message" => translate('error_saving_notifications', $i18n)
            ];
            echo json_encode($response);
        } else {
            $row = $result->fetchArray();
            $count = $row[0];
            if ($count == 0) {
                $query = "INSERT INTO telegram_notifications (enabled, bot_token, chat_id, user_id)
                              VALUES (:enabled, :bot_token, :chat_id, :userId)";
            } else {
                $query = "UPDATE telegram_notifications
                              SET enabled = :enabled, bot_token = :bot_token, chat_id = :chat_id WHERE user_id = :userId";
            }

            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':enabled', $enabled, PDO::PARAM_INT);
            $stmt->bindValue(':bot_token', $bot_token, PDO::PARAM_STR);
            $stmt->bindValue(':chat_id', $chat_id, PDO::PARAM_STR);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $response = [
                    "success" => true,
                    "message" => translate('notifications_settings_saved', $i18n)
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "success" => false,
                    "message" => translate('error_saving_notifications', $i18n)
                ];
                echo json_encode($response);
            }
        }
    }
}
?>