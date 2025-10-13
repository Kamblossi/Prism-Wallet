<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    $budget = $data["budget"];

    $sql = "UPDATE users SET budget = :budget WHERE id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':budget', $budget, PDO::PARAM_STR);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_STR);
    $stmt->execute();

    // PDO conversion - removed result check
        $response = [
            "success" => true,
            "message" => translate('user_details_saved', $i18n)
        ];
        echo json_encode($response);
    } else {
        $response = [
            "success" => false,
            "message" => translate('error_updating_user_data', $i18n)
        ];
        echo json_encode($response);
    }
}


?>