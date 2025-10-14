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
  if ($email && $password) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
      if (session_status() === PHP_SESSION_NONE) session_start();
      $_SESSION['user_id'] = (int)$user['id'];
      header('Location: /index.php');
      exit;
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
  <form method="post">
    <h2>Sign in</h2>
    <?php if ($error) { echo '<div style="color:#b00020">'.htmlspecialchars($error).'</div>'; } ?>
    <input type="email" name="email" placeholder="Email" required />
    <input type="password" name="password" placeholder="Password" required />
    <button type="submit">Login</button>
    <a href="/register.php">Create an account</a>
  </form>
</body>
</html>
