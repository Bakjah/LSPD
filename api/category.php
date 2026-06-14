<?php
/**
 * Category API — Get threads in a category
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$categoryId = (int) ($_GET['category_id'] ?? 0);
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = min(50, max(5, (int) (getSetting('threads_per_page', 20))));
$sort       = $_GET['sort'] ?? 'latest';
$prefix     = trim($_GET['prefix'] ?? '');

if (!$categoryId) jsonResponse(['error' => 'Category ID is required'], 400);

$db = getDB();

// Verify category exists
$catStmt = $db->prepare("SELECT c.*, f.name as forum_name, f.department_id FROM categories c JOIN forums f ON c.forum_id = f.id WHERE c.id = ? AND c.is_active = 1");
$catStmt->bind_param('i', $categoryId);
$catStmt->execute();
$catResult = $catStmt->get_result();
if ($catResult->num_rows === 0) jsonResponse(['error' => 'Category not found'], 404);
$category = $catResult->fetch_assoc();
$catStmt->close();

$offset = ($page - 1) * $perPage;
$conditions = "t.category_id = ? AND t.is_deleted = 0";
$params = [$categoryId];
$types = 'i';

if ($prefix) {
    $conditions .= " AND t.prefix = ?";
    $params[] = $prefix;
    $types .= 's';
}

$orderBy = match ($sort) {
    'popular'  => "t.is_sticky DESC, t.is_pinned DESC, t.views DESC, t.last_reply_at DESC",
    'oldest'  => "t.is_sticky DESC, t.is_pinned DESC, t.created_at ASC",
    'replies' => "t.is_sticky DESC, t.is_pinned DESC, t.replies DESC",
    default   => "t.is_sticky DESC, t.is_pinned DESC, t.last_reply_at DESC",
};

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM threads t WHERE $conditions");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Get threads
$threadStmt = $db->prepare("
    SELECT t.*, u.username, u.avatar, u.rank_id,
           r.name as rank_name, r.color as rank_color, r.badge as rank_badge,
           d.name as dept_name, d.code as dept_code,
           (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id AND p.is_deleted = 0) as actual_replies,
           (SELECT MAX(p.created_at) FROM posts p WHERE p.thread_id = t.id AND p.is_deleted = 0) as last_post_time,
           (SELECT u2.username FROM posts p2 JOIN users u2 ON p2.user_id = u2.id WHERE p2.thread_id = t.id AND p2.is_deleted = 0 ORDER BY p2.created_at DESC LIMIT 1) as last_poster
    FROM threads t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN roles r ON u.rank_id = r.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE $conditions
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';
$threadStmt->bind_param($types, ...$params);
$threadStmt->execute();
$threadResult = $threadStmt->get_result();

$threads = [];
while ($t = $threadResult->fetch_assoc()) {
    $threads[] = [
        'id'           => (int) $t['id'],
        'uuid'         => $t['uuid'],
        'title'        => $t['title'],
        'slug'         => $t['slug'],
        'prefix'       => $t['prefix'],
        'views'        => (int) $t['views'],
        'replies'      => (int) ($t['actual_replies'] - 1),
        'likes'        => (int) $t['likes'],
        'is_pinned'    => (bool) $t['is_pinned'],
        'is_sticky'    => (bool) $t['is_sticky'],
        'is_locked'    => (bool) $t['is_locked'],
        'is_archived'  => (bool) $t['is_archived'],
        'created_at'   => $t['created_at'],
        'last_reply_at'=> $t['last_post_time'] ?? $t['last_reply_at'],
        'author'       => [
            'id'         => (int) $t['user_id'],
            'username'   => $t['username'],
            'avatar'     => $t['avatar'],
            'rank'       => $t['rank_name'],
            'rank_color' => $t['rank_color'],
            'rank_badge' => $t['rank_badge'],
            'dept'       => $t['dept_name'],
            'dept_code'  => $t['dept_code'],
        ],
        'last_poster'  => $t['last_poster'],
    ];
}
$threadStmt->close();

jsonResponse([
    'category'   => [
        'id'          => (int) $category['id'],
        'name'        => $category['name'],
        'description' => $category['description'],
        'forum_name'  => $category['forum_name'],
    ],
    'threads'    => $threads,
    'pagination' => [
        'page'       => $page,
        'per_page'   => $perPage,
        'total'      => $total,
        'total_pages'=> ceil($total / $perPage),
    ],
]);
