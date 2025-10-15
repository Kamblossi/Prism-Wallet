<?php
<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/endpoint_helpers.php';

// Legacy endpoint is now admin-only and writes to global admin settings
if (!current_user_is_admin($pdo)) {
    json_error(translate('error', $i18n) ?: 'Forbidden', 403, 'forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(translate('invalid_request_method', $i18n) ?: 'Invalid request', 405, 'invalid_method');
}

$newApiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
$provider = isset($_POST['provider']) ? (int)$_POST['provider'] : 0;

$stmt = $pdo->prepare('UPDATE admin SET fixer_api_key = :k, fixer_provider = :p WHERE id = (SELECT id FROM admin ORDER BY id ASC LIMIT 1)');
$stmt->execute([':k'=>$newApiKey, ':p'=>$provider]);

audit_admin_action($pdo, 'fixer.save_legacy', null, ['provider'=>$provider, 'has_key'=>$newApiKey!=='' ]);
json_success(translate('success', $i18n) ?: 'ok');
?>
