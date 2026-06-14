<?php
/**
 * Profile API — User profile data
 */
require_once __DIR__ . '/config.php';

$db = getDB();

// GET: Get user profile
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $username = trim($_GET['username'] ?? '');
    $userId   = (int) ($_GET['id'] ?? 0);

    if (!$username && !$userId) jsonResponse(['error' => 'Username or ID is required'], 400);

    if ($userId) {
        $stmt = $db->prepare("
            SELECT u.*, r.name as rank_name, r.color as rank_color, r.badge as rank_badge,
                   d.name as dept_name, d.code as dept_code, d.color as dept_color
            FROM users u
            LEFT JOIN roles r ON u.rank_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $userId);
    } else {
        $stmt = $db->prepare("
            SELECT u.*, r.name as rank_name, r.color as rank_color, r.badge as rank_badge,
                   d.name as dept_name, d.code as dept_code, d.color as dept_color
            FROM users u
            LEFT JOIN roles r ON u.rank_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.username = ? LIMIT 1
        ");
        $stmt->bind_param('s', $username);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) jsonResponse(['error' => 'User not found'], 404);
    $profile = $result->fetch_assoc();
    $stmt->close();

    // Get user roles
    $rolesStmt = $db->prepare("
        SELECT r.name, r.color, r.badge, r.is_staff
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ?
    ");
    $rolesStmt->bind_param('i', $profile['id']);
    $rolesStmt->execute();
    $rolesResult = $rolesStmt->get_result();
    $roles = [];
    while ($r = $rolesResult->fetch_assoc()) {
        $roles[] = ['name' => $r['name'], 'color' => $r['color'], 'badge' => $r['badge'], 'is_staff' => (bool) $r['is_staff']];
    }
    $rolesStmt->close();

    // Get user medals
    $medalsStmt = $db->prepare("
        SELECT m.name, m.icon, m.color, m.type, um.reason, um.granted_at,
               g.username as granted_by_username
        FROM user_medals um
        JOIN medals m ON um.medal_id = m.id
        LEFT JOIN users g ON um.granted_by = g.id
        WHERE um.user_id = ?
        ORDER BY um.granted_at DESC
    ");
    $medalsStmt->bind_param('i', $profile['id']);
    $medalsStmt->execute();
    $medalsResult = $medalsStmt->get_result();
    $medals = [];
    while ($m = $medalsResult->fetch_assoc()) {
        $medals[] = [
            'name'    => $m['name'],
            'icon'    => $m['icon'],
            'color'   => $m['color'],
            'type'    => $m['type'],
            'reason'  => $m['reason'],
            'granted_at' => $m['granted_at'],
            'granted_by' => $m['granted_by_username'],
        ];
    }
    $medalsStmt->close();

    // Get recent threads
    $threadsStmt = $db->prepare("
        SELECT t.id, t.title, t.slug, t.views, t.replies, t.created_at,
               c.name as category_name, f.name as forum_name
        FROM threads t
        JOIN categories c ON t.category_id = c.id
        JOIN forums f ON c.forum_id = f.id
        WHERE t.user_id = ? AND t.is_deleted = 0
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $threadsStmt->bind_param('i', $profile['id']);
    $threadsStmt->execute();
    $threadsResult = $threadsStmt->get_result();
    $recentThreads = [];
    while ($t = $threadsResult->fetch_assoc()) {
        $recentThreads[] = [
            'id' => (int) $t['id'],
            'title' => $t['title'],
            'slug' => $t['slug'],
            'views' => (int) $t['views'],
            'replies' => (int) $t['replies'],
            'created_at' => $t['created_at'],
            'category' => $t['category_name'],
            'forum' => $t['forum_name'],
        ];
    }
    $threadsStmt->close();

    // Get active warnings
    $warnStmt = $db->prepare("
        SELECT w.*, u.username as warned_by_username
        FROM warnings w
        JOIN users u ON w.warned_by = u.id
        WHERE w.user_id = ? AND w.is_active = 1 AND (w.expires_at IS NULL OR w.expires_at > NOW())
    ");
    $warnStmt->bind_param('i', $profile['id']);
    $warnStmt->execute();
    $warnResult = $warnStmt->get_result();
    $warnings = [];
    while ($w = $warnResult->fetch_assoc()) {
        $warnings[] = [
            'reason' => $w['reason'],
            'notes' => $w['notes'],
            'created_at' => $w['created_at'],
            'expires_at' => $w['expires_at'],
            'warned_by' => $w['warned_by_username'],
        ];
    }
    $warnStmt->close();

    jsonResponse([
        'profile' => [
            'id'           => (int) $profile['id'],
            'uuid'         => $profile['uuid'],
            'username'     => $profile['username'],
            'avatar'       => $profile['avatar'],
            'cover_photo'  => $profile['cover_photo'],
            'biography'    => $profile['biography'],
            'signature'    => $profile['signature'],
            'rank'         => $profile['rank_name'],
            'rank_color'   => $profile['rank_color'],
            'rank_badge'   => $profile['rank_badge'],
            'dept'         => $profile['dept_name'],
            'dept_code'   => $profile['dept_code'],
            'dept_color'   => $profile['dept_color'],
            'join_date'    => $profile['join_date'],
            'last_seen'    => $profile['last_seen'],
            'total_threads'=> (int) $profile['total_threads'],
            'total_posts'  => (int) $profile['total_posts'],
            'is_verified'  => (bool) $profile['is_verified'],
            'is_banned'    => (bool) $profile['is_banned'],
            'is_suspended' => (bool) $profile['is_suspended'],
        ],
        'roles'         => $roles,
        'medals'        => $medals,
        'recent_threads'=> $recentThreads,
        'warnings'      => $warnings,
    ]);
}

// PUT: Update own profile
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $user = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    $fields = [];
    $params = [];
    $types  = '';

    if (isset($input['biography'])) {
        $fields[] = 'biography = ?';
        $params[] = trim($input['biography']);
        $types .= 's';
    }

    if (isset($input['signature'])) {
        $fields[] = 'signature = ?';
        $params[] = trim($input['signature']);
        $types .= 's';
    }

    if (isset($input['avatar'])) {
        $fields[] = 'avatar = ?';
        $params[] = trim($input['avatar']);
        $types .= 's';
    }

    if (empty($fields)) jsonResponse(['error' => 'No fields to update'], 400);

    $params[] = $user['id'];
    $types .= 'i';

    $stmt = $db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    logActivity($user['id'], 'update_profile', 'Profile updated');

    jsonResponse(['message' => 'Profile updated successfully']);
}
