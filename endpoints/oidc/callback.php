<?php
require_once __DIR__ . '/../../includes/connect.php';

session_start();

$settings = $pdo->query('SELECT * FROM oauth_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
if (!$settings || empty($settings['oidc_oauth_enabled'])) { header('Location: /login.php'); exit; }

$state = $_GET['state'] ?? '';
if (empty($_SESSION['oidc_state']) || !hash_equals($_SESSION['oidc_state'], $state)) { header('Location: /login.php'); exit; }
unset($_SESSION['oidc_state']);

$code = $_GET['code'] ?? '';
if (!$code) { header('Location: /login.php'); exit; }

$clientId = $settings['client_id'] ?? '';
$clientSecret = $settings['client_secret'] ?? '';
$tokenUrl = $settings['token_url'] ?? '';
$userInfoUrl = $settings['user_info_url'] ?? '';
$redirectUrl = $settings['redirect_url'] ?? (rtrim(getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? ''), '/') . '/endpoints/oidc/callback.php');
$authStyle = strtolower($settings['auth_style'] ?? 'auto');
$userIdField = $settings['user_identifier_field'] ?? 'sub';
$autoCreate = (int)($settings['auto_create_user'] ?? 0) === 1;

// Exchange code for tokens
$postFields = ['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$redirectUrl,'client_id'=>$clientId];
$headers = ['Content-Type: application/x-www-form-urlencoded'];
if ($authStyle === 'basic' || ($authStyle === 'auto' && $clientSecret)) {
    $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
} else {
    $postFields['client_secret'] = $clientSecret;
}

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$tokenReply = curl_exec($ch);
curl_close($ch);
$tok = $tokenReply ? json_decode($tokenReply, true) : null;
if (!$tok || empty($tok['access_token'])) { header('Location: /login.php'); exit; }

// Fetch user info
$id = null; $email = null; $name = null; $avatar = null; $lang = 'en';
if ($userInfoUrl) {
    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tok['access_token'], 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $u = curl_exec($ch);
    curl_close($ch);
    $user = $u ? json_decode($u, true) : [];
    $id = $user[$userIdField] ?? ($user['sub'] ?? null);
    $email = $user['email'] ?? null;
    $name = $user['name'] ?? ($user['preferred_username'] ?? ($email ? explode('@',$email)[0] : 'user'));
    $avatar = $user['picture'] ?? null;
}

if (!$email) { // fallback if only id available
    $email = $id ? ($id . '@oidc.local') : null;
}
if (!$email) { header('Location: /login.php'); exit; }

// Find or create local user
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :e');
$stmt->execute([':e'=>$email]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row && $autoCreate) {
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare('INSERT INTO users (username, email, is_admin, avatar, language, budget, is_verified, created_at, updated_at) VALUES (:u,:e,FALSE,:a,:l,0,1,NOW(),NOW()) RETURNING id');
        $ins->execute([':u'=>$name, ':e'=>$email, ':a'=>$avatar ?: 'images/avatars/0.svg', ':l'=>$lang]);
        $uid = (int)$ins->fetchColumn();
        // Seed settings + USD currency
        $pdo->prepare("INSERT INTO settings (user_id, dark_theme, color_theme, monthly_price, convert_currency, remove_background, hide_disabled, disabled_to_bottom, show_original_price, mobile_nav, show_subscription_progress) VALUES (:uid,2,'blue',TRUE,TRUE,FALSE,FALSE,FALSE,TRUE,FALSE,FALSE)")->execute([':uid'=>$uid]);
        $pdo->prepare("INSERT INTO currencies (user_id, name, symbol, code, rate) VALUES (:uid,'US Dollar','$','USD',1)")->execute([':uid'=>$uid]);
        $pdo->commit();
        $row = $pdo->query('SELECT * FROM users WHERE id = '.$uid)->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); header('Location: /login.php'); exit; }
}

if (!$row) { header('Location: /login.php'); exit; }

// Log the user in via PHP session
$_SESSION['user_id'] = (int)$row['id'];
header('Location: /index.php');
exit;

