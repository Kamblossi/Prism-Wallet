<?php
require_once __DIR__ . '/../includes/header.php';

if ($isAdmin != 1) {
    header('Location: /index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Manual verify
    if (isset($_POST['verify_user'])) {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $error = 'Email is required.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE email = :email');
                $stmt->execute(['email' => $email]);
                if ($stmt->rowCount() > 0) {
                    $message = 'User marked as verified.';
                } else {
                    $error = 'No user found for that email.';
                }
            } catch (Throwable $e) {
                $error = 'Update failed.';
            }
        }
    }
    // Resend verification email
    if (isset($_POST['resend_verification'])) {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $error = 'Email is required.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id, is_verified FROM users WHERE email = :email');
                $stmt->execute(['email'=>$email]);
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($u && (int)$u['is_verified'] !== 1) {
                    $token = bin2hex(random_bytes(16));
                    $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:sP');
                    $pdo->prepare('UPDATE users SET verification_token = :t, token_expires_at = :x WHERE id = :id')
                        ->execute(['t'=>$token,'x'=>$expires,'id'=>(int)$u['id']]);

                    $adminCfg = $pdo->query('SELECT * FROM admin ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
                    $serverUrl = $adminCfg['server_url'] ?? (getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? 'http://localhost:8081'));
                    $verifyLink = rtrim($serverUrl,'/') . '/verify.php?token=' . urlencode($token);

                    if (!empty($adminCfg['smtp_address']) && !empty($adminCfg['smtp_port'])) {
                        require __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
                        require __DIR__ . '/../libs/PHPMailer/SMTP.php';
                        require __DIR__ . '/../libs/PHPMailer/Exception.php';
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = $adminCfg['smtp_address'];
                        $mail->Port = (int)$adminCfg['smtp_port'];
                        $mail->SMTPAuth = !empty($adminCfg['smtp_username']) || !empty($adminCfg['smtp_password']);
                        if ($mail->SMTPAuth) {
                            $mail->Username = $adminCfg['smtp_username'] ?? '';
                            $mail->Password = $adminCfg['smtp_password'] ?? '';
                        }
                        $enc = strtolower($adminCfg['encryption'] ?? 'none');
                        if (in_array($enc, ['ssl','tls'], true)) { $mail->SMTPSecure = $enc; }
                        $from = $adminCfg['from_email'] ?? 'no-reply@example.com';
                        $mail->setFrom($from, 'Prism Wallet');
                        $mail->addAddress($email);
                        $mail->isHTML(true);
                        $mail->Subject = 'Verify your email';
                        $mail->Body = 'Click to verify your account: <a href="' . htmlspecialchars($verifyLink, ENT_QUOTES) . '">Verify Email</a>';
                        $mail->AltBody = 'Verify your account: ' . $verifyLink;
                        $mail->send();
                    }
                    $message = 'Verification email resent.';
                } else {
                    $message = 'User is already verified or not found.';
                }
            } catch (Throwable $e) {
                $error = 'Failed to resend verification email.';
            }
        }
    }
}

// Load a short list of recent unverified users
$unverified = [];
try {
    $res = $pdo->query("SELECT id, email, created_at FROM users WHERE COALESCE(is_verified,0) = 0 ORDER BY created_at DESC LIMIT 50");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) { $unverified[] = $row; }
} catch (Throwable $e) {}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Verify Users</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;padding:24px}
    form{display:flex;gap:8px;align-items:center;margin-bottom:18px}
    input{padding:8px;border:1px solid #ccc;border-radius:6px}
    button{padding:8px 12px;border:0;border-radius:6px;background:#1e90ff;color:#fff;cursor:pointer}
    table{border-collapse:collapse;width:100%;max-width:760px}
    th,td{border:1px solid #ddd;padding:8px;text-align:left}
    .ok{color:#0a7}.err{color:#b00020}
  </style>
</head>
<body>
  <h1>Manually Verify User</h1>
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
    <?php if ($message) { echo 'showToast('.json_encode($message).',"success");'; }
          if ($error) { echo 'showToast('.json_encode($error).',"error");'; } ?>
  </script>

  <form method="post">
    <input type="email" name="email" placeholder="user@example.com" required />
    <button type="submit" name="verify_user" value="1">Mark Verified</button>
  </form>

  <h2>Unverified users (latest 50)</h2>
  <table>
    <thead><tr><th>ID</th><th>Email</th><th>Created</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($unverified as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
          <td>
            <form method="post" style="margin:0;display:inline-flex;gap:6px;align-items:center">
              <input type="hidden" name="email" value="<?= htmlspecialchars($u['email'] ?? '') ?>" />
              <button type="submit" name="verify_user" value="1">Verify</button>
            </form>
            <form method="post" style="margin:0;display:inline-flex;gap:6px;align-items:center">
              <input type="hidden" name="email" value="<?= htmlspecialchars($u['email'] ?? '') ?>" />
              <button type="submit" name="resend_verification" value="1">Resend</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
