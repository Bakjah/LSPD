<?php
/**
 * Forums API
 * GET /api/forums
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/logging.php';

// Apply CORS headers
applyCORS();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();

    // Check if forums table exists
    $stmt = $db->query("SHOW TABLES LIKE 'forums'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Forum tables not found. Run database/setup.php first.']);
        exit;
    }

    $department = isset($_GET['department']) ? sanitize($_GET['department']) : null;
    $forumId = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;

    // Get single forum with categories
    if ($forumId) {
        $stmt = $db->prepare("
            SELECT f.*, d.name as department_name, d.code as department_code, d.color as department_color
            FROM forums f
            LEFT JOIN departments d ON f.department_id = d.id
            WHERE f.id = ? AND f.is_active = 1
        ");
        $stmt->execute([$forumId]);
        $forum = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$forum) {
            http_response_code(404);
            echo json_encode(['error' => 'Forum not found']);
            exit;
        }

        // Get categories
        $stmt = $db->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM topics t WHERE t.category_id = c.id AND t.is_deleted = 0) as topic_count,
                   (SELECT COUNT(*) FROM topics t JOIN posts p ON t.id = p.topic_id WHERE t.category_id = c.id AND t.is_deleted = 0) as post_count
            FROM categories c
            WHERE c.forum_id = ? AND c.is_active = 1
            ORDER BY c.sort_order ASC
        ");
        $stmt->execute([$forumId]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        echo json_encode([
            'success' => true,
            'forum' => [
                'id' => (int) $forum['id'],
                'uuid' => $forum['uuid'],
                'name' => $forum['name'],
                'slug' => $forum['slug'],
                'description' => $forum['description'],
                'icon' => $forum['icon'],
                'color' => $forum['color'],
                'department' => $forum['department_id'] ? [
                    'id' => (int) $forum['department_id'],
                    'name' => $forum['department_name'],
                    'code' => $forum['department_code'],
                    'color' => $forum['department_color'],
                ] : null,
                'categories' => array_map(fn($c) => [
                    'id' => (int) $c['id'],
                    'uuid' => $c['uuid'],
                    'name' => $c['name'],
                    'slug' => $c['slug'],
                    'description' => $c['description'],
                    'icon' => $c['icon'],
                    'color' => $c['color'],
                    'topic_count' => (int) $c['topic_count'],
                    'post_count' => (int) $c['post_count'],
                ], $categories),
            ],
        ]);
        exit;
    }

    // Get all forums grouped by department
    $where = ['f.is_active = 1'];
    $params = [];

    if ($department) {
        $where[] = 'd.code = ?';
        $params[] = strtoupper($department);
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $sql = "
        SELECT f.*, d.name as department_name, d.code as department_code, d.color as department_color,
               (SELECT COUNT(*) FROM categories c WHERE c.forum_id = f.id AND c.is_active = 1) as category_count
        FROM forums f
        LEFT JOIN departments d ON f.department_id = d.id
        {$whereClause}
        ORDER BY d.sort_order ASC, f.sort_order ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $forums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Group by department
    $grouped = [];
    foreach ($forums as $forum) {
        $deptCode = $forum['department_code'] ?? 'COMMUNITY';
        $deptName = $forum['department_name'] ?? 'Community';
        $deptColor = $forum['department_color'] ?? '#9925EB';

        if (!isset($grouped[$deptCode])) {
            $grouped[$deptCode] = [
                'department' => [
                    'code' => $deptCode,
                    'name' => $deptName,
                    'color' => $deptColor,
                ],
                'forums' => [],
            ];
        }

        // Get categories for each forum
        $stmt = $db->prepare("
            SELECT c.id, c.uuid, c.name, c.slug, c.description, c.icon, c.color,
                   (SELECT COUNT(*) FROM topics t WHERE t.category_id = c.id AND t.is_deleted = 0) as topic_count,
                   (SELECT COUNT(*) FROM topics t JOIN posts p ON t.id = p.topic_id WHERE t.category_id = c.id AND t.is_deleted = 0) as post_count
            FROM categories c
            WHERE c.forum_id = ? AND c.is_active = 1
            ORDER BY c.sort_order ASC
        ");
        $stmt->execute([$forum['id']]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $grouped[$deptCode]['forums'][] = [
            'id' => (int) $forum['id'],
            'uuid' => $forum['uuid'],
            'name' => $forum['name'],
            'slug' => $forum['slug'],
            'description' => $forum['description'],
            'icon' => $forum['icon'],
            'color' => $forum['color'],
            'category_count' => (int) $forum['category_count'],
            'categories' => array_map(fn($c) => [
                'id' => (int) $c['id'],
                'uuid' => $c['uuid'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'description' => $c['description'],
                'icon' => $c['icon'],
                'color' => $c['color'],
                'topic_count' => (int) $c['topic_count'],
                'post_count' => (int) $c['post_count'],
            ], $categories),
        ];
    }

    echo json_encode([
        'success' => true,
        'forums' => array_values($grouped),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'An error occurred',
    ]);
}