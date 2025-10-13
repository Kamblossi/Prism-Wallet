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
        !isset($data["webhook_url"]) || $data["webhook_url"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        $enabled = $data["enabled"];
        $url = $data["webhook_url"];
        $headers = $data["headers"];
        $payload = $data["payload"];
        $cancelation_payload = $data["cancelation_payload"];
        $ignore_ssl = $data["ignore_ssl"];

        // Validate URL scheme
        $parsedUrl = parse_url($url);
        if (
            !isset($parsedUrl['scheme']) ||
            !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) ||
            !filter_var($url, FILTER_VALIDATE_URL)
        ) {
            die(json_encode([
                "success" => false,
                "message" => translate("error", $i18n)
            ]));
        }

        $query = "SELECT COUNT(*) FROM webhook_notifications WHERE user_id = :userId";
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
                $query = "INSERT INTO webhook_notifications (enabled, url, headers, payload, cancelation_payload, user_id, ignore_ssl)
                              VALUES (:enabled, :url, :headers, :payload, :cancelation_payload, :userId, :ignore_ssl)";
            } else {
                $query = "UPDATE webhook_notifications
                              SET enabled = :enabled, url = :url, headers = :headers, payload = :payload, cancelation_payload = :cancelation_payload, ignore_ssl = :ignore_ssl WHERE user_id = :userId";
            }

            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':enabled', $enabled, PDO::PARAM_INT);
            $stmt->bindValue(':url', $url, PDO::PARAM_STR);
            $stmt->bindValue(':headers', $headers, PDO::PARAM_STR);
            $stmt->bindValue(':payload', $payload, PDO::PARAM_STR);
            $stmt->bindValue(':cancelation_payload', $cancelation_payload, PDO::PARAM_STR);
            $stmt->bindValue(':ignore_ssl', $ignore_ssl, PDO::PARAM_INT);
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