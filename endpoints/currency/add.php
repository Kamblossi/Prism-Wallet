<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $currencyName = "Currency";
    $currencySymbol = "$";
    $currencyCode = "CODE";
    $currencyRate = 1;
    $sqlInsert = "INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :userId)";
    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->bindParam(':name', $currencyName, PDO::PARAM_STR);
    $stmtInsert->bindParam(':symbol', $currencySymbol, PDO::PARAM_STR);
    $stmtInsert->bindParam(':code', $currencyCode, PDO::PARAM_STR);
    $stmtInsert->bindParam(':rate', $currencyRate, PDO::PARAM_STR);
    $stmtInsert->bindParam(':userId', $userId, PDO::PARAM_INT);
    $resultInsert = $stmtInsert->execute();

    if ($resultInsert) {
        $currencyId = $db->lastInsertRowID();
        echo $currencyId;
    } else {
        echo translate('error_adding_currency', $i18n);
    }
} else {
    $response = [
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ];
    echo json_encode($response);
}

?>