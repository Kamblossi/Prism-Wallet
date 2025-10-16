<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $currencyName = 'Currency';
    $currencySymbol = '$';
    $currencyCode = 'CODE';
    $currencyRate = 1;
    $sqlInsert = "INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :userId) RETURNING id";
    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->bindValue(':name', $currencyName, PDO::PARAM_STR);
    $stmtInsert->bindValue(':symbol', $currencySymbol, PDO::PARAM_STR);
    $stmtInsert->bindValue(':code', $currencyCode, PDO::PARAM_STR);
    $stmtInsert->bindValue(':rate', $currencyRate, PDO::PARAM_STR);
    $stmtInsert->bindValue(':userId', $userId, PDO::PARAM_INT);
    if ($stmtInsert->execute()) {
        $currencyId = (int)$stmtInsert->fetchColumn();
        echo (string)$currencyId;
    } else {
        echo 'Error';
    }
} else {
    $response = [
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ];
    echo json_encode($response);
}

?>
