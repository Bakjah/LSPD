<?php
/**
 * Posts API
 * POST /api/posts - Create reply
 * PUT /api/posts - Edit post
 * DELETE /api/posts - Delete post
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

    // Check if posts table exists
    $stmt = $db->query("SHOW TABLES LIKE 'posts'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Forum tables not found. Run database/setup.php first.']);
        exit;
    }
    $stmt->closeCursor();

    // GET - Get single post
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $postId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if (!$postId) {
            http_response_code(400);
            echo json_encode(['error' => 'Post ID required']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT p.*, u.username, u.avatar, u.uuid as user_uuid,
                   t.title as topic_title, t.slug as topic_slug
            FROM posts p
            JOIN users u ON p.user_id = u.id
            JOIN topics t ON p.topic_id = t.id
            WHERE p.id = ? AND p.is_deleted = 0
        ");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'post' => [
                'id' => (int) $post['id'],
                'uuid' => $post['uuid'],
                'content' => $post['content'],
                'likes' => (int) $post['likes'],
                'is_first_post' => (bool) $post['is_first_post'],
                'edit_count' => (int) $post['edit_count'],
                'last_edited_at' => $post['last_edited_at'],
                'created_at' => $post['created_at'],
                'author' => [
                    'uuid' => $post['user_uuid'],
                    'username' => $post['username'],
                    'avatar' => $post['avatar'],
                ],
                'topic' => [
                    'title' => $post['topic_title'],
                    'slug' => $post['topic_slug'],
                ],
            ],
        ]);
        exit;
    }

    // Auth helper
    $authUser = function() use ($db) {
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

        return $user;
    };

    // POST - Create reply
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }

        $errors = [];
        if (empty($input['topic_id'])) $errors[] = 'topic_id is required';
        if (empty($input['content'])) $errors[] = 'content is required';

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
            exit;
        }

        $topicId = (int) $input['topic_id'];
        $content = $input['content'];

        // Check topic exists and is not locked
        $stmt = $db->prepare("SELECT id, is_locked, is_archived, user_id FROM topics WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$topicId]);
        $topic = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$topic) {
            http_response_code(404);
            echo json_encode(['error' => 'Topic not found']);
            exit;
        }

        if ($topic['is_locked'] || $topic['is_archived']) {
            http_response_code(403);
            echo json_encode(['error' => 'Topic is locked or archived']);
            exit;
        }

        // Create post
        $uuid = generateUUID();
        $stmt = $db->prepare("
            INSERT INTO posts (uuid, topic_id, user_id, content, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$uuid, $topicId, $user['id'], $content]);
        $postId = $db->lastInsertId();
        $stmt->closeCursor();

        // Update topic reply count and last reply
        $stmt = $db->prepare("
            UPDATE topics
            SET replies = replies + 1, last_reply_at = NOW(), last_reply_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$user['id'], $topicId]);
        $stmt->closeCursor();

        // Log activity
        logActivity($user['id'], 'post_created', 'post', $postId, "Replied to topic: {$topicId}");

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Reply posted successfully',
            'post' => [
                'id' => (int) $postId,
                'uuid' => $uuid,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'author' => [
                    'uuid' => $user['uuid'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                ],
            ],
        ]);
        exit;
    }

    // PUT - Edit post
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['post_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'post_id is required']);
            exit;
        }

        $postId = (int) $input['post_id'];
        $content = $input['content'];

        if (empty($content)) {
            http_response_code(400);
            echo json_encode(['error' => 'content is required']);
            exit;
        }

        // Get post
        $stmt = $db->prepare("SELECT * FROM posts WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
            exit;
        }

        // Check ownership or admin
        $isAdmin = $user['is_admin'] ?? false;
        $isModerator = hasPermission($user['id'], 'moderate_forum');

        if ($post['user_id'] !== $user['id'] && !$isAdmin && !$isModerator) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only edit your own posts']);
            exit;
        }

        // Update post
        $stmt = $db->prepare("
            UPDATE posts
            SET content = ?, edit_count = edit_count + 1, last_edited_at = NOW(), last_edited_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$content, $user['id'], $postId]);
        $stmt->closeCursor();

        logActivity($user['id'], 'post_edited', 'post', $postId);

        echo json_encode([
            'success' => true,
            'message' => 'Post updated successfully',
            'post' => [
                'id' => $postId,
                'content' => $content,
                'edit_count' => $post['edit_count'] + 1,
                'last_edited_at' => date('Y-m-d H:i:s'),
            ],
        ]);
        exit;
    }

    // DELETE - Delete post
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['post_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'post_id is required']);
            exit;
        }

        $postId = (int) $input['post_id'];

        // Get post
        $stmt = $db->prepare("SELECT * FROM posts WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
            exit;
        }

        // Check ownership or admin/moderator
        $isAdmin = $user['is_admin'] ?? false;
        $isModerator = hasPermission($user['id'], 'moderate_forum');

        if ($post['user_id'] !== $user['id'] && !$isAdmin && !$isModerator) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only delete your own posts']);
            exit;
        }

        // Soft delete
        $stmt = $db->prepare("
            UPDATE posts
            SET is_deleted = 1, deleted_by = ?, deleted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user['id'], $postId]);
        $stmt->closeCursor();

        // Update topic reply count
        $stmt = $db->prepare("UPDATE topics SET replies = GREATEST(0, replies - 1) WHERE id = ?");
        $stmt->execute([$post['topic_id']]);
        $stmt->closeCursor();

        logActivity($user['id'], 'post_deleted', 'post', $postId);

        echo json_encode([
            'success' => true,
            'message' => 'Post deleted successfully',
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'An error occurred',
    ]);
}