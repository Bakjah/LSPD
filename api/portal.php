<?php
/**
 * Portal API - Standalone Endpoint
 * GET /api/portal
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/logging.php';

// Apply CORS headers (will only apply once due to static guard in function)
applyCORS();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();

    // Get overall stats
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND is_banned = 0");
    $stmt->execute();
    $totalUsers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // Online users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND is_active = 1");
    $stmt->execute();
    $onlineUsers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // New this week
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_active = 1");
    $stmt->execute();
    $newUsersWeek = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // Get departments
    $stmt = $db->prepare("
        SELECT d.id, d.uuid, d.code, d.name, d.slug, d.description, d.color, d.icon, d.logo, d.banner,
               COUNT(DISTINCT ud.user_id) as member_count
        FROM departments d
        LEFT JOIN user_departments ud ON d.id = ud.department_id
        WHERE d.is_active = 1
        GROUP BY d.id, d.uuid, d.code, d.name, d.slug, d.description, d.color, d.icon, d.logo, d.banner
        ORDER BY d.sort_order ASC
    ");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formattedDepts = [];
    foreach ($departments as $dept) {
        // Get leaders
        $stmt = $db->prepare("
            SELECT u.uuid, u.username, u.avatar, r.name as role_name, r.badge
            FROM user_roles ur
            JOIN users u ON ur.user_id = u.id
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.department_id = ? AND r.is_leader = 1
            ORDER BY r.hierarchy ASC
            LIMIT 3
        ");
        $stmt->execute([$dept['id']]);
        $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $formattedDepts[] = [
            'id' => (int) $dept['id'],
            'uuid' => $dept['uuid'],
            'code' => $dept['code'],
            'name' => $dept['name'],
            'slug' => $dept['slug'],
            'description' => $dept['description'],
            'color' => $dept['color'],
            'icon' => $dept['icon'],
            'logo' => $dept['logo'],
            'banner' => $dept['banner'],
            'member_count' => (int) $dept['member_count'],
            'leaders' => array_map(fn($l) => [
                'uuid' => $l['uuid'],
                'username' => $l['username'],
                'avatar' => $l['avatar'],
                'role' => $l['role_name'],
                'badge' => $l['badge'],
            ], $leaders),
        ];
    }

    // Get recent members
    $stmt = $db->prepare("
        SELECT u.uuid, u.username, u.avatar, u.created_at, u.last_seen,
               d.code as dept_code, d.name as dept_name, d.color as dept_color
        FROM users u
        LEFT JOIN user_departments ud ON u.id = ud.user_id AND ud.is_primary = 1
        LEFT JOIN departments d ON ud.department_id = d.id
        WHERE u.is_active = 1 AND u.is_banned = 0
        ORDER BY u.created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $recentMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Get recent activity
    $stmt = $db->prepare("
        SELECT al.id, al.action, al.created_at,
               u.uuid as user_uuid, u.username as user_username, u.avatar as user_avatar
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $actionLabels = [
        'user_register' => 'joined the community',
        'user_login' => 'came online',
        'user_logout' => 'went offline',
        'profile_update' => 'updated their profile',
        'avatar_update' => 'changed their avatar',
        'role_assigned' => 'was promoted',
        'password_change' => 'changed their password',
    ];

    $formattedActivity = [];
    foreach ($activities as $a) {
        $formattedActivity[] = [
            'id' => (int) $a['id'],
            'action' => $a['action'],
            'label' => $actionLabels[$a['action']] ?? $a['action'],
            'created_at' => $a['created_at'],
            'user' => $a['user_uuid'] ? [
                'uuid' => $a['user_uuid'],
                'username' => $a['user_username'],
                'avatar' => $a['user_avatar'],
            ] : null,
        ];
    }

    // Response
    echo json_encode([
        'success' => true,
        'portal' => [
            'name' => getSetting('site_name', 'Los Santos Roleplay Community'),
            'tagline' => getSetting('site_tagline', 'To Protect and Serve'),
            'stats' => [
                'total_users' => $totalUsers,
                'online_users' => $onlineUsers,
                'new_users_week' => $newUsersWeek,
                'total_departments' => count($departments),
            ],
            'departments' => $formattedDepts,
            'recent_members' => array_map(fn($m) => [
                'uuid' => $m['uuid'],
                'username' => $m['username'],
                'avatar' => $m['avatar'],
                'join_date' => $m['created_at'],
                'last_seen' => $m['last_seen'],
                'department' => $m['dept_code'] ? [
                    'code' => $m['dept_code'],
                    'name' => $m['dept_name'],
                    'color' => $m['dept_color'],
                ] : null,
            ], $recentMembers),
            'recent_activity' => $formattedActivity,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'An error occurred',
    ]);
}
