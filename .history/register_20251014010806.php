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
        $stmt = $pdo->prepare("INSERT INTO users (clerk_id, username, email, firstname, lastname, is_admin, avatar, language, budget, password_hash) VALUES (:cid, :username, :email, :firstname, :lastname, FALSE, 'user.svg', 'en', 0, :ph) RETURNING id");
        $stmt->execute([
          'cid' => 'local-'.bin2hex(random_bytes(6)),
          'username' => explode('@',$email)[0],
          'email' => $email,
          'firstname' => $firstname,
          'lastname' => $lastname,
          'ph' => $hash,
        ]);
        $uid = (int)$stmt->fetchColumn();
        // Defaults
        $pdo->prepare('INSERT INTO settings (user_id, dark_theme, color_theme, monthly_price, convert_currency, remove_background, hide_disabled, disabled_to_bottom, show_original_price, mobile_nav, show_subscription_progress) VALUES (:u, 2, \"blue\", TRUE, TRUE, FALSE, FALSE, FALSE, TRUE, FALSE, FALSE)')->execute(['u'=>$uid]);
        $stmt = $pdo->prepare("INSERT INTO currencies (user_id, name, symbol, code, rate) VALUES (:u, 'US Dollar', '$', 'USD', 1) RETURNING id");
        $stmt->execute(['u'=>$uid]);
        $usd = (int)$stmt->fetchColumn();
        $pdo->prepare('UPDATE users SET main_currency = :c WHERE id = :u')->execute(['c'=>$usd,'u'=>$uid]);
        $pdo->commit();
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = $uid;
        header('Location: /index.php');
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
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
