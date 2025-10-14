<?php
<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'errorMessage' => translate('session_expired', $i18n)]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    $stmt = $pdo->prepare('SELECT MAX("order") as maxOrder FROM categories WHERE user_id = :userId');
    $stmt->execute([':userId' => $userId]);
    $maxOrder = (int)($stmt->fetch(PDO::FETCH_ASSOC)['maxOrder'] ?? 0);
    $order = $maxOrder + 1;
    $categoryName = 'Category';
    $stmtInsert = $pdo->prepare('INSERT INTO categories ("name", "order", "user_id") VALUES (:name, :order, :userId) RETURNING id');
    $stmtInsert->execute([':name' => $categoryName, ':order' => $order, ':userId' => $userId]);
    $categoryId = (int)$stmtInsert->fetchColumn();
    echo json_encode(['success' => true, 'categoryId' => $categoryId]);
    exit;
}

if ($action === 'edit') {
    $categoryId = $_GET['categoryId'] ?? '';
    $name = isset($_GET['name']) ? validate($_GET['name']) : '';
    if ($categoryId === '' || $name === '') {
        echo json_encode(['success' => false, 'errorMessage' => translate('fill_all_fields', $i18n)]);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE categories SET name = :name WHERE id = :id AND user_id = :userId');
    $stmt->execute([':name' => $name, ':id' => $categoryId, ':userId' => $userId]);
    echo json_encode(['success' => true, 'message' => translate('category_saved', $i18n)]);
    exit;
}

if ($action === 'delete') {
    $categoryId = $_GET['categoryId'] ?? '';
    if ($categoryId === '' || (int)$categoryId === 1) {
        echo json_encode(['success' => false, 'errorMessage' => translate('failed_remove_category', $i18n)]);
        exit;
    }
    $check = $pdo->prepare('SELECT COUNT(*) FROM subscriptions WHERE category_id = :cid AND user_id = :uid');
    $check->execute([':cid' => $categoryId, ':uid' => $userId]);
    $count = (int)$check->fetchColumn();
    if ($count > 0) {
        echo json_encode(['success' => false, 'errorMessage' => translate('category_in_use', $i18n)]);
        exit;
    }
    $del = $pdo->prepare('DELETE FROM categories WHERE id = :id AND user_id = :uid');
    $del->execute([':id' => $categoryId, ':uid' => $userId]);
    echo json_encode(['success' => true, 'message' => translate('category_removed', $i18n)]);
    exit;
}

echo json_encode(['success' => false, 'errorMessage' => translate('error', $i18n)]);
?>
