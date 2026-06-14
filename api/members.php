<?php
/**
 * Members List API
 */
require_once __DIR__ . '/config.php';
startSession();

header('Content-Type: application/json');

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');
$dept = (int) ($_GET['dept'] ?? 0);
$role = trim($_GET['role'] ?? '');
$sort = $_GET['sort'] ?? 'username';

$db = getDB();
$params = [];
$whereParts = ['u.is_banned = 0'];

if ($search !== '') {
    $whereParts[] = 'u.username LIKE ?';
    $params[] = '%' . $db->real_escape_string($search) . '%';
}

if ($dept > 0) {
    $whereParts[] = 'ur.department_id = ?';
    $params[] = $dept;
}

$whereClause = implode(' AND ', $whereParts);

$orderBy = match($sort) {
    'joined' => 'u.created_at DESC',
    'posts' => 'post_count DESC',
    'threads' => 'thread_count DESC',
    default => 'u.username ASC',
};

$countSql = "SELECT COUNT(DISTINCT u.id) as total
FROM users u
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN roles r ON ur.role_id = r.id
WHERE {$whereClause}";

$memberSql = "SELECT u.id, u.uuid, u.username, u.avatar, u.bio, u.department,
  u.thread_count, u.post_count, u.created_at as joined_at,
  GROUP_CONCAT(DISTINCT r.name ORDER BY r.rank DESC SEPARATOR ', ') as roles,
  MAX(r.rank) as max_rank,
  MAX(CASE WHEN r.name = 'Administrator' THEN 1 ELSE 0 END) as is_admin,
  MAX(CASE WHEN r.name LIKE '%Moderator%' THEN 1 ELSE 0 END) as is_mod,
  MAX(CASE WHEN r.name LIKE '%Chief%' OR r.name LIKE '%Director%' OR r.name LIKE '%Sheriff%' THEN 1 ELSE 0 END) as is_chief,
  MAX(CASE WHEN r.name LIKE '%Officer%' OR r.name LIKE '%Deputy%' OR r.name LIKE '%Firefighter%' OR r.name LIKE '%Reporter%' THEN 1 ELSE 0 END) as is_officer,
  MAX(CASE WHEN r.name LIKE '%Cadet%' OR r.name LIKE '%Recruit%' THEN 1 ELSE 0 END) as is_cadet,
  d.name as department
FROM users u
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN roles r ON ur.role_id = r.id
LEFT JOIN departments d ON ur.department_id = d.id
WHERE {$whereClause}
GROUP BY u.id
ORDER BY {$orderBy}
LIMIT {$perPage} OFFSET {$offset}";

$countStmt = $db->prepare($countSql);
if (count($params)) {
    $types = str_repeat('s', count($params));
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$memberStmt = $db->prepare($memberSql);
if (count($params)) {
    $types = str_repeat('s', count($params));
    $memberStmt->bind_param($types, ...$params);
}
$memberStmt->execute();
$members = $memberStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$memberStmt->close();

jsonResponse([
    'members' => $members,
    'total' => $total,
    'per_page' => $perPage,
    'current_page' => $page,
    'total_pages' => ceil($total / $perPage),
]);
