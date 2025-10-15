<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

require_admin($pdo);
require_csrf();
rate_limit($pdo, 'admin:delete_user:' . (get_current_user_id($pdo) ?? 0), 30);

$data = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$userId = (int)($data['user_id'] ?? 0);
$transferTo = isset($data['transfer_to']) ? (int)$data['transfer_to'] : null;
if ($userId <= 0) { json_error('Missing user_id', 400, 'bad_request'); }

// Prevent deleting the last admin
$isAdmin = (int)$pdo->prepare('SELECT COUNT(*) FROM users WHERE id = :id AND is_admin = TRUE')->execute([':id'=>$userId]) ?: 0;
$stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = :id');
$stmt->execute([':id'=>$userId]);
$isAdminFlag = (bool)$stmt->fetchColumn();
if ($isAdminFlag) {
    $cntAdmins = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = TRUE')->fetchColumn();
    if ($cntAdmins <= 1) { json_error('Cannot delete the last admin', 400, 'last_admin'); }
}

$pdo->beginTransaction();
try {
    $moved = ['subscriptions'=>0,'payment_methods'=>0,'categories'=>0,'currencies'=>0];
    if ($transferTo && $transferTo !== $userId) {
        foreach (['subscriptions','payment_methods','categories','currencies'] as $tbl) {
            $q = $pdo->prepare("UPDATE $tbl SET user_id = :to WHERE user_id = :from");
            $q->execute([':to'=>$transferTo, ':from'=>$userId]);
            $moved[$tbl] = $q->rowCount();
        }
    }
    // Finally, delete the user; cascades will handle dependent rows with FK ON DELETE CASCADE
    $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $del->execute([':id'=>$userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error('Delete failed: ' . $e->getMessage(), 400, 'delete_failed');
}

audit_admin_action($pdo, 'user.delete', $userId, ['transfer_to'=>$transferTo, 'moved'=>$moved]);
json_success(translate('success', $i18n) ?: 'deleted', ['user_id'=>$userId, 'moved'=>$moved]);
