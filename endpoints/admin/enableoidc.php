<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(translate('error', $i18n) ?: 'Invalid request', 405, 'invalid_method');
}

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$enabled = !empty($data['oidcEnabled']) ? 1 : 0;

// Ensure row id=1 exists in oauth_settings
$count = (int)$pdo->query('SELECT COUNT(*) FROM oauth_settings WHERE id = 1')->fetchColumn();
if ($count === 0) {
    $pdo->exec("INSERT INTO oauth_settings (id, oidc_oauth_enabled) VALUES (1, 0)");
}

$stmt = $pdo->prepare('UPDATE oauth_settings SET oidc_oauth_enabled = :en WHERE id = 1');
$stmt->execute([':en' => $enabled]);

audit_admin_action($pdo, $enabled ? 'oidc.enable' : 'oidc.disable', null);
json_success(translate('success', $i18n) ?: 'ok', ['enabled' => (bool)$enabled]);
