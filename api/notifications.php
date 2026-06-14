<?php
/**
 * Notifications API
 * GET /api/notifications.php - List notifications
 * POST /api/notifications.php - Mark as read
 * DELETE /api/notifications.php - Delete notification
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/logging.php';

header('Content-Type: application/json; charset=utf-8');
// CORS handled by applyCORS()
// CORS handled by applyCORS()
// CORS handled by applyCORS()

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $db = getDB();

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

    // GET - List notifications
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = $authUser();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $unreadOnly = isset($_GET['unread']) && $_GET['unread'] == '1';
        $type = isset($_GET['type']) ? sanitize($_GET['type']) : null;

        // Get notifications
        $where = ['n.user_id = ?'];
        $params = [$user['id']];

        if ($unreadOnly) {
            $where[] = 'n.is_read = 0';
        }

        if ($type) {
            $where[] = 'n.type = ?';
            $params[] = $type;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Count total
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications n {$whereClause}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        // Get unread count
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user['id']]);
        $unreadCount = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        // Get notifications
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare("
            SELECT n.*,
                   fu.username as from_username, fu.uuid as from_uuid, fu.avatar as from_avatar,
                   t.title as topic_title, t.slug as topic_slug
            FROM notifications n
            LEFT JOIN users fu ON n.from_user_id = fu.id
            LEFT JOIN topics t ON n.topic_id = t.id
            {$whereClause}
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Type labels
        $typeLabels = [
            'mention' => 'mentioned you',
            'quote' => 'quoted your post',
            'reply' => 'replied to your topic',
            'pm' => 'sent you a message',
            'reaction' => 'reacted to your post',
            'role' => 'role updated',
            'system' => 'system notification',
        ];

        $formatted = array_map(function($n) use ($typeLabels) {
            return [
                'id' => (int) $n['id'],
                'type' => $n['type'],
                'label' => $typeLabels[$n['type']] ?? $n['type'],
                'message' => $n['message'],
                'is_read' => (bool) $n['is_read'],
                'read_at' => $n['read_at'],
                'created_at' => $n['created_at'],
                'from_user' => $n['from_user_id'] ? [
                    'uuid' => $n['from_uuid'],
                    'username' => $n['from_username'],
                    'avatar' => $n['from_avatar'],
                ] : null,
                'topic' => $n['topic_id'] ? [
                    'title' => $n['topic_title'],
                    'slug' => $n['topic_slug'],
                ] : null,
            ];
        }, $notifications);

        echo json_encode([
            'success' => true,
            'notifications' => $formatted,
            'unread_count' => $unreadCount,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
        exit;
    }

    // POST - Mark as read
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        $markAll = isset($input['mark_all']) && $input['mark_all'];
        $ids = $input['ids'] ?? [];

        if ($markAll) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user['id']]);
            $affected = $stmt->rowCount();
            $stmt->closeCursor();

            echo json_encode([
                'success' => true,
                'message' => "Marked {$affected} notifications as read",
            ]);
        } elseif (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id IN ($placeholders) AND user_id = ?");
            $params = array_merge($ids, [$user['id']]);
            $stmt->execute($params);
            $stmt->closeCursor();

            echo json_encode([
                'success' => true,
                'message' => 'Notifications marked as read',
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ids or mark_all required']);
        }
        exit;
    }

    // DELETE - Delete notification
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $user = $authUser();
        $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];

        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'ids required']);
            exit;
        }

        $ids = array_map('intval', array_filter($ids));
        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ids']);
            exit;
        }

        $placeholders = implode(',', $ids);
        $params = array_merge($ids, [$user['id']]);
        $stmt = $db->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?");
        $stmt->execute($params);
        $stmt->closeCursor();

        echo json_encode([
            'success' => true,
            'message' => 'Notifications deleted',
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
