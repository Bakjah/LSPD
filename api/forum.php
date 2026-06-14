<?php
/**
 * Forum API — Get all forums and categories
 */
require_once __DIR__ . '/helpers/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$db = getDB();

// Get all active forums with categories
$stmt = $db->query("
    SELECT f.*, d.name as dept_name, d.code as dept_code, d.color as dept_color, d.icon as dept_icon
    FROM forums f
    LEFT JOIN departments d ON f.department_id = d.id
    WHERE f.is_active = 1
    ORDER BY f.sort_order ASC
");

$forums = [];
while ($forum = $stmt->fetch()) {
    $forumId = $forum['id'];

    // Get categories for this forum
    $catStmt = $db->prepare("
        SELECT c.*,
            (SELECT COUNT(*) FROM topics t WHERE t.category_id = c.id AND t.is_deleted = 0) as thread_count,
            (SELECT COUNT(*) FROM posts p
                JOIN topics t ON p.topic_id = t.id
                WHERE t.category_id = c.id AND p.is_deleted = 0) as post_count,
            (SELECT MAX(t.last_reply_at) FROM topics t WHERE t.category_id = c.id AND t.is_deleted = 0) as last_activity
        FROM categories c
        WHERE c.forum_id = ? AND c.is_active = 1
        ORDER BY c.sort_order ASC
    ");
    $catStmt->execute([$forumId]);
    $catResult = $catStmt->fetchAll();

    $categories = [];
    foreach ($catResult as $cat) {
        $categories[] = [
            'id'           => (int) $cat['id'],
            'name'         => $cat['name'],
            'description'  => $cat['description'],
            'icon'         => $cat['icon'],
            'color'        => $cat['color'],
            'thread_count' => (int) $cat['thread_count'],
            'post_count'   => (int) $cat['post_count'],
            'last_activity'=> $cat['last_activity'],
        ];
    }
    $catStmt->closeCursor();

    $forums[] = [
        'id'         => (int) $forum['id'],
        'name'       => $forum['name'],
        'description'=> $forum['description'],
        'icon'       => $forum['icon'],
        'color'      => $forum['color'],
        'department' => $forum['dept_name'],
        'dept_code'  => $forum['dept_code'],
        'dept_color' => $forum['dept_color'],
        'dept_icon'  => $forum['dept_icon'],
        'categories' => $categories,
    ];
}

jsonResponse(['forums' => $forums]);
