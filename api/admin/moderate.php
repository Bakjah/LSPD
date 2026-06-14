<?php
/**
 * Moderation API — Thread/post moderation actions
 */
require_once __DIR__ . '/config.php';

$db = getDB();
$user = requireStaff();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get moderation log
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 30;
    $offset  = ($page - 1) * $perPage;

    $logs = $db->query("
        SELECT a.*, u.username
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.action IN ('delete_thread','delete_post','lock_thread','pin_thread','warn_user','suspend_user','ban_user','move_thread')
        ORDER BY a.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");

    $items = [];
    while ($l = $logs->fetch_assoc()) {
        $items[] = [
            'id'         => (int) $l['id'],
            'username'   => $l['username'],
            'action'     => $l['action'],
            'details'    => $l['details'],
            'ip_address' => $l['ip_address'],
            'created_at' => $l['created_at'],
        ];
    }

    jsonResponse(['logs' => $items]);
}

// POST: Moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'lock_thread':
            $threadId = (int) ($input['thread_id'] ?? 0);
            if (!$threadId) jsonResponse(['error' => 'Thread ID required'], 400);
            if (!hasPermission($user['id'], 'lock_threads')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE threads SET is_locked = 1 WHERE id = $threadId");
            logActivity($user['id'], 'lock_thread', "Locked thread ID: $threadId");
            jsonResponse(['message' => 'Thread locked']);

        case 'unlock_thread':
            $threadId = (int) ($input['thread_id'] ?? 0);
            if (!$threadId) jsonResponse(['error' => 'Thread ID required'], 400);
            if (!hasPermission($user['id'], 'lock_threads')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE threads SET is_locked = 0 WHERE id = $threadId");
            logActivity($user['id'], 'lock_thread', "Unlocked thread ID: $threadId");
            jsonResponse(['message' => 'Thread unlocked']);

        case 'pin_thread':
            $threadId = (int) ($input['thread_id'] ?? 0);
            if (!$threadId) jsonResponse(['error' => 'Thread ID required'], 400);
            if (!hasPermission($user['id'], 'pin_threads')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE threads SET is_pinned = 1 WHERE id = $threadId");
            logActivity($user['id'], 'pin_thread', "Pinned thread ID: $threadId");
            jsonResponse(['message' => 'Thread pinned']);

        case 'unpin_thread':
            $threadId = (int) ($input['thread_id'] ?? 0);
            if (!$threadId) jsonResponse(['error' => 'Thread ID required'], 400);
            if (!hasPermission($user['id'], 'pin_threads')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE threads SET is_pinned = 0 WHERE id = $threadId");
            logActivity($user['id'], 'pin_thread', "Unpinned thread ID: $threadId");
            jsonResponse(['message' => 'Thread unpinned']);

        case 'move_thread':
            $threadId    = (int) ($input['thread_id'] ?? 0);
            $categoryId  = (int) ($input['category_id'] ?? 0);
            if (!$threadId || !$categoryId) jsonResponse(['error' => 'Thread ID and Category ID required'], 400);
            if (!hasPermission($user['id'], 'move_threads')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE threads SET category_id = $categoryId WHERE id = $threadId");
            logActivity($user['id'], 'move_thread', "Moved thread ID: $threadId to category: $categoryId");
            jsonResponse(['message' => 'Thread moved']);

        case 'delete_thread':
            $threadId = (int) ($input['thread_id'] ?? 0);
            if (!$threadId) jsonResponse(['error' => 'Thread ID required'], 400);
            if (!hasPermission($user['id'], 'delete_threads')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE threads SET is_deleted = 1, deleted_by = " . $user['id'] . ", deleted_at = NOW() WHERE id = $threadId");
            logActivity($user['id'], 'delete_thread', "Deleted thread ID: $threadId");
            jsonResponse(['message' => 'Thread deleted']);

        case 'delete_post':
            $postId = (int) ($input['post_id'] ?? 0);
            if (!$postId) jsonResponse(['error' => 'Post ID required'], 400);
            if (!hasPermission($user['id'], 'delete_posts')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE posts SET is_deleted = 1, deleted_by = " . $user['id'] . ", deleted_at = NOW() WHERE id = $postId");
            logActivity($user['id'], 'delete_post', "Deleted post ID: $postId");
            jsonResponse(['message' => 'Post deleted']);

        case 'warn_user':
            $targetId = (int) ($input['user_id'] ?? 0);
            $reason   = trim($input['reason'] ?? '');
            $notes    = trim($input['notes'] ?? '');
            $expires  = $input['expires_at'] ?? null;
            if (!$targetId || !$reason) jsonResponse(['error' => 'User ID and reason required'], 400);
            if (!hasPermission($user['id'], 'warn_users')) jsonResponse(['error' => 'Permission denied'], 403);
            $stmt = $db->prepare("INSERT INTO warnings (user_id, warned_by, reason, notes, expires_at) VALUES (?,?,?,?,?)");
            $stmt->bind_param('iisss', $targetId, $user['id'], $reason, $notes, $expires);
            $stmt->execute();
            $warnId = $stmt->insert_id;
            $stmt->close();
            logActivity($user['id'], 'warn_user', "Warned user ID: $targetId - Reason: $reason");
            jsonResponse(['message' => 'User warned', 'warning_id' => $warnId]);

        case 'suspend_user':
            $targetId = (int) ($input['user_id'] ?? 0);
            $reason   = trim($input['reason'] ?? '');
            $until    = $input['suspended_until'] ?? null;
            if (!$targetId || !$reason) jsonResponse(['error' => 'User ID and reason required'], 400);
            if (!hasPermission($user['id'], 'suspend_users')) jsonResponse(['error' => 'Permission denied'], 403);
            $stmt = $db->prepare("UPDATE users SET is_suspended = 1, suspended_until = ?, suspended_reason = ? WHERE id = ?");
            $stmt->bind_param('ssi', $until, $reason, $targetId);
            $stmt->execute();
            $stmt->close();
            logActivity($user['id'], 'suspend_user', "Suspended user ID: $targetId until: $until - Reason: $reason");
            jsonResponse(['message' => 'User suspended']);

        case 'unsuspend_user':
            $targetId = (int) ($input['user_id'] ?? 0);
            if (!$targetId) jsonResponse(['error' => 'User ID required'], 400);
            if (!hasPermission($user['id'], 'suspend_users')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE users SET is_suspended = 0, suspended_until = NULL, suspended_reason = NULL WHERE id = $targetId");
            logActivity($user['id'], 'suspend_user', "Unsuspended user ID: $targetId");
            jsonResponse(['message' => 'User unsuspended']);

        case 'ban_user':
            $targetId = (int) ($input['user_id'] ?? 0);
            $reason   = trim($input['reason'] ?? '');
            if (!$targetId || !$reason) jsonResponse(['error' => 'User ID and reason required'], 400);
            if (!hasPermission($user['id'], 'ban_users')) jsonResponse(['error' => 'Permission denied'], 403);
            $stmt = $db->prepare("UPDATE users SET is_banned = 1, banned_reason = ?, banned_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $reason, $targetId);
            $stmt->execute();
            $stmt->close();
            logActivity($user['id'], 'ban_user', "Banned user ID: $targetId - Reason: $reason");
            jsonResponse(['message' => 'User banned']);

        case 'unban_user':
            $targetId = (int) ($input['user_id'] ?? 0);
            if (!$targetId) jsonResponse(['error' => 'User ID required'], 400);
            if (!hasPermission($user['id'], 'ban_users')) jsonResponse(['error' => 'Permission denied'], 403);
            $db->query("UPDATE users SET is_banned = 0, banned_reason = NULL, banned_at = NULL WHERE id = $targetId");
            logActivity($user['id'], 'ban_user', "Unbanned user ID: $targetId");
            jsonResponse(['message' => 'User unbanned']);

        case 'add_note':
            $targetId = (int) ($input['user_id'] ?? 0);
            $note     = trim($input['note'] ?? '');
            if (!$targetId || !$note) jsonResponse(['error' => 'User ID and note required'], 400);
            $stmt = $db->prepare("INSERT INTO moderator_notes (user_id, staff_id, note) VALUES (?,?,?)");
            $stmt->bind_param('iis', $targetId, $user['id'], $note);
            $stmt->execute();
            $stmt->close();
            jsonResponse(['message' => 'Note added']);

        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
}
