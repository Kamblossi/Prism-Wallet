<?php
// Admin-only: resend verification email to a user by email
require_once __DIR__ . '/../../includes/connect_endpoint.php';

header('Content-Type: application/json');

try {
    if (!isset($session) || !method_exists($session, 'isAdmin') || !$session->isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($payload['email'] ?? '');
    if ($email === '') {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, is_verified FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        // Mask existence for privacy
        echo json_encode(['success' => true, 'message' => 'If the account is unverified, an email has been sent.']);
        exit;
    }
    if ((int)$user['is_verified'] === 1) {
        echo json_encode(['success' => false, 'message' => 'User is already verified.']);
        exit;
    }

    $token = bin2hex(random_bytes(16));
    $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:sP');
    $pdo->prepare('UPDATE users SET verification_token = :t, token_expires_at = :x WHERE id = :id')
        ->execute(['t' => $token, 'x' => $expires, 'id' => (int)$user['id']]);

    $admin = $pdo->query('SELECT * FROM admin ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    $serverUrl = $admin['server_url'] ?? (getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? 'http://localhost:8081'));
    $verifyLink = rtrim($serverUrl, '/') . '/verify.php?token=' . urlencode($token);

    if (!empty($admin['smtp_address']) && !empty($admin['smtp_port'])) {
        require __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
        require __DIR__ . '/../../libs/PHPMailer/SMTP.php';
        require __DIR__ . '/../../libs/PHPMailer/Exception.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $admin['smtp_address'];
        $mail->Port = (int)$admin['smtp_port'];
        $mail->SMTPAuth = !empty($admin['smtp_username']) || !empty($admin['smtp_password']);
        if ($mail->SMTPAuth) {
            $mail->Username = $admin['smtp_username'] ?? '';
            $mail->Password = $admin['smtp_password'] ?? '';
        }
        $enc = strtolower($admin['encryption'] ?? 'none');
        if (in_array($enc, ['ssl','tls'], true)) { $mail->SMTPSecure = $enc; }
        $from = $admin['from_email'] ?? 'no-reply@example.com';
        $mail->setFrom($from, 'Prism Wallet');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Verify your email';
        $mail->Body = 'Click to verify your account: <a href="' . htmlspecialchars($verifyLink, ENT_QUOTES) . '">Verify Email</a>';
        $mail->AltBody = 'Verify your account: ' . $verifyLink;
        $mail->send();
    }

    echo json_encode(['success' => true, 'message' => 'Verification email resent.']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

?>

