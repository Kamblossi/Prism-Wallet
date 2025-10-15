<?php
require_once __DIR__ . '/../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$apiKey = trim((string)($data['api_key'] ?? ''));
$provider = (int)($data['provider'] ?? 0);

$stmt = $pdo->prepare('UPDATE admin SET fixer_api_key = :k, fixer_provider = :p WHERE id = (SELECT id FROM admin ORDER BY id ASC LIMIT 1)');
$stmt->execute([':k'=>$apiKey, ':p'=>$provider]);

audit_admin_action($pdo, 'fixer.save', null, ['provider'=>$provider, 'has_key'=> $apiKey !== '' ]);
json_success(translate('success', $i18n) ?: 'ok');

