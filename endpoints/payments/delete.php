<?php

require_once '../../includes/connect_endpoint.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
        $paymentMethodId = $_GET["id"];
        $deleteQuery = "DELETE FROM payment_methods WHERE id = :paymentMethodId and user_id = :userId";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->bindParam(':paymentMethodId', $paymentMethodId, PDO::PARAM_INT);
        $deleteStmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if ($deleteStmt->execute()) {
            $success['success'] = true;
            $success['message'] = translate('payment_method_removed', $i18n);
            $json = json_encode($success);
            header('Content-Type: application/json');
            echo $json;
        } else {
            http_response_code(500);
            echo json_encode(array("message" => translate('error', $i18n)));
        }
    } else {
        http_response_code(405);
        echo json_encode(array("message" => translate('invalid_request_method', $i18n)));
    }
}
$db->close();

?>