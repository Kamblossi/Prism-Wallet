<?php
require_once __DIR__ . '/../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$enabled = !empty($data['enabled']) ? 1 : 0;
$type = trim((string)($data['type'] ?? ''));
$apiKey = trim((string)($data['api_key'] ?? ''));
$model = trim((string)($data['model'] ?? ''));
$url = trim((string)($data['url'] ?? ''));

$sql = 'UPDATE admin SET ai_enabled = :en, ai_type = :t, ai_api_key = :k, ai_model = :m, ai_url = :u WHERE id = (SELECT id FROM admin ORDER BY id ASC LIMIT 1)';
$stmt = $pdo->prepare($sql);
$stmt->execute([':en'=>$enabled, ':t'=>$type, ':k'=>$apiKey, ':m'=>$model, ':u'=>$url]);

audit_admin_action($pdo, 'ai.save_settings', null, ['type'=>$type, 'enabled'=>$enabled]);
json_success(translate('success', $i18n) ?: 'ok');

