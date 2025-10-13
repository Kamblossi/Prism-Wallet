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

    $remove_background = $data['value'];

    // Validate input
    if (!isset($remove_background) || !is_bool($remove_background)) {
        die(json_encode([
            "success" => false,
            "message" => translate("error", $i18n)
        ]));
    }

    $stmt = $pdo->prepare('UPDATE settings SET remove_background = :remove_background WHERE user_id = :userId');
    $stmt->bindParam(':remove_background', $remove_background, PDO::PARAM_INT);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        die(json_encode([
            "success" => true,
            "message" => translate("success", $i18n)
        ]));
    } else {
        die(json_encode([
            "success" => false,
            "message" => translate("error", $i18n)
        ]));
    }
}

?>