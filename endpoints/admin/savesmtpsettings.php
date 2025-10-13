<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

// Check that user is an admin
if ($userId !== 1) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    $smtpAddress = $data['smtpaddress'];
    $smtpPort = $data['smtpport'];
    $encryption = $data['encryption'];
    $smtpUsername = $data['smtpusername'];
    $smtpPassword = $data['smtppassword'];
    $fromEmail = $data['fromemail'];

    if (empty($smtpAddress) || empty($smtpPort)) {
        die(json_encode([
            "success" => false,
            "message" => translate('fill_all_fields', $i18n)
        ]));
    }

    // Save settings
    $stmt = $pdo->prepare('UPDATE admin SET smtp_address = :smtp_address, smtp_port = :smtp_port, encryption = :encryption, smtp_username = :smtp_username, smtp_password = :smtp_password, from_email = :from_email');
    $stmt->bindValue(':smtp_address', $smtpAddress, PDO::PARAM_STR);
    $stmt->bindValue(':smtp_port', $smtpPort, PDO::PARAM_STR);
    $encryption = empty($data['encryption']) ? 'tls' : $data['encryption'];
    $stmt->bindValue(':encryption', $encryption, PDO::PARAM_STR);
    $stmt->bindValue(':smtp_username', $smtpUsername, PDO::PARAM_STR);
    $stmt->bindValue(':smtp_password', $smtpPassword, PDO::PARAM_STR);
    $stmt->bindValue(':from_email', $fromEmail, PDO::PARAM_STR);
    $stmt->execute();

    // PDO conversion - removed result check
        die(json_encode([
            "success" => true,
            "message" => translate('success', $i18n)
        ]));
    } else {
        die(json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]));
    }

}

?>