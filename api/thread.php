<?php
/**
 * Thread API — CRUD operations for threads
 */
require_once __DIR__ . '/config.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $threadId = (int) ($_GET['id'] ?? 0);
    if (!$threadId) jsonResponse(['error' => 'Thread ID is required'], 400);

    // Get thread
    $stmt = $db->prepare("
        SELECT t.*, u.username, u.avatar, u.rank_id,
               r.name as rank_name, r.color as rank_color, r.badge as rank_badge,
               d.name as dept_name, d.code as dept_code,
               c.name as category_name, c.id as category_id,
               f.name as forum_name, f.id as forum_id
        FROM threads t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN roles r ON u.rank_id = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        JOIN categories c ON t.category_id = c.id
        JOIN forums f ON c.forum_id = f.id
        WHERE t.id = ? AND t.is_deleted = 0
    ");
    $stmt->bind_param('i', $threadId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) jsonResponse(['error' => 'Thread not found'], 404);
    $thread = $result->fetch_assoc();
    $stmt->close();

    // Increment view count
    $db->query("UPDATE threads SET views = views + 1 WHERE id = $threadId");

    // Get posts
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(5, (int) (getSetting('posts_per_page', 15))));
    $offset = ($page - 1) * $perPage;

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE thread_id = ? AND is_deleted = 0");
    $countStmt->bind_param('i', $threadId);
    $countStmt->execute();
    $totalPosts = (int) $countStmt->get_result()->fetch_assoc()['total'];
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
        // Get reactions for this post
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

        // Check if current user reacted
        $userReactions = [];
        if (isLoggedIn()) {
            $userId = getCurrentUser()['id'];
            $urStmt = $db->prepare("SELECT reaction_id FROM post_reactions WHERE post_id = ? AND user_id = ?");
            $urStmt->bind_param('ii', $p['id'], $userId);
            $urStmt->execute();
            $urResult = $urStmt->get_result();
            while ($ur = $urResult->fetch_assoc()) {
                $userReactions[] = (int) $ur['reaction_id'];
            }
            $urStmt->close();
        }

        $posts[] = [
            'id'           => (int) $p['id'],
            'uuid'         => $p['uuid'],
            'content'      => $p['content'],
            'likes'        => (int) $p['likes'],
            'is_first_post'=> (bool) $p['is_first_post'],
            'edit_count'   => (int) $p['edit_count'],
            'last_edited_at' => $p['last_edited_at'],
            'created_at'   => $p['created_at'],
            'author'       => [
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
            'reactions'    => $reactions,
            'user_reactions' => $userReactions,
        ];
    }
    $postsStmt->close();

    // Check if user is watching
    $isWatching = false;
    $isBookmarked = false;
    if (isLoggedIn()) {
        $uid = getCurrentUser()['id'];
        $wStmt = $db->prepare("SELECT 1 FROM thread_watch WHERE user_id = ? AND thread_id = ?");
        $wStmt->bind_param('ii', $uid, $threadId);
        $wStmt->execute();
        $isWatching = $wStmt->get_result()->num_rows > 0;
        $wStmt->close();

        $bStmt = $db->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND thread_id = ?");
        $bStmt->bind_param('ii', $uid, $threadId);
        $bStmt->execute();
        $isBookmarked = $bStmt->get_result()->num_rows > 0;
        $bStmt->close();
    }

    jsonResponse([
        'thread' => [
            'id'            => (int) $thread['id'],
            'uuid'          => $thread['uuid'],
            'title'         => $thread['title'],
            'slug'          => $thread['slug'],
            'content'       => $thread['content'],
            'views'         => (int) $thread['views'] + 1,
            'replies'       => (int) $thread['replies'],
            'likes'         => (int) $thread['likes'],
            'prefix'        => $thread['prefix'],
            'is_pinned'     => (bool) $thread['is_pinned'],
            'is_sticky'     => (bool) $thread['is_sticky'],
            'is_locked'     => (bool) $thread['is_locked'],
            'is_archived'   => (bool) $thread['is_archived'],
            'created_at'    => $thread['created_at'],
            'last_reply_at' => $thread['last_reply_at'],
            'category'      => ['id' => (int) $thread['category_id'], 'name' => $thread['category_name']],
            'forum'         => ['id' => (int) $thread['forum_id'], 'name' => $thread['forum_name']],
            'author'        => [
                'id'         => (int) $thread['user_id'],
                'username'   => $thread['username'],
                'avatar'     => $thread['avatar'],
                'rank'       => $thread['rank_name'],
                'rank_color' => $thread['rank_color'],
                'rank_badge' => $thread['rank_badge'],
                'dept'       => $thread['dept_name'],
                'dept_code'  => $thread['dept_code'],
            ],
            'is_watching'   => $isWatching,
            'is_bookmarked' => $isBookmarked,
        ],
        'posts'      => $posts,
        'pagination' => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $totalPosts,
            'total_pages' => ceil($totalPosts / $perPage),
        ],
    ]);
}

// All write methods require authentication
$user = requireAuth();

// POST: Create thread
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $title    = trim($input['title'] ?? '');
    $content  = trim($input['content'] ?? '');
    $categoryId = (int) ($input['category_id'] ?? 0);
    $prefix   = trim($input['prefix'] ?? '');

    if (!$title) jsonResponse(['error' => 'Title is required'], 400);
    if (!$content) jsonResponse(['error' => 'Content is required'], 400);
    if (!$categoryId) jsonResponse(['error' => 'Category is required'], 400);

    // Check category exists
    $catStmt = $db->prepare("SELECT id FROM categories WHERE id = ? AND is_active = 1");
    $catStmt->bind_param('i', $categoryId);
    $catStmt->execute();
    if ($catStmt->get_result()->num_rows === 0) jsonResponse(['error' => 'Category not found'], 404);
    $catStmt->close();

    $uuid = generateUUID();
    $slug = slugify($title);
    $userId = $user['id'];

    $stmt = $db->prepare("INSERT INTO threads (uuid, category_id, user_id, title, slug, content, prefix, last_reply_at, last_reply_by) VALUES (?,?,?,?,?,?,?,NOW(),?)");
    $stmt->bind_param('siisssss', $uuid, $categoryId, $userId, $title, $slug, $content, $prefix, $userId);
    $stmt->execute();
    $threadId = $stmt->insert_id;
    $stmt->close();

    // Create first post
    $postUuid = generateUUID();
    $postStmt = $db->prepare("INSERT INTO posts (uuid, thread_id, user_id, content, is_first_post) VALUES (?,?,?,?,1)");
    $postStmt->bind_param('siis', $postUuid, $threadId, $userId, $content);
    $postStmt->execute();
    $postStmt->close();

    // Update user thread count
    $db->query("UPDATE users SET total_threads = total_threads + 1 WHERE id = $userId");

    logActivity($userId, 'create_thread', "Created thread: $title (ID: $threadId)");

    jsonResponse([
        'message'   => 'Thread created successfully',
        'thread_id' => $threadId,
        'slug'      => $slug,
    ], 201);
}

// PUT: Update thread
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $threadId = (int) ($_GET['id'] ?? 0);
    if (!$threadId) jsonResponse(['error' => 'Thread ID is required'], 400);

    $stmt = $db->prepare("SELECT user_id, is_locked FROM threads WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param('i', $threadId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) jsonResponse(['error' => 'Thread not found'], 404);
    $thread = $result->fetch_assoc();
    $stmt->close();

    if ($thread['user_id'] !== $user['id'] && !hasPermission($user['id'], 'moderate_threads')) {
        jsonResponse(['error' => 'You can only edit your own threads'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $title  = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $prefix  = trim($input['prefix'] ?? '');

    if (!$title) jsonResponse(['error' => 'Title is required'], 400);

    $slug = slugify($title);
    $updateStmt = $db->prepare("UPDATE threads SET title = ?, slug = ?, content = ?, prefix = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('ssssi', $title, $slug, $content, $prefix, $threadId);
    $updateStmt->execute();
    $updateStmt->close();

    logActivity($user['id'], 'edit_thread', "Edited thread ID: $threadId");

    jsonResponse(['message' => 'Thread updated successfully']);
}

// DELETE: Soft-delete thread
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $threadId = (int) ($_GET['id'] ?? 0);
    if (!$threadId) jsonResponse(['error' => 'Thread ID is required'], 400);

    $stmt = $db->prepare("SELECT user_id FROM threads WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param('i', $threadId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) jsonResponse(['error' => 'Thread not found'], 404);
    $thread = $result->fetch_assoc();
    $stmt->close();

    if ($thread['user_id'] !== $user['id'] && !hasPermission($user['id'], 'delete_threads')) {
        jsonResponse(['error' => 'Permission denied'], 403);
    }

    $delStmt = $db->prepare("UPDATE threads SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() WHERE id = ?");
    $delStmt->bind_param('ii', $user['id'], $threadId);
    $delStmt->execute();
    $delStmt->close();

    $db->query("UPDATE users SET total_threads = total_threads - 1 WHERE id = " . $thread['user_id']);

    logActivity($user['id'], 'delete_thread', "Deleted thread ID: $threadId");

    jsonResponse(['message' => 'Thread deleted successfully']);
}