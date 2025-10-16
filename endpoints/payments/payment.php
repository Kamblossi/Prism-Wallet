<?php
require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if (!isset($_GET['paymentId']) || !isset($_GET['enabled'])) {
    die(json_encode([
        "success" => false,
        "message" => translate('fields_missing', $i18n)
    ]));
}

$paymentId = $_GET['paymentId'];

$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM subscriptions WHERE payment_method_id = :paymentId AND user_id = :userId');
$stmt->bindValue(':paymentId', $paymentId, PDO::PARAM_INT);
$stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$inUse = ((int)($row['count'] ?? 0)) > 0;

if ($inUse) {
    die(json_encode([
        "success" => false,
        "message" => translate('payment_in_use', $i18n)
    ]));
}

$enabledRaw = $_GET['enabled'];
$enabled = ($enabledRaw === '1' || $enabledRaw === 'true' || $enabledRaw === 't' || $enabledRaw === 1 || $enabledRaw === true);

$sqlUpdate = 'UPDATE payment_methods SET enabled = :enabled WHERE id = :id AND user_id = :userId';
$stmtUpdate = $pdo->prepare($sqlUpdate);
$stmtUpdate->bindValue(':enabled', $enabled, PDO::PARAM_BOOL);
$stmtUpdate->bindParam(':id', $paymentId);
$stmtUpdate->bindParam(':userId', $userId);
$resultUpdate = $stmtUpdate->execute();

$text = $enabled ? 'enabled' : 'disabled';

if ($resultUpdate) {
    die(json_encode([
        "success" => true,
        "message" => translate($text, $i18n)
    ]));
}

die(json_encode([
    "success" => false,
    "message" => translate('failed_update_payment', $i18n)
]));
