<?php
// SMTP settings page for logged-in users
require_once __DIR__ . '/../includes/header.php';

// Only allow authenticated users; header.php already enforces login.
// Load current admin settings (single row table)
$stmt = $pdo->query('SELECT * FROM admin ORDER BY id ASC LIMIT 1');
$admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'smtp_address' => '',
    'smtp_port' => '',
    'smtp_username' => '',
    'smtp_password' => '',
    'from_email' => '',
    'encryption' => 'none',
    'server_url' => getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? ''),
];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtp_address = trim($_POST['smtp_address'] ?? '');
    $smtp_port = (int)($_POST['smtp_port'] ?? 0);
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $from_email = trim($_POST['from_email'] ?? '');
    $encryption = strtolower(trim($_POST['encryption'] ?? 'none'));
    $server_url = trim($_POST['server_url'] ?? '');

    if (!in_array($encryption, ['none','ssl','tls'], true)) {
        $encryption = 'none';
    }
    if ($server_url !== '' && !preg_match('~^https?://~', $server_url)) {
        $error = 'Server URL must start with http:// or https://';
    }
    if (!$error && ($smtp_address === '' || $smtp_port <= 0)) {
        $error = 'SMTP address and port are required.';
    }

    if (!$error) {
        if ($admin && isset($admin['id'])) {
            $sql = 'UPDATE admin SET smtp_address=:a, smtp_port=:p, smtp_username=:u, smtp_password=:pw, from_email=:fe, encryption=:enc, server_url=:su WHERE id=:id';
            $params = [
                'a'=>$smtp_address,'p'=>$smtp_port,'u'=>$smtp_username,'pw'=>$smtp_password,
                'fe'=>$from_email,'enc'=>$encryption,'su'=>$server_url,'id'=>$admin['id']
            ];
        } else {
            $sql = 'INSERT INTO admin (smtp_address,smtp_port,smtp_username,smtp_password,from_email,encryption,server_url) VALUES (:a,:p,:u,:pw,:fe,:enc,:su)';
            $params = [
                'a'=>$smtp_address,'p'=>$smtp_port,'u'=>$smtp_username,'pw'=>$smtp_password,
                'fe'=>$from_email,'enc'=>$encryption,'su'=>$server_url
            ];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $message = 'Settings saved.';
        // Reload values
        $stmt = $pdo->query('SELECT * FROM admin ORDER BY id ASC LIMIT 1');
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>SMTP Settings</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:0;padding:24px}
    form{max-width:640px;display:flex;flex-direction:column;gap:12px}
    label{font-weight:600}
    input,select{padding:10px;border:1px solid #ccc;border-radius:6px}
    .row{display:flex;gap:12px}
    .row > *{flex:1}
    .msg{margin-bottom:12px}
    .ok{color:#0a7}
    .err{color:#b00020}
  </style>
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
    <?php if ($message) { echo 'showToast('.json_encode($message).',"success");'; }
          if ($error) { echo 'showToast('.json_encode($error).',"error");'; } ?>
  </script>
  <h1>SMTP Settings</h1>
  <form method="post">
    <label>SMTP Address
      <input type="text" name="smtp_address" value="<?= htmlspecialchars($admin['smtp_address'] ?? '') ?>" required />
    </label>
    <div class="row">
      <label>Port
        <input type="number" name="smtp_port" value="<?= htmlspecialchars((string)($admin['smtp_port'] ?? '')) ?>" required />
      </label>
      <label>Encryption
        <select name="encryption">
          <?php $encVal = strtolower($admin['encryption'] ?? 'none'); ?>
          <option value="none" <?= $encVal==='none'?'selected':'' ?>>None</option>
          <option value="ssl" <?= $encVal==='ssl'?'selected':'' ?>>SSL</option>
          <option value="tls" <?= $encVal==='tls'?'selected':'' ?>>TLS</option>
        </select>
      </label>
    </div>
    <label>SMTP Username
      <input type="text" name="smtp_username" value="<?= htmlspecialchars($admin['smtp_username'] ?? '') ?>" />
    </label>
    <label>SMTP Password
      <input type="password" name="smtp_password" value="<?= htmlspecialchars($admin['smtp_password'] ?? '') ?>" />
    </label>
    <label>From Email
      <input type="email" name="from_email" value="<?= htmlspecialchars($admin['from_email'] ?? '') ?>" />
    </label>
    <label>Server URL
      <input type="text" name="server_url" value="<?= htmlspecialchars($admin['server_url'] ?? '') ?>" placeholder="http://localhost:8081" />
    </label>
    <button type="submit">Save Settings</button>
  </form>
</body>
</html>
