<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$userId = (int)($data['user_id'] ?? 0);
$makeAdmin = isset($data['make_admin']) ? (bool)$data['make_admin'] : null;
if ($userId <= 0 || $makeAdmin === null) { json_error('Missing user_id or make_admin', 400, 'bad_request'); }

// Disallow changing own admin status resulting in zero admins
$actor = get_current_user_id($pdo);

$pdo->beginTransaction();
try {
    if ($makeAdmin) {
        $stmt = $pdo->prepare('UPDATE users SET is_admin = TRUE WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    } else {
        // Ensure at least one admin remains
        $cnt = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = TRUE')->fetchColumn();
        if ($cnt <= 1) { throw new RuntimeException('Cannot demote the last admin'); }
        $stmt = $pdo->prepare('UPDATE users SET is_admin = FALSE WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error($e->getMessage(), 400, 'update_failed');
}

audit_admin_action($pdo, $makeAdmin ? 'user.promote_admin' : 'user.demote_admin', $userId, ['actor' => $actor]);
json_success(translate('success', $i18n) ?: 'updated', ['user_id' => $userId, 'is_admin' => $makeAdmin]);
