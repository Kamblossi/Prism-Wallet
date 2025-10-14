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

    $apiKey = bin2hex(random_bytes(32));

    $sql = "UPDATE users SET api_key = :apiKey WHERE id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey, PDO::PARAM_STR);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_STR);
    $stmt->execute();

    // Always return success JSON when the UPDATE completes
    $response = [
        "success" => true,
        "message" => translate('user_details_saved', $i18n),
        "apiKey" => $apiKey
    ];
    echo json_encode($response);

}

?>
