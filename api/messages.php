<?php
/**
 * Private Messages API
 * GET /api/messages.php - List messages
 * POST /api/messages.php - Send message
 * PUT /api/messages.php - Update (mark read, star)
 * DELETE /api/messages.php - Delete message
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

    // GET - List messages
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = $authUser();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $folder = $_GET['folder'] ?? 'inbox'; // inbox, sent, starred
        $search = isset($_GET['search']) ? sanitize($_GET['search']) : null;

        // Count total
        $where = [];
        $params = [];

        if ($folder === 'inbox') {
            $where[] = 'pm.recipient_id = ? AND pm.is_deleted_by_recipient = 0';
            $params[] = $user['id'];
        } elseif ($folder === 'sent') {
            $where[] = 'pm.sender_id = ? AND pm.is_deleted_by_sender = 0';
            $params[] = $user['id'];
        } elseif ($folder === 'starred') {
            $where[] = '((pm.recipient_id = ? AND pm.is_deleted_by_recipient = 0) OR (pm.sender_id = ? AND pm.is_deleted_by_sender = 0)) AND pm.is_starred = 1';
            $params[] = $user['id'];
            $params[] = $user['id'];
        }

        if ($search) {
            $where[] = '(pm.subject LIKE ? OR pm.content LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("SELECT COUNT(*) FROM private_messages pm {$whereClause}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        // Get unread count
        $stmt = $db->prepare("SELECT COUNT(*) FROM private_messages WHERE recipient_id = ? AND is_read = 0 AND is_deleted_by_recipient = 0");
        $stmt->execute([$user['id']]);
        $unreadCount = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        // Get messages
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare("
            SELECT pm.*,
                   sender.username as sender_username, sender.uuid as sender_uuid, sender.avatar as sender_avatar,
                   recipient.username as recipient_username, recipient.uuid as recipient_uuid, recipient.avatar as recipient_avatar
            FROM private_messages pm
            LEFT JOIN users sender ON pm.sender_id = sender.id
            LEFT JOIN users recipient ON pm.recipient_id = recipient.id
            {$whereClause}
            ORDER BY pm.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $formatted = array_map(function($m) use ($user) {
            return [
                'id' => (int) $m['id'],
                'uuid' => $m['uuid'],
                'subject' => $m['subject'],
                'content' => $m['content'],
                'is_read' => (bool) $m['is_read'],
                'is_starred' => (bool) $m['is_starred'],
                'created_at' => $m['created_at'],
                'is_sent' => $m['sender_id'] === $user['id'],
                'sender' => [
                    'uuid' => $m['sender_uuid'],
                    'username' => $m['sender_username'],
                    'avatar' => $m['sender_avatar'],
                ],
                'recipient' => [
                    'uuid' => $m['recipient_uuid'],
                    'username' => $m['recipient_username'],
                    'avatar' => $m['recipient_avatar'],
                ],
            ];
        }, $messages);

        echo json_encode([
            'success' => true,
            'messages' => $formatted,
            'unread_count' => $unreadCount,
            'folder' => $folder,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
        exit;
    }

    // POST - Send message
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }

        $errors = [];
        if (empty($input['recipient'])) $errors[] = 'recipient is required';
        if (empty($input['subject'])) $errors[] = 'subject is required';
        if (empty($input['content'])) $errors[] = 'content is required';

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
            exit;
        }

        $recipientUsername = sanitize($input['recipient']);
        $subject = sanitize(substr($input['subject'], 0, 300));
        $content = $input['content'];

        // Find recipient
        $stmt = $db->prepare("SELECT id, username FROM users WHERE username = ? AND is_active = 1 AND is_banned = 0");
        $stmt->execute([$recipientUsername]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$recipient) {
            http_response_code(404);
            echo json_encode(['error' => 'Recipient not found']);
            exit;
        }

        // Can't send to self
        if ($recipient['id'] === $user['id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot send message to yourself']);
            exit;
        }

        // Create message
        $uuid = generateUUID();
        $stmt = $db->prepare("
            INSERT INTO private_messages (uuid, sender_id, recipient_id, subject, content, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$uuid, $user['id'], $recipient['id'], $subject, $content]);
        $messageId = $db->lastInsertId();
        $stmt->closeCursor();

        // Create notification for recipient
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, message, link, created_at)
            VALUES (?, ?, 'pm', ?, '/messages', NOW())
        ");
        $stmt->execute([$recipient['id'], $user['id'], "{$user['username']} sent you a message: {$subject}"]);
        $stmt->closeCursor();

        logActivity($user['id'], 'pm_sent', 'pm', $messageId);

        echo json_encode([
            'success' => true,
            'message' => 'Message sent',
            'pm' => [
                'id' => (int) $messageId,
                'uuid' => $uuid,
                'subject' => $subject,
            ],
        ], 201);
        exit;
    }

    // PUT - Update message (mark read, star)
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'id required']);
            exit;
        }

        $messageId = (int) $input['id'];
        $updates = [];
        $params = [];

        // Mark as read
        if (isset($input['is_read'])) {
            $updates[] = 'is_read = ?';
            $params[] = $input['is_read'] ? 1 : 0;
        }

        // Star/unstar
        if (isset($input['is_starred'])) {
            $updates[] = 'is_starred = ?';
            $params[] = $input['is_starred'] ? 1 : 0;
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No updates provided']);
            exit;
        }

        $params[] = $messageId;
        $params[] = $user['id'];

        // User must be sender or recipient
        $stmt = $db->prepare("
            UPDATE private_messages
            SET " . implode(', ', $updates) . "
            WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
        ");
        $stmt->execute($params);
        $stmt->closeCursor();

        echo json_encode([
            'success' => true,
            'message' => 'Message updated',
        ]);
        exit;
    }

    // DELETE - Delete message
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'id required']);
            exit;
        }

        $messageId = (int) $input['id'];

        // Check if user is sender or recipient
        $stmt = $db->prepare("SELECT sender_id, recipient_id FROM private_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$message) {
            http_response_code(404);
            echo json_encode(['error' => 'Message not found']);
            exit;
        }

        // Soft delete based on user role
        if ($message['sender_id'] === $user['id']) {
            $stmt = $db->prepare("UPDATE private_messages SET is_deleted_by_sender = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
        }

        if ($message['recipient_id'] === $user['id']) {
            $stmt = $db->prepare("UPDATE private_messages SET is_deleted_by_recipient = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
        }
        $stmt->closeCursor();

        echo json_encode([
            'success' => true,
            'message' => 'Message deleted',
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
