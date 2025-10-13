<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

require 'settimezone.php';

$query = "SELECT * FROM admin";
$stmt = $pdo->prepare($query);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin['require_email_verification'] == 0) {
    if (php_sapi_name() !== 'cli') {
        echo "Email verification is not required.";
    }
    die();
}

$query = "SELECT * FROM email_verification WHERE email_sent = 0";
$stmt = $pdo->prepare($query);
$stmt->execute();

$rows = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = $row;
}

if ($rows) {
    if ($admin['smtp_address'] && $admin['smtp_port'] && $admin['smtp_username'] && $admin['smtp_password'] && $admin['encryption']) {
        // There are SMTP settings
        $smtpAddress = $admin['smtp_address'];
        $smtpPort = $admin['smtp_port'];
        $smtpUsername = $admin['smtp_username'];
        $smtpPassword = $admin['smtp_password'];
        $fromEmail = empty($admin['from_email']) ? 'wallos@wallosapp.com' : $admin['from_email'];
        $encryption = $admin['encryption'];
        $server_url = $admin['server_url'];
        $smtpAuth = (isset($admin["smtp_username"]) && $admin["smtp_username"] != "") || (isset($admin["smtp_password"]) && $admin["smtp_password"] != "");

        require __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
        require __DIR__ . '/../../libs/PHPMailer/SMTP.php';
        require __DIR__ . '/../../libs/PHPMailer/Exception.php';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpAddress;
        $mail->SMTPAuth = $smtpAuth;
        if ($smtpAuth) {
          $mail->Username = $smtpUsername;
          $mail->Password = $smtpPassword;
        }
        if ($encryption != "none") {
          $mail->SMTPSecure = $encryption;
        }
        $mail->Port = $smtpPort;
        $mail->setFrom($fromEmail);

        try {
            foreach ($rows as $user) {
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Prism Wallet - Email Verification';
                $mail->Body = '<img src="' . $server_url . '/images/icon/android-chrome-192x192.png" alt="Prism Wallet" />
                    <br>
                    Registration on Prism Wallet was successful.
                    <br>
                    Please click the following link to verify your email: <a href="' . $server_url . '/verifyemail.php?email=' . $user['email'] . '&token=' . $user['token'] . '">Verify Email</a>';

                $mail->send();

                $query = "UPDATE email_verification SET email_sent = 1 WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
                $stmt->execute();

                $mail->clearAddresses();

                echo "Verification email sent to " . $user['email'] . "<br>";
            }
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        // There are no SMTP settings
        if (php_sapi_name() !== 'cli') {
            echo "SMTP settings are not configured. Please configure SMTP settings in the admin page.";
        }
        exit();
    }
} else {
    // There are no verification emails to be sent
    if (php_sapi_name() !== 'cli') {
        echo "No verification emails to be sent.";
    }
    exit();
}

?>
