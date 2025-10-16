<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if (!isset($_POST['paymentId']) || !isset($_POST['name']) || $_POST['paymentId'] === '' || $_POST['name'] === '') {
    die(json_encode([
        "success" => false,
        "message" => translate('fields_missing', $i18n)
    ]));
}

$paymentId = $_POST['paymentId'];
$name = $_POST['name'];

$sql = "UPDATE payment_methods SET name = :name WHERE id = :paymentId and user_id = :userId";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':name', $name, PDO::PARAM_STR);
$stmt->bindParam(':paymentId', $paymentId, PDO::PARAM_INT);
$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
$ok = $stmt->execute();
if ($ok) {
    echo json_encode([
        'success' => true,
        'message' => translate('payment_renamed', $i18n)
    ]);
    exit;
}

http_response_code(500);
echo json_encode([
    'success' => false,
    'message' => translate('payment_not_renamed', $i18n)
]);

?>
