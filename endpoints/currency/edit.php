<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['currencyId']) && $_GET['currencyId'] !== "" && isset($_GET['name']) && $_GET['name'] !== "" && isset($_GET['symbol']) && $_GET['symbol'] !== "") {
        $currencyId = (int)$_GET['currencyId'];
        $name = validate($_GET['name']);
        $symbol = validate($_GET['symbol']);
        $code = validate($_GET['code']);
        $stmt = $pdo->prepare('UPDATE currencies SET name = :name, symbol = :symbol, code = :code WHERE id = :currencyId AND user_id = :userId');
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':symbol', $symbol, PDO::PARAM_STR);
        $stmt->bindParam(':code', $code, PDO::PARAM_STR);
        $stmt->bindParam(':currencyId', $currencyId, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => $name . " " . translate('currency_saved', $i18n)]);
        } else {
            echo json_encode(["success" => false, "message" => translate('failed_to_store_currency', $i18n)]);
        }
    } else {
        echo json_encode(["success" => false, "message" => translate('fields_missing', $i18n)]);
    }
} else {
    echo json_encode(["success" => false, "message" => translate('session_expired', $i18n)]);
}

?>
