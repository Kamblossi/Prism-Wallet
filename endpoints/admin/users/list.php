<?php
require_once __DIR__ . '/../../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../../includes/endpoint_helpers.php';

// Admin only
require_admin($pdo);

// Parse input
$input = json_decode(file_get_contents('php://input') ?: 'null', true) ?? [];
$page = max(1, (int)($input['page'] ?? 1));
$perPage = (int)($input['per_page'] ?? 20);
if ($perPage < 1 || $perPage > 100) { $perPage = 20; }
$q = trim((string)($input['q'] ?? ''));
$filter = $input['filter'] ?? [];

// Build where clause
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(lower(username) LIKE :q OR lower(email) LIKE :q)';
    $params[':q'] = '%' . strtolower($q) . '%';
}
if (isset($filter['is_admin'])) { $where[] = 'is_admin = :is_admin'; $params[':is_admin'] = (bool)$filter['is_admin']; }
if (isset($filter['is_verified'])) { $where[] = 'is_verified = :is_verified'; $params[':is_verified'] = (int)$filter['is_verified']; }
if (isset($filter['is_active'])) { $where[] = 'is_active = :is_active'; $params[':is_active'] = (bool)$filter['is_active']; }
if (empty($filter['include_deleted'])) { $where[] = 'deleted_at IS NULL'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$offset = ($page - 1) * $perPage;

// Fetch page with derived stats
$sql = "SELECT u.id, u.username, u.email, u.is_admin, u.is_verified, u.is_active,
               u.created_at, u.last_login,
               COALESCE((SELECT COUNT(*) FROM subscriptions s WHERE s.user_id = u.id), 0) AS subscription_count
        FROM users u
        $whereSql
        ORDER BY u.id ASC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

json_success(translate('success', $i18n) ?: 'ok', [
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'items' => $rows,
]);
