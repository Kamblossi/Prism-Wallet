<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(translate('error', $i18n) ?: 'Invalid request', 405, 'invalid_method');
}

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];

$fields = [
    'name' => '', 'client_id' => '', 'client_secret' => '',
    'authorization_url' => '', 'token_url' => '', 'user_info_url' => '',
    'redirect_url' => '', 'logout_url' => '', 'user_identifier_field' => '',
    'scopes' => '', 'auth_style' => '', 'auto_create_user' => 0,
    'password_login_disabled' => 0,
];
foreach ($fields as $k => $def) {
    $fields[$k] = isset($data['oidc' . str_replace('_', '', ucwords($k, '_'))]) ? $data['oidc' . str_replace('_', '', ucwords($k, '_'))] : ($data[$k] ?? $def);
}

$exists = (int)$pdo->query('SELECT COUNT(*) FROM oauth_settings WHERE id = 1')->fetchColumn();
if ($exists === 0) {
    $pdo->exec('INSERT INTO oauth_settings (id) VALUES (1)');
}

$sql = 'UPDATE oauth_settings SET 
    name = :name,
    client_id = :client_id,
    client_secret = :client_secret,
    authorization_url = :authorization_url,
    token_url = :token_url,
    user_info_url = :user_info_url,
    redirect_url = :redirect_url,
    logout_url = :logout_url,
    user_identifier_field = :user_identifier_field,
    scopes = :scopes,
    auth_style = :auth_style,
    auto_create_user = :auto_create_user,
    password_login_disabled = :password_login_disabled
    WHERE id = 1';
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':name'=>$fields['name'], ':client_id'=>$fields['client_id'], ':client_secret'=>$fields['client_secret'],
    ':authorization_url'=>$fields['authorization_url'], ':token_url'=>$fields['token_url'], ':user_info_url'=>$fields['user_info_url'],
    ':redirect_url'=>$fields['redirect_url'], ':logout_url'=>$fields['logout_url'], ':user_identifier_field'=>$fields['user_identifier_field'],
    ':scopes'=>$fields['scopes'], ':auth_style'=>$fields['auth_style'], ':auto_create_user'=>(int)$fields['auto_create_user'], ':password_login_disabled'=>(int)$fields['password_login_disabled']
]);

audit_admin_action($pdo, 'oidc.save_settings', null, $fields);
json_success(translate('success', $i18n) ?: 'ok');
