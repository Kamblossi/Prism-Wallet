<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();
rate_limit($pdo, 'admin:resend_verification:' . (get_current_user_id($pdo) ?? 0), 60);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$email = trim((string)($data['email'] ?? ''));
if ($email === '') { json_error('Missing email', 400, 'bad_request'); }

// Generate token
$token = bin2hex(random_bytes(16));
$expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:sP');
$stmt = $pdo->prepare('UPDATE users SET verification_token = :t, token_expires_at = :x WHERE lower(email) = lower(:e) RETURNING id');
$stmt->execute([':t'=>$token, ':x'=>$expires, ':e'=>$email]);
$uid = $stmt->fetchColumn();
if (!$uid) { json_error('User not found', 404, 'not_found'); }

// Send email using SMTP settings from admin table
$admin = $pdo->query('SELECT * FROM admin ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$serverUrl = $admin['server_url'] ?? (getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? 'http://localhost:8081'));
$verifyLink = rtrim($serverUrl, '/') . '/verify.php?token=' . urlencode($token);

try {
    if (!empty($admin['smtp_address']) && !empty($admin['smtp_port'])) {
        require __DIR__ . '/../../../libs/PHPMailer/PHPMailer.php';
        require __DIR__ . '/../../../libs/PHPMailer/SMTP.php';
        require __DIR__ . '/../../../libs/PHPMailer/Exception.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $admin['smtp_address'];
        $mail->Port = (int)$admin['smtp_port'];
        $mail->SMTPAuth = !empty($admin['smtp_username']) || !empty($admin['smtp_password']);
        if ($mail->SMTPAuth) { $mail->Username = $admin['smtp_username'] ?? ''; $mail->Password = $admin['smtp_password'] ?? ''; }
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
} catch (Throwable $e) { /* ignore */ }

audit_admin_action($pdo, 'user.resend_verification', (int)$uid);
json_success(translate('success', $i18n) ?: 'verification email sent', ['id'=>(int)$uid]);
