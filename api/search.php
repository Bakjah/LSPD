<?php
/**
 * Search API
 */
require_once __DIR__ . '/config.php';
startSession();

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';
$category = (int) ($_GET['category'] ?? 0);
$sort = $_GET['sort'] ?? 'relevance';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if (empty($query) || strlen($query) < 2) {
    jsonResponse(['error' => 'Search query must be at least 2 characters.'], 400);
}

$db = getDB();
$params = [];
$whereParts = [];

$searchTerm = '%' . $db->real_escape_string($query) . '%';

if ($type === 'threads' || $type === 'all') {
    $whereParts[] = "(t.title LIKE ? OR t.content LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($type === 'posts' || $type === 'all') {
    $whereParts[] = "p.content LIKE ?";
    $params[] = $searchTerm;
}

$whereClause = implode(' OR ', $whereParts);
if ($category > 0) {
    $whereClause .= ' AND t.category_id = ?';
    $params[] = $category;
}

$whereClause .= ' AND t.is_deleted = 0';

$orderBy = match($sort) {
    'newest' => 't.created_at DESC',
    'oldest' => 't.created_at ASC',
    default => 't.view_count DESC',
};

$countSql = "SELECT COUNT(DISTINCT t.id) as total
FROM threads t
LEFT JOIN posts p ON p.thread_id = t.id
WHERE ($whereClause)";

$searchSql = "SELECT DISTINCT t.id as thread_id, t.title, t.slug as thread_slug, t.prefix,
  t.view_count, t.reply_count, t.is_pinned, t.is_locked, t.created_at,
  u.username as author, c.name as category_name, c.id as category_id,
  LEFT(COALESCE(p.content, t.content), 200) as excerpt
FROM threads t
LEFT JOIN posts p ON p.thread_id = t.id AND p.is_first = 1
LEFT JOIN users u ON t.user_id = u.id
LEFT JOIN categories c ON t.category_id = c.id
WHERE ($whereClause)
ORDER BY t.is_pinned DESC, {$orderBy}
LIMIT {$perPage} OFFSET {$offset}";

$startTime = microtime(true);

$countStmt = $db->prepare($countSql);
if (count($params)) {
    $types = str_repeat('s', count($params));
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$total = $countResult->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$searchStmt = $db->prepare($searchSql);
if (count($params)) {
    $types = str_repeat('s', count($params));
    $searchStmt->bind_param($types, ...$params);
}
$searchStmt->execute();
$results = $searchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$searchStmt->close();

$queryTime = round((microtime(true) - $startTime) * 1000);

jsonResponse([
    'results' => $results,
    'total' => $total,
    'per_page' => $perPage,
    'current_page' => $page,
    'total_pages' => ceil($total / $perPage),
    'query' => $query,
    'query_time' => $queryTime,
]);
