<?php

require_once '../../includes/connect_endpoint.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $categories = $_POST['categoryIds'];
    $order = 2;

    foreach ($categories as $categoryId) {
        $sql = "UPDATE categories SET `order` = :order WHERE id = :categoryId AND user_id = :userId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':order', $order, PDO::PARAM_INT);
        $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $order++;
    }

    $response = [
        "success" => true,
        "message" => translate("sort_order_saved", $i18n)
    ];
    echo json_encode($response);
} else {
    $response = [
        "success" => false,
        "errorMessage" => translate("session_expired", $i18n)
    ];
    echo json_encode($response);
    die();
}

?>