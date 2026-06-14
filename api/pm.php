<?php
/**
 * Private Message API — Inbox, sent, compose, delete
 */
require_once __DIR__ . '/config.php';

$db = getDB();
$user = requireAuth();

// GET: List messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $folder   = $_GET['folder'] ?? 'inbox';
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $perPage  = 20;
    $offset   = ($page - 1) * $perPage;
    $search   = trim($_GET['search'] ?? '');

    $conditions = match ($folder) {
        'sent'     => "pm.sender_id = ? AND pm.is_deleted_by_sender = 0",
        'starred'  => "pm.recipient_id = ? AND pm.is_deleted_by_recipient = 0 AND pm.is_starred = 1",
        default    => "pm.recipient_id = ? AND pm.is_deleted_by_recipient = 0",
    };

    $params = [$user['id']];
    $types = 'i';

    if ($search) {
        $conditions .= " AND (pm.subject LIKE ? OR pm.content LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM private_messages pm WHERE $conditions");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $selectFields = $folder === 'sent'
        ? "pm.*, u.username as recipient_username, u.avatar as recipient_avatar"
        : "pm.*, u.username as sender_username, u.avatar as sender_avatar";

    $msgsStmt = $db->prepare("
        SELECT $selectFields
        FROM private_messages pm
        LEFT JOIN users u ON " . ($folder === 'sent' ? "pm.recipient_id = u.id" : "pm.sender_id = u.id") . "
        WHERE $conditions
        ORDER BY pm.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';
    $msgsStmt->bind_param($types, ...$params);
    $msgsStmt->execute();
    $msgsResult = $msgsStmt->get_result();

    $messages = [];
    while ($m = $msgsResult->fetch_assoc()) {
        $messages[] = [
            'id'           => (int) $m['id'],
            'uuid'         => $m['uuid'],
            'subject'      => $m['subject'],
            'is_read'      => (bool) $m['is_read'],
            'is_starred'   => (bool) $m['is_starred'],
            'created_at'   => $m['created_at'],
            'sender'       => $folder !== 'sent' ? ['username' => $m['sender_username'], 'avatar' => $m['sender_avatar']] : null,
            'recipient'    => $folder === 'sent' ? ['username' => $m['recipient_username'], 'avatar' => $m['recipient_avatar']] : null,
        ];
    }
    $msgsStmt->close();

    // Get unread count
    $unreadStmt = $db->prepare("SELECT COUNT(*) as unread FROM private_messages WHERE recipient_id = ? AND is_read = 0 AND is_deleted_by_recipient = 0");
    $unreadStmt->bind_param('i', $user['id']);
    $unreadStmt->execute();
    $unreadCount = (int) $unreadStmt->get_result()->fetch_assoc()['unread'];
    $unreadStmt->close();

    jsonResponse([
        'messages'    => $messages,
        'unread_count'=> $unreadCount,
        'pagination'  => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => ceil($total / $perPage),
        ],
    ]);
}

// POST: Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $recipientUsername = trim($input['to'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $content = trim($input['content'] ?? '');

    if (!$recipientUsername) jsonResponse(['error' => 'Recipient is required'], 400);
    if (!$subject) jsonResponse(['error' => 'Subject is required'], 400);
    if (!$content) jsonResponse(['error' => 'Message content is required'], 400);

    // Find recipient
    $recipStmt = $db->prepare("SELECT id, username FROM users WHERE username = ? AND is_active = 1 AND is_banned = 0");
    $recipStmt->bind_param('s', $recipientUsername);
    $recipStmt->execute();
    $recipResult = $recipStmt->get_result();
    if ($recipResult->num_rows === 0) jsonResponse(['error' => 'Recipient not found'], 404);
    $recipient = $recipResult->fetch_assoc();
    $recipStmt->close();

    if ($recipient['id'] === $user['id']) jsonResponse(['error' => 'Cannot send message to yourself'], 400);

    $uuid = generateUUID();
    $stmt = $db->prepare("INSERT INTO private_messages (uuid, sender_id, recipient_id, subject, content) VALUES (?,?,?,?,?)");
    $stmt->bind_param('siiss', $uuid, $user['id'], $recipient['id'], $subject, $content);
    $stmt->execute();
    $msgId = $stmt->insert_id;
    $stmt->close();

    // Notify recipient
    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, from_user_id, reference_type, reference_id, message) VALUES (?,?,?,?,?,?)");
    $msg = $user['username'] . ' sent you a private message';
    $notifStmt->bind_param('iisiis', $recipient['id'], $pmType = 'pm', $user['id'], $refType = 'pm', $msgId, $msg);
    $notifStmt->execute();
    $notifStmt->close();

    logActivity($user['id'], 'send_pm', "Sent PM to: " . $recipient['username']);

    jsonResponse(['message' => 'Message sent', 'message_id' => $msgId], 201);
}

// PUT: Update message (mark read, star, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $msgId = (int) ($_GET['id'] ?? 0);
    if (!$msgId) jsonResponse(['error' => 'Message ID is required'], 400);

    $stmt = $db->prepare("SELECT * FROM private_messages WHERE id = ? AND (sender_id = ? OR recipient_id = ?)");
    $stmt->bind_param('iii', $msgId, $user['id'], $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) jsonResponse(['error' => 'Message not found'], 404);
    $msg = $result->fetch_assoc();
    $stmt->close();

    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['is_read']) && $input['is_read'] && !$msg['is_read'] && $msg['recipient_id'] === $user['id']) {
        $readStmt = $db->prepare("UPDATE private_messages SET is_read = 1 WHERE id = ?");
        $readStmt->bind_param('i', $msgId);
        $readStmt->execute();
        $readStmt->close();
    }

    if (isset($input['is_starred'])) {
        $starStmt = $db->prepare("UPDATE private_messages SET is_starred = ? WHERE id = ?");
        $starred = $input['is_starred'] ? 1 : 0;
        $starStmt->bind_param('ii', $starred, $msgId);
        $starStmt->execute();
        $starStmt->close();
    }

    jsonResponse(['message' => 'Message updated']);
}

// DELETE: Delete message
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $msgId = (int) ($_GET['id'] ?? 0);
    if (!$msgId) jsonResponse(['error' => 'Message ID is required'], 400);

    $stmt = $db->prepare("SELECT sender_id, recipient_id FROM private_messages WHERE id = ?");
    $stmt->bind_param('i', $msgId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) jsonResponse(['error' => 'Message not found'], 404);
    $msg = $result->fetch_assoc();
    $stmt->close();

    if ($msg['sender_id'] === $user['id']) {
        $delStmt = $db->prepare("UPDATE private_messages SET is_deleted_by_sender = 1 WHERE id = ?");
        $delStmt->bind_param('i', $msgId);
        $delStmt->execute();
        $delStmt->close();
    } elseif ($msg['recipient_id'] === $user['id']) {
        $delStmt = $db->prepare("UPDATE private_messages SET is_deleted_by_recipient = 1 WHERE id = ?");
        $delStmt->bind_param('i', $msgId);
        $delStmt->execute();
        $delStmt->close();
    } else {
        jsonResponse(['error' => 'Permission denied'], 403);
    }

    jsonResponse(['message' => 'Message deleted']);
}
