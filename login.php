<?php
require_once __DIR__ . '/includes/connect.php';

$error = '';
$notice = '';

// Offer OIDC login when enabled
$oidcEnabled = false;
try {
  $row = $pdo->query('SELECT oidc_oauth_enabled FROM oauth_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
  $oidcEnabled = $row && (int)($row['oidc_oauth_enabled'] ?? 0) === 1;
} catch (Throwable $e) { $oidcEnabled = false; }

// Handle resend verification email request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
  $resendEmail = trim($_POST['resend_email'] ?? '');
  if ($resendEmail) {
    try {
      $stmt = $pdo->prepare('SELECT id, is_verified FROM users WHERE email = :email');
      $stmt->execute(['email' => $resendEmail]);
      $u = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($u && (int)$u['is_verified'] !== 1) {
        $token = bin2hex(random_bytes(16));
        $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:sP');
        $pdo->prepare('UPDATE users SET verification_token = :t, token_expires_at = :x WHERE id = :id')
            ->execute(['t'=>$token,'x'=>$expires,'id'=>(int)$u['id']]);

        // Load SMTP settings and send
        $admin = $pdo->query('SELECT * FROM admin ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $serverUrl = $admin['server_url'] ?? (getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? 'http://localhost:8081'));
        $verifyLink = rtrim($serverUrl, '/') . '/verify.php?token=' . urlencode($token);

        if (!empty($admin['smtp_address']) && !empty($admin['smtp_port'])) {
          require __DIR__ . '/libs/PHPMailer/PHPMailer.php';
          require __DIR__ . '/libs/PHPMailer/SMTP.php';
          require __DIR__ . '/libs/PHPMailer/Exception.php';
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
          $mail->addAddress($resendEmail);
          $mail->isHTML(true);
          $mail->Subject = 'Verify your email';
          $mail->Body = 'Click to verify your account: <a href="' . htmlspecialchars($verifyLink, ENT_QUOTES) . '">Verify Email</a>';
          $mail->AltBody = 'Verify your account: ' . $verifyLink;
          $mail->send();
        }

        $notice = 'If an account exists and is unverified, a new verification email has been sent.';
      } else {
        $notice = 'If an account exists and is unverified, a new verification email has been sent.';
      }
    } catch (Throwable $e) {
      $error = 'Failed to send verification email.';
    }
  } else {
    $error = 'Please enter your email to resend verification.';
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($email && $password) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
      if (isset($user['is_verified']) && (int)$user['is_verified'] !== 1) {
        $error = 'Your account is not verified. Please check your email.';
      } else {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = (int)$user['id'];
        // Track session version for force-logout
        $_SESSION['session_version'] = (int)($user['session_version'] ?? 0);
        header('Location: /index.php');
        exit;
      }
    }
  }
  $error = 'Invalid credentials.';
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Login</title>
  <link rel="stylesheet" href="/styles/login.css?v=1" />
  <style>body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;display:grid;place-items:center;min-height:100vh}form{display:flex;flex-direction:column;gap:12px;padding:24px;border:1px solid #e3e3e3;border-radius:8px;min-width:320px}input{padding:10px 12px;border:1px solid #ccc;border-radius:6px}button{padding:10px 12px;border:0;border-radius:6px;background:#1e90ff;color:#fff;cursor:pointer}</style>
  </head>
<body>
  <div id="toast-container" style="position:fixed;top:16px;right:16px;z-index:9999"></div>
  <script>
    function showToast(msg,type){
      var c=document.getElementById('toast-container');
      var t=document.createElement('div');
      t.textContent=msg;
      t.style.cssText='margin-top:8px; padding:10px 12px; border-radius:6px; color:#fff; box-shadow:0 2px 8px rgba(0,0,0,.15);';
      t.style.background = type==='error' ? '#b00020' : (type==='success' ? '#0a7' : '#333');
      c.appendChild(t);
      setTimeout(function(){ t.style.opacity='0'; t.style.transition='opacity .4s'; }, 3200);
      setTimeout(function(){ t.remove(); }, 3700);
    }
    <?php if ($error) { echo 'showToast('.json_encode($error).',"error");'; } if ($notice) { echo 'showToast('.json_encode($notice).',"success");'; } ?>
  </script>
  <form method="post">
    <h2>Sign in</h2>
    <?php /* messages shown via toast */ ?>
    <input type="email" name="email" placeholder="Email" required />
    <input type="password" name="password" placeholder="Password" required />
    <button type="submit">Login</button>
    <a href="/register.php">Create an account</a>
  </form>

  <?php if ($oidcEnabled): ?>
  <div style="margin-top:12px;">
    <form method="get" action="/endpoints/oidc/login.php">
      <button type="submit">Login with Single Sign-On</button>
    </form>
  </div>
  <?php endif; ?>

  <form method="post" style="margin-top:16px;">
    <h3>Resend verification email</h3>
    <input type="email" name="resend_email" placeholder="Email" required />
    <button type="submit" name="resend_verification" value="1">Resend</button>
  </form>
</body>
</html>
