<?php
/**
 * Topics API
 * GET /api/topics - List topics
 * POST /api/topics - Create topic (auth required)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/logging.php';

// Apply CORS headers
applyCORS();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $db = getDB();

    // Check if topics table exists
    $stmt = $db->query("SHOW TABLES LIKE 'topics'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Forum tables not found. Run database/setup.php first.']);
        exit;
    }
    $stmt->closeCursor();

    // GET - List topics
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $topicId = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        // Get single topic with posts
        if ($topicId) {
            // Get topic
            $stmt = $db->prepare("
                SELECT t.*, u.username, u.avatar, u.uuid as user_uuid,
                       c.name as category_name, c.slug as category_slug,
                       f.name as forum_name, f.slug as forum_slug,
                       d.name as department_name, d.code as department_code, d.color as department_color
                FROM topics t
                JOIN users u ON t.user_id = u.id
                JOIN categories c ON t.category_id = c.id
                JOIN forums f ON c.forum_id = f.id
                LEFT JOIN departments d ON f.department_id = d.id
                WHERE t.id = ? AND t.is_deleted = 0
            ");
            $stmt->execute([$topicId]);
            $topic = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$topic) {
                http_response_code(404);
                echo json_encode(['error' => 'Topic not found']);
                exit;
            }

            // Increment views
            $stmt = $db->prepare("UPDATE topics SET views = views + 1 WHERE id = ?");
            $stmt->execute([$topicId]);
            $stmt->closeCursor();

            // Get posts
            $postStmt = $db->prepare("
                SELECT p.*, u.username, u.avatar, u.uuid as user_uuid,
                       (SELECT username FROM users WHERE id = p.last_edited_by) as edited_by_username
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.topic_id = ? AND p.is_deleted = 0
                ORDER BY p.created_at ASC
            ");
            $postStmt->execute([$topicId]);
            $posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);
            $postStmt->closeCursor();

            // Get tags
            $tagStmt = $db->prepare("
                SELECT tt.name, tt.slug, tt.color
                FROM topic_tag_map ttm
                JOIN topic_tags tt ON ttm.tag_id = tt.id
                WHERE ttm.topic_id = ?
            ");
            $tagStmt->execute([$topicId]);
            $tags = $tagStmt->fetchAll(PDO::FETCH_ASSOC);
            $tagStmt->closeCursor();

            // Get last reply info
            $lastReplyUser = null;
            if ($topic['last_reply_by']) {
                $stmt = $db->prepare("SELECT username, avatar, uuid FROM users WHERE id = ?");
                $stmt->execute([$topic['last_reply_by']]);
                $lastReplyUser = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
            }

            echo json_encode([
                'success' => true,
                'topic' => [
                    'id' => (int) $topic['id'],
                    'uuid' => $topic['uuid'],
                    'title' => $topic['title'],
                    'slug' => $topic['slug'],
                    'content' => $topic['content'],
                    'views' => (int) $topic['views'] + 1,
                    'replies' => (int) $topic['replies'],
                    'likes' => (int) $topic['likes'],
                    'is_pinned' => (bool) $topic['is_pinned'],
                    'is_locked' => (bool) $topic['is_locked'],
                    'is_sticky' => (bool) $topic['is_sticky'],
                    'is_archived' => (bool) $topic['is_archived'],
                    'prefix' => $topic['prefix'],
                    'created_at' => $topic['created_at'],
                    'updated_at' => $topic['updated_at'],
                    'author' => [
                        'uuid' => $topic['user_uuid'],
                        'username' => $topic['username'],
                        'avatar' => $topic['avatar'],
                    ],
                    'category' => [
                        'id' => (int) $topic['category_id'],
                        'name' => $topic['category_name'],
                        'slug' => $topic['category_slug'],
                    ],
                    'forum' => [
                        'name' => $topic['forum_name'],
                        'slug' => $topic['forum_slug'],
                        'department' => $topic['department_code'],
                    ],
                    'tags' => array_map(fn($t) => [
                        'name' => $t['name'],
                        'slug' => $t['slug'],
                        'color' => $t['color'],
                    ], $tags),
                    'last_reply' => $lastReplyUser ? [
                        'username' => $lastReplyUser['username'],
                        'avatar' => $lastReplyUser['avatar'],
                        'at' => $topic['last_reply_at'],
                    ] : null,
                    'posts' => array_map(fn($p) => [
                        'id' => (int) $p['id'],
                        'uuid' => $p['uuid'],
                        'content' => $p['content'],
                        'likes' => (int) $p['likes'],
                        'is_first_post' => (bool) $p['is_first_post'],
                        'edit_count' => (int) $p['edit_count'],
                        'last_edited_at' => $p['last_edited_at'],
                        'edited_by' => $p['edited_by_username'],
                        'created_at' => $p['created_at'],
                        'author' => [
                            'uuid' => $p['user_uuid'],
                            'username' => $p['username'],
                            'avatar' => $p['avatar'],
                        ],
                    ], $posts),
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // List topics by category
        $where = ['t.is_deleted = 0'];
        $params = [];

        if ($categoryId) {
            $where[] = 't.category_id = ?';
            $params[] = $categoryId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Count total
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM topics t {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $countStmt->closeCursor();

        // Get topics
        $sql = "
            SELECT t.*, u.username, u.avatar, u.uuid as user_uuid,
                   c.name as category_name, c.slug as category_slug,
                   (SELECT username FROM users WHERE id = t.last_reply_by) as last_reply_username
            FROM topics t
            JOIN users u ON t.user_id = u.id
            JOIN categories c ON t.category_id = c.id
            {$whereClause}
            ORDER BY t.is_pinned DESC, t.is_sticky DESC, t.last_reply_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $formattedTopics = array_map(fn($t) => [
            'id' => (int) $t['id'],
            'uuid' => $t['uuid'],
            'title' => $t['title'],
            'slug' => $t['slug'],
            'views' => (int) $t['views'],
            'replies' => (int) $t['replies'],
            'likes' => (int) $t['likes'],
            'is_pinned' => (bool) $t['is_pinned'],
            'is_locked' => (bool) $t['is_locked'],
            'is_sticky' => (bool) $t['is_sticky'],
            'prefix' => $t['prefix'],
            'created_at' => $t['created_at'],
            'last_reply_at' => $t['last_reply_at'],
            'author' => [
                'uuid' => $t['user_uuid'],
                'username' => $t['username'],
                'avatar' => $t['avatar'],
            ],
            'category' => [
                'name' => $t['category_name'],
                'slug' => $t['category_slug'],
            ],
            'last_reply_by' => $t['last_reply_username'],
        ], $topics);

        echo json_encode([
            'success' => true,
            'topics' => $formattedTopics,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // POST - Create topic (requires auth)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Authenticate
        $token = getJWTFromHeader();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }

        $payload = verifyJWT($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        $user = getUserWithRoles($payload['sub']);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Get input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }

        // Validate
        $errors = [];
        if (empty($input['category_id'])) $errors[] = 'category_id is required';
        if (empty($input['title'])) $errors[] = 'title is required';
        if (empty($input['content'])) $errors[] = 'content is required';

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
            exit;
        }

        $categoryId = (int) $input['category_id'];
        $title = sanitize(substr($input['title'], 0, 300));
        $content = $input['content'];
        $prefix = isset($input['prefix']) ? sanitize(substr($input['prefix'], 0, 50)) : null;
        $tags = $input['tags'] ?? [];

        // Generate slug
        $slug = slugify($title) . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $uuid = generateUUID();

        // Check category exists
        $stmt = $db->prepare("SELECT id FROM categories WHERE id = ? AND is_active = 1");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Category not found']);
            exit;
        }
        $stmt->closeCursor();

        // Create topic
        $stmt = $db->prepare("
            INSERT INTO topics (uuid, category_id, user_id, title, slug, content, prefix, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$uuid, $categoryId, $user['id'], $title, $slug, $content, $prefix]);
        $topicId = $db->lastInsertId();
        $stmt->closeCursor();

        // Create first post (same content as topic)
        $postUuid = generateUUID();
        $stmt = $db->prepare("
            INSERT INTO posts (uuid, topic_id, user_id, content, is_first_post, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$postUuid, $topicId, $user['id'], $content]);
        $stmt->closeCursor();

        // Add tags
        if (!empty($tags)) {
            $tagStmt = $db->prepare("SELECT id FROM topic_tags WHERE slug = ?");
            $insertTagStmt = $db->prepare("INSERT INTO topic_tag_map (topic_id, tag_id) VALUES (?, ?)");
            foreach ($tags as $tagSlug) {
                $tagStmt->execute([sanitize($tagSlug)]);
                $tag = $tagStmt->fetch(PDO::FETCH_ASSOC);
                if ($tag) {
                    $insertTagStmt->execute([$topicId, $tag['id']]);
                }
            }
            $tagStmt->closeCursor();
            $insertTagStmt->closeCursor();
        }

        // Update user post count
        $stmt = $db->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        $stmt->closeCursor();

        // Log activity
        logActivity($user['id'], 'topic_created', 'topic', $topicId, "Created topic: {$title}");

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Topic created successfully',
            'topic' => [
                'id' => (int) $topicId,
                'uuid' => $uuid,
                'title' => $title,
                'slug' => $slug,
            ],
        ]);
        exit;
    }

    // Method not allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'An error occurred',
    ]);
}