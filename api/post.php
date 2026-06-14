<?php
/**
 * Post API — CRUD operations for posts
 */
require_once __DIR__ . '/config.php';

$db = getDB();

// GET: Fetch posts (paginated)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $threadId = (int) ($_GET['thread_id'] ?? 0);
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $perPage  = min(50, max(5, (int) (getSetting('posts_per_page', 15))));
    $offset   = ($page - 1) * $perPage;

    if (!$threadId) jsonResponse(['error' => 'Thread ID is required'], 400);

    // Verify thread exists
    $threadStmt = $db->prepare("SELECT id, is_locked, is_deleted FROM threads WHERE id = ?");
    $threadStmt->bind_param('i', $threadId);
    $threadStmt->execute();
    $threadResult = $threadStmt->get_result();
    if ($threadResult->num_rows === 0) jsonResponse(['error' => 'Thread not found'], 404);
    $thread = $threadResult->fetch_assoc();
    $threadStmt->close();

    if ($thread['is_deleted']) jsonResponse(['error' => 'Thread has been deleted'], 410);

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE thread_id = ? AND is_deleted = 0");
    $countStmt->bind_param('i', $threadId);
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $postsStmt = $db->prepare("
        SELECT p.*, u.username, u.avatar, u.biography, u.signature, u.total_posts, u.join_date, u.last_seen,
               r.name as rank_name, r.color as rank_color, r.badge as rank_badge,
               d.name as dept_name, d.code as dept_code
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN roles r ON u.rank_id = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE p.thread_id = ? AND p.is_deleted = 0
        ORDER BY p.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $postsStmt->bind_param('iii', $threadId, $perPage, $offset);
    $postsStmt->execute();
    $postsResult = $postsStmt->get_result();

    $posts = [];
    while ($p = $postsResult->fetch_assoc()) {
        // Get reactions
        $reactionsStmt = $db->prepare("
            SELECT rx.name, rx.icon, rx.color, COUNT(*) as count
            FROM post_reactions pr
            JOIN reactions rx ON pr.reaction_id = rx.id
            WHERE pr.post_id = ?
            GROUP BY rx.id
        ");
        $reactionsStmt->bind_param('i', $p['id']);
        $reactionsStmt->execute();
        $reactionsResult = $reactionsStmt->get_result();
        $reactions = [];
        while ($r = $reactionsResult->fetch_assoc()) {
            $reactions[] = ['name' => $r['name'], 'icon' => $r['icon'], 'color' => $r['color'], 'count' => (int) $r['count']];
        }
        $reactionsStmt->close();

        $userReactions = [];
        if (isLoggedIn()) {
            $uid = getCurrentUser()['id'];
            $urStmt = $db->prepare("SELECT reaction_id FROM post_reactions WHERE post_id = ? AND user_id = ?");
            $urStmt->bind_param('ii', $p['id'], $uid);
            $urStmt->execute();
            $urResult = $urStmt->get_result();
            while ($ur = $urResult->fetch_assoc()) {
                $userReactions[] = (int) $ur['reaction_id'];
            }
            $urStmt->close();
        }

        $posts[] = [
            'id'             => (int) $p['id'],
            'uuid'           => $p['uuid'],
            'content'        => $p['content'],
            'likes'          => (int) $p['likes'],
            'is_first_post'  => (bool) $p['is_first_post'],
            'edit_count'     => (int) $p['edit_count'],
            'last_edited_at' => $p['last_edited_at'],
            'created_at'     => $p['created_at'],
            'author'         => [
                'id'         => (int) $p['user_id'],
                'username'   => $p['username'],
                'avatar'     => $p['avatar'],
                'biography'  => $p['biography'],
                'signature'  => $p['signature'],
                'total_posts'=> (int) $p['total_posts'],
                'join_date'  => $p['join_date'],
                'last_seen'  => $p['last_seen'],
                'rank'       => $p['rank_name'],
                'rank_color' => $p['rank_color'],
                'rank_badge' => $p['rank_badge'],
                'dept'       => $p['dept_name'],
                'dept_code'  => $p['dept_code'],
            ],
            'reactions'      => $reactions,
            'user_reactions' => $userReactions,
        ];
    }
    $postsStmt->close();

    jsonResponse([
        'posts'      => $posts,
        'pagination' => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => ceil($total / $perPage),
        ],
    ]);
}

$user = requireAuth();

// POST: Create post (reply)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $threadId = (int) ($input['thread_id'] ?? 0);
    $content  = trim($input['content'] ?? '');

    if (!$threadId) jsonResponse(['error' => 'Thread ID is required'], 400);
    if (!$content) jsonResponse(['error' => 'Content is required'], 400);

    // Check thread
    $threadStmt = $db->prepare("SELECT id, user_id, is_locked, is_deleted FROM threads WHERE id = ?");
    $threadStmt->bind_param('i', $threadId);
    $threadStmt->execute();
    $threadResult = $threadStmt->get_result();
    if ($threadResult->num_rows === 0) jsonResponse(['error' => 'Thread not found'], 404);
    $thread = $threadResult->fetch_assoc();
    $threadStmt->close();

    if ($thread['is_deleted']) jsonResponse(['error' => 'Thread has been deleted'], 410);
    if ($thread['is_locked']) jsonResponse(['error' => 'Thread is locked'], 403);

    $userId = $user['id'];
    $uuid = generateUUID();

    $stmt = $db->prepare("INSERT INTO posts (uuid, thread_id, user_id, content) VALUES (?,?,?,?)");
    $stmt->bind_param('siis', $uuid, $threadId, $userId, $content);
    $stmt->execute();
    $postId = $stmt->insert_id;
    $stmt->close();

    // Update thread
    $updateThread = $db->prepare("UPDATE threads SET replies = replies + 1, last_reply_at = NOW(), last_reply_by = ? WHERE id = ?");
    $updateThread->bind_param('ii', $userId, $threadId);
    $updateThread->execute();
    $updateThread->close();

    // Update user post count
    $db->query("UPDATE users SET total_posts = total_posts + 1 WHERE id = $userId");

    // Create notification for thread author
    if ($thread['user_id'] !== $userId) {
        $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, from_user_id, reference_type, reference_id, thread_id, post_id, message) VALUES (?,?,?,?,?,?,?,?)");
        $msg = $user['username'] . ' replied to your thread';
        $notifStmt->bind_param('iisiisis', $thread['user_id'], $replyType = 'reply', $userId, $refType = 'thread', $threadId, $threadId, $postId, $msg);
        $notifStmt->execute();
        $notifStmt->close();
    }

    // Parse @mentions and create notifications
    if (preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches)) {
        foreach (array_unique($matches[1]) as $mentionedUsername) {
            $mentionStmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $mentionStmt->bind_param('si', $mentionedUsername, $userId);
            $mentionStmt->execute();
            $mentionResult = $mentionStmt->get_result();
            if ($mentionResult->num_rows > 0) {
                $mentionedId = $mentionResult->fetch_assoc()['id'];
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, from_user_id, reference_type, reference_id, thread_id, post_id, message) VALUES (?,?,?,?,?,?,?,?)");
                $msg = $user['username'] . ' mentioned you in a post';
                $notifStmt->bind_param('iisiisis', $mentionedId, $mentionType = 'mention', $userId, $refType = 'post', $postId, $threadId, $postId, $msg);
                $notifStmt->execute();
                $notifStmt->close();
            }
            $mentionStmt->close();
        }
    }

    logActivity($userId, 'create_post', "Posted reply in thread ID: $threadId");

    jsonResponse([
        'message' => 'Post created successfully',
        'post_id' => $postId,
    ], 201);
}

// PUT: Edit post
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $postId = (int) ($_GET['id'] ?? 0);
    if (!$postId) jsonResponse(['error' => 'Post ID is required'], 400);

    $stmt = $db->prepare("SELECT user_id, content, thread_id FROM posts WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) jsonResponse(['error' => 'Post not found'], 404);
    $post = $result->fetch_assoc();
    $stmt->close();

    if ($post['user_id'] !== $user['id'] && !hasPermission($user['id'], 'edit_own_post')) {
        jsonResponse(['error' => 'Permission denied'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $content = trim($input['content'] ?? '');
    if (!$content) jsonResponse(['error' => 'Content is required'], 400);

    $editStmt = $db->prepare("UPDATE posts SET content = ?, edit_count = edit_count + 1, last_edited_at = NOW(), last_edited_by = ? WHERE id = ?");
    $editStmt->bind_param('sii', $content, $user['id'], $postId);
    $editStmt->execute();
    $editStmt->close();

    jsonResponse(['message' => 'Post updated successfully']);
}

// DELETE: Soft-delete post
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $postId = (int) ($_GET['id'] ?? 0);
    if (!$postId) jsonResponse(['error' => 'Post ID is required'], 400);

    $stmt = $db->prepare("SELECT user_id, thread_id, is_first_post FROM posts WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) jsonResponse(['error' => 'Post not found'], 404);
    $post = $result->fetch_assoc();
    $stmt->close();

    if ($post['user_id'] !== $user['id'] && !hasPermission($user['id'], 'delete_posts')) {
        jsonResponse(['error' => 'Permission denied'], 403);
    }

    $delStmt = $db->prepare("UPDATE posts SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() WHERE id = ?");
    $delStmt->bind_param('ii', $user['id'], $postId);
    $delStmt->execute();
    $delStmt->close();

    if (!$post['is_first_post']) {
        $db->query("UPDATE threads SET replies = GREATEST(0, replies - 1) WHERE id = " . $post['thread_id']);
        $db->query("UPDATE users SET total_posts = total_posts - 1 WHERE id = " . $post['user_id']);
    }

    logActivity($user['id'], 'delete_post', "Deleted post ID: $postId");

    jsonResponse(['message' => 'Post deleted successfully']);
}