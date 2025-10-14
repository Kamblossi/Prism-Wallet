<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode([ 'success' => false, 'errorMessage' => translate('session_expired', $i18n) ]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    // Create a placeholder member named "Member"
    $householdName = 'Member';
    $stmt = $pdo->prepare('INSERT INTO household (name, user_id) VALUES (:name, :user_id) RETURNING id');
    $stmt->execute([':name' => $householdName, ':user_id' => $userId]);
    $newId = (int)$stmt->fetchColumn();
    echo json_encode([ 'success' => true, 'householdId' => $newId ]);
    exit;
}

if ($action === 'edit') {
    $memberId = $_GET['memberId'] ?? '';
    $name = isset($_GET['name']) ? validate($_GET['name']) : '';
    $email = isset($_GET['email']) ? validate($_GET['email']) : '';

    if ($memberId === '' || $name === '') {
        echo json_encode([ 'success' => false, 'errorMessage' => translate('fill_all_fields', $i18n) ]);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE household SET name = :name, email = :email WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':name' => $name, ':email' => $email, ':id' => $memberId, ':user_id' => $userId]);
    echo json_encode([ 'success' => true, 'message' => translate('member_saved', $i18n) ]);
    exit;
}

if ($action === 'delete') {
    $memberId = $_GET['memberId'] ?? '';
    if ($memberId === '' || (int)$memberId === 1) {
        echo json_encode([ 'success' => false, 'errorMessage' => translate('failed_remove_household', $i18n) ]);
        exit;
    }

    // Check if member is in use by any subscription
    $check = $pdo->prepare('SELECT COUNT(*) FROM subscriptions WHERE payer_user_id = :id AND user_id = :user_id');
    $check->execute([':id' => $memberId, ':user_id' => $userId]);
    $count = (int)$check->fetchColumn();
    if ($count > 0) {
        echo json_encode([ 'success' => false, 'errorMessage' => translate('household_in_use', $i18n) ]);
        exit;
    }

    $del = $pdo->prepare('DELETE FROM household WHERE id = :id AND user_id = :user_id');
    $del->execute([':id' => $memberId, ':user_id' => $userId]);
    echo json_encode([ 'success' => true, 'message' => translate('member_removed', $i18n) ]);
    exit;
}

echo json_encode([ 'success' => false, 'errorMessage' => translate('error', $i18n) ]);
?>
