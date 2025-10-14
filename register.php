<?php
require_once __DIR__ . '/includes/connect.php';

$auth_provider = $_ENV['AUTH_PROVIDER'] ?? getenv('AUTH_PROVIDER') ?? 'clerk';
if ($auth_provider !== 'local') {
  header('Location: /clerk-auth.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $firstname = trim($_POST['firstname'] ?? '');
  $lastname = trim($_POST['lastname'] ?? '');
  if ($email && $password) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetchColumn()) {
      $error = 'Email already in use.';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $pdo->beginTransaction();
      try {
        // create user with is_verified = 0 and a verification token that expires in 1 hour
        $token = bin2hex(random_bytes(16));
        $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:sP');
        $stmt = $pdo->prepare("INSERT INTO users (clerk_id, username, email, firstname, lastname, is_admin, avatar, language, budget, password_hash, is_verified, verification_token, token_expires_at) VALUES (:cid, :username, :email, :firstname, :lastname, FALSE, 'user.svg', 'en', 0, :ph, 0, :vt, :vx) RETURNING id");
        $stmt->execute([
          'cid' => 'local-'.bin2hex(random_bytes(6)),
          'username' => ($firstname !== '' ? $firstname : explode('@',$email)[0]),
          'email' => $email,
          'firstname' => $firstname,
          'lastname' => $lastname,
          'ph' => $hash,
          'vt' => $token,
          'vx' => $expires,
        ]);
        $uid = (int)$stmt->fetchColumn();
        // Defaults
        $stmt = $pdo->prepare('INSERT INTO settings (user_id, dark_theme, color_theme, monthly_price, convert_currency, remove_background, hide_disabled, disabled_to_bottom, show_original_price, mobile_nav, show_subscription_progress) VALUES (:u, 2, :theme, TRUE, TRUE, FALSE, FALSE, FALSE, TRUE, FALSE, FALSE)');
        $stmt->execute(['u' => $uid, 'theme' => 'blue']);
        $stmt = $pdo->prepare("INSERT INTO currencies (user_id, name, symbol, code, rate) VALUES (:u, 'US Dollar', '$', 'USD', 1) RETURNING id");
        $stmt->execute(['u'=>$uid]);
        $usd = (int)$stmt->fetchColumn();
        $pdo->prepare('UPDATE users SET main_currency = :c WHERE id = :u')->execute(['c'=>$usd,'u'=>$uid]);
        $pdo->commit();

        // Send verification email using SMTP settings from admin
        try {
          $admin = $pdo->query('SELECT * FROM admin ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
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
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Verify your email';
            $mail->Body = 'Welcome! Click to verify your account: <a href="' . htmlspecialchars($verifyLink, ENT_QUOTES) . '">Verify Email</a><br>If you did not sign up, you can ignore this email.';
            $mail->AltBody = 'Verify your account: ' . $verifyLink;
            $mail->send();
          }
        } catch (Throwable $e) {
          // Do not fail registration on email send errors; user can re-request.
        }

        header('Location: /registration_success.php');
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[register] Registration failed: ' . $e->getMessage());
        $error = 'Registration failed.';
      }
    }
  } else {
    $error = 'Email and password are required.';
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Register</title>
  <style>body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;display:grid;place-items:center;min-height:100vh}form{display:flex;flex-direction:column;gap:12px;padding:24px;border:1px solid #e3e3e3;border-radius:8px;min-width:320px}input{padding:10px 12px;border:1px solid #ccc;border-radius:6px}button{padding:10px 12px;border:0;border-radius:6px;background:#1e90ff;color:#fff;cursor:pointer}</style>
</head>
<body>
  <form method="post">
    <h2>Create account</h2>
    <?php if ($error) { echo '<div style="color:#b00020">'.htmlspecialchars($error).'</div>'; } ?>
    <input type="text" name="firstname" placeholder="First name" />
    <input type="text" name="lastname" placeholder="Last name" />
    <input type="email" name="email" placeholder="Email" required />
    <input type="password" name="password" placeholder="Password" required />
    <button type="submit">Register</button>
    <a href="/login.php">Have an account? Sign in</a>
  </form>
</body>
</html>
