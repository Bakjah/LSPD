<?php
/**
 * Portal Module
 * Community Portal Homepage API
 */

require_once __DIR__ . '/../../helpers/database.php';
require_once __DIR__ . '/../../helpers/jwt.php';
require_once __DIR__ . '/../../helpers/logging.php';

/**
 * Handle portal routes
 */
function handleRoute(string $method, string $action, ?string $id): void
{
    switch ($action) {
        case 'stats':
            if ($method === 'GET') {
                getStats();
            }
            break;

        case 'departments':
            if ($method === 'GET') {
                getDepartments();
            }
            break;

        case 'activity':
            if ($method === 'GET') {
                getRecentActivity();
            }
            break;

        case 'members':
            if ($method === 'GET') {
                getRecentMembers();
            }
            break;

        default:
            if ($method === 'GET') {
                getHomepage();
            }
            break;
    }
}

/**
 * GET /api/portal
 * Get full homepage data
 */
function getHomepage(): void
{
    $db = getDB();

    // Get overall stats
    $stats = getPortalStats($db);

    // Get departments
    $departments = getDepartmentsData($db);

    // Get recent members
    $recentMembers = getRecentMembersData($db, 8);

    // Get recent activity
    $recentActivity = getRecentActivityData($db, 10);

    jsonResponse([
        'success' => true,
        'portal' => [
            'name' => getSetting('site_name', 'Los Santos Roleplay Community'),
            'tagline' => getSetting('site_tagline', 'To Protect and Serve'),
            'stats' => $stats,
            'departments' => $departments,
            'recent_members' => $recentMembers,
            'recent_activity' => $recentActivity,
        ],
    ]);
}

/**
 * GET /api/portal/stats
 * Get portal statistics
 */
function getStats(): void
{
    $db = getDB();
    $stats = getPortalStats($db);

    jsonResponse([
        'success' => true,
        'stats' => $stats,
    ]);
}

/**
 * GET /api/portal/departments
 * Get all departments with stats
 */
function getDepartments(): void
{
    $db = getDB();
    $departments = getDepartmentsData($db);

    jsonResponse([
        'success' => true,
        'departments' => $departments,
    ]);
}

/**
 * GET /api/portal/activity
 * Get recent activity
 */
function getRecentActivity(): void
{
    $db = getDB();
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
    $activity = getRecentActivityData($db, $limit);

    jsonResponse([
        'success' => true,
        'activity' => $activity,
    ]);
}

/**
 * GET /api/portal/members
 * Get recent members
 */
function getRecentMembers(): void
{
    $db = getDB();
    $limit = min(24, max(1, (int) ($_GET['limit'] ?? 8)));
    $members = getRecentMembersData($db, $limit);

    jsonResponse([
        'success' => true,
        'members' => $members,
    ]);
}

/**
 * Get portal statistics
 */
function getPortalStats(PDO $db): array
{
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND is_banned = 0");
    $stmt->execute();
    $totalUsers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // Online users (seen in last 15 minutes)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND is_active = 1");
    $stmt->execute();
    $onlineUsers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // New users this week
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_active = 1");
    $stmt->execute();
    $newUsersWeek = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // Total departments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
    $stmt->execute();
    $totalDepts = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // Department breakdown
    $stmt = $db->prepare("
        SELECT d.code, d.name, d.color, COUNT(DISTINCT ud.user_id) as member_count
        FROM departments d
        LEFT JOIN user_departments ud ON d.id = ud.department_id
        WHERE d.is_active = 1
        GROUP BY d.id, d.code, d.name, d.color
        ORDER BY d.sort_order ASC
    ");
    $stmt->execute();
    $deptBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    return [
        'total_users' => $totalUsers,
        'online_users' => $onlineUsers,
        'new_users_week' => $newUsersWeek,
        'total_departments' => $totalDepts,
        'department_breakdown' => $deptBreakdown,
    ];
}

/**
 * Get departments data
 */
function getDepartmentsData(PDO $db): array
{
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

    $formatted = [];

    foreach ($departments as $dept) {
        // Get leaders
        $stmt = $db->prepare("
            SELECT u.uuid, u.username, u.avatar, r.name as role_name, r.badge, r.color
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

        $formatted[] = [
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
                'color' => $l['color'],
            ], $leaders),
        ];
    }

    return $formatted;
}

/**
 * Get recent members data
 */
function getRecentMembersData(PDO $db, int $limit): array
{
    $stmt = $db->prepare("
        SELECT u.uuid, u.username, u.avatar, u.created_at, u.last_seen,
               d.code as primary_dept_code, d.name as primary_dept_name, d.color as primary_dept_color
        FROM users u
        LEFT JOIN user_departments ud ON u.id = ud.user_id AND ud.is_primary = 1
        LEFT JOIN departments d ON ud.department_id = d.id
        WHERE u.is_active = 1 AND u.is_banned = 0
        ORDER BY u.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    return array_map(fn($m) => [
        'uuid' => $m['uuid'],
        'username' => $m['username'],
        'avatar' => $m['avatar'],
        'join_date' => $m['created_at'],
        'last_seen' => $m['last_seen'],
        'primary_department' => $m['primary_dept_code'] ? [
            'code' => $m['primary_dept_code'],
            'name' => $m['primary_dept_name'],
            'color' => $m['primary_dept_color'],
        ] : null,
    ], $members);
}

/**
 * Get recent activity data
 */
function getRecentActivityData(PDO $db, int $limit): array
{
    $stmt = $db->prepare("
        SELECT al.id, al.action, al.entity_type, al.entity_id, al.details, al.created_at,
               u.uuid as user_uuid, u.username as user_username, u.avatar as user_avatar
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formatted = [];

    foreach ($activities as $a) {
        $actionLabels = [
            'user_register' => 'joined the community',
            'user_login' => 'came online',
            'user_logout' => 'went offline',
            'profile_update' => 'updated their profile',
            'avatar_update' => 'changed their avatar',
            'role_assigned' => 'was promoted',
            'role_removed' => 'role was updated',
            'password_change' => 'changed their password',
        ];

        $formatted[] = [
            'id' => (int) $a['id'],
            'action' => $a['action'],
            'label' => $actionLabels[$a['action']] ?? $a['action'],
            'entity_type' => $a['entity_type'],
            'entity_id' => $a['entity_id'],
            'details' => $a['details'],
            'created_at' => $a['created_at'],
            'user' => $a['user_uuid'] ? [
                'uuid' => $a['user_uuid'],
                'username' => $a['user_username'],
                'avatar' => $a['user_avatar'],
            ] : null,
        ];
    }

    return $formatted;
}
