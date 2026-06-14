<?php
/**
 * Departments Module
 * Department endpoints
 */

require_once __DIR__ . '/../../helpers/database.php';
require_once __DIR__ . '/../../helpers/jwt.php';
require_once __DIR__ . '/../../helpers/logging.php';

/**
 * Handle departments routes
 */
function handleRoute(string $method, string $action, ?string $id): void
{
    switch ($action) {
        case 'index':
        case '':
            if ($method === 'GET') {
                listDepartments();
            }
            break;

        case 'list':
            if ($method === 'GET') {
                listDepartments();
            }
            break;

        case 'members':
            if ($method === 'GET' && $id) {
                getDepartmentMembers($id);
            }
            break;

        case 'staff':
            if ($method === 'GET' && $id) {
                getDepartmentStaff($id);
            }
            break;

        case 'stats':
            if ($method === 'GET' && $id) {
                getDepartmentStats($id);
            }
            break;

        default:
            if ($method === 'GET' && is_numeric($action)) {
                getDepartment($action);
            } elseif ($method === 'GET' && $action) {
                getDepartment($action);
            } else {
                listDepartments();
            }
            break;
    }
}

/**
 * GET /api/departments
 * List all departments
 */
function listDepartments(): void
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, uuid, code, name, slug, description, color, icon, logo, banner, sort_order, is_active
        FROM departments
        WHERE is_active = 1
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formatted = [];

    foreach ($departments as $dept) {
        // Get member count
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM user_departments WHERE department_id = ?");
        $stmt->execute([$dept['id']]);
        $memberCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $stmt->closeCursor();

        // Get leader info
        $stmt = $db->prepare("
            SELECT u.username, u.avatar, r.name as role_name, r.badge
            FROM user_roles ur
            JOIN users u ON ur.user_id = u.id
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.department_id = ? AND r.is_leader = 1
            LIMIT 1
        ");
        $stmt->execute([$dept['id']]);
        $leader = $stmt->fetch(PDO::FETCH_ASSOC);
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
            'member_count' => $memberCount,
            'leader' => $leader ? [
                'username' => $leader['username'],
                'avatar' => $leader['avatar'],
                'role' => $leader['role_name'],
                'badge' => $leader['badge'],
            ] : null,
        ];
    }

    jsonResponse([
        'success' => true,
        'departments' => $formatted,
    ]);
}

/**
 * GET /api/departments/:slug
 * Get single department
 */
function getDepartment(string $identifier): void
{
    $db = getDB();

    // Find by slug or ID
    if (is_numeric($identifier)) {
        $stmt = $db->prepare("
            SELECT id, uuid, code, name, slug, description, color, icon, logo, banner, sort_order, is_active
            FROM departments
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$identifier]);
    } else {
        $stmt = $db->prepare("
            SELECT id, uuid, code, name, slug, description, color, icon, logo, banner, sort_order, is_active
            FROM departments
            WHERE slug = ? AND is_active = 1
        ");
        $stmt->execute([$identifier]);
    }

    $dept = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$dept) {
        errorResponse('Department not found', 404);
    }

    // Get member count
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM user_departments WHERE department_id = ?");
    $stmt->execute([$dept['id']]);
    $memberCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // Get all leaders
    $stmt = $db->prepare("
        SELECT u.uuid, u.username, u.avatar, r.name as role_name, r.badge, r.color
        FROM user_roles ur
        JOIN users u ON ur.user_id = u.id
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.department_id = ? AND r.is_leader = 1
        ORDER BY r.hierarchy ASC
    ");
    $stmt->execute([$dept['id']]);
    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formattedLeaders = [];
    foreach ($leaders as $leader) {
        $formattedLeaders[] = [
            'uuid' => $leader['uuid'],
            'username' => $leader['username'],
            'avatar' => $leader['avatar'],
            'role' => $leader['role_name'],
            'badge' => $leader['badge'],
            'color' => $leader['color'],
        ];
    }

    jsonResponse([
        'success' => true,
        'department' => [
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
            'member_count' => $memberCount,
            'leaders' => $formattedLeaders,
        ],
    ]);
}

/**
 * GET /api/departments/:id/members
 * Get department members
 */
function getDepartmentMembers(string $identifier): void
{
    $db = getDB();

    // Find department
    $deptId = is_numeric($identifier)
        ? (int) $identifier
        : getDepartmentIdBySlug($identifier);

    if (!$deptId) {
        errorResponse('Department not found', 404);
    }

    $params = getPaginationParams();
    $role = sanitize($_GET['role'] ?? '');
    $search = sanitize($_GET['search'] ?? '');

    $where = ['ud.department_id = ?'];
    $sqlParams = [$deptId];

    if ($role) {
        $where[] = 'r.slug = ?';
        $sqlParams[] = $role;
    }

    if ($search) {
        $where[] = 'u.username LIKE ?';
        $sqlParams[] = '%' . $search . '%';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Get total count
    $countSql = "
        SELECT COUNT(DISTINCT u.id) as total
        FROM users u
        JOIN user_departments ud ON u.id = ud.user_id
        LEFT JOIN user_roles ur ON u.id = ur.user_id AND ur.department_id = ?
        LEFT JOIN roles r ON ur.role_id = r.id
        {$whereClause}
    ";
    $stmt = $db->prepare($countSql);
    $stmt->execute(array_merge([$deptId], $sqlParams));
    $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt->closeCursor();

    // Get members
    $sql = "
        SELECT DISTINCT u.id, u.uuid, u.username, u.avatar, u.last_seen, u.created_at,
               r.name as role_name, r.slug as role_slug, r.color as role_color, r.badge as role_badge,
               r.hierarchy as role_hierarchy
        FROM users u
        JOIN user_departments ud ON u.id = ud.user_id
        LEFT JOIN user_roles ur ON u.id = ur.user_id AND ur.department_id = ?
        LEFT JOIN roles r ON ur.role_id = r.id
        {$whereClause}
        ORDER BY r.hierarchy ASC, u.username ASC
        LIMIT ? OFFSET ?
    ";

    $sqlParams[] = $params['per_page'];
    $sqlParams[] = $params['offset'];

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$deptId], $sqlParams));
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formatted = [];
    foreach ($members as $m) {
        $formatted[] = [
            'id' => (int) $m['id'],
            'uuid' => $m['uuid'],
            'username' => $m['username'],
            'avatar' => $m['avatar'],
            'last_seen' => $m['last_seen'],
            'join_date' => $m['created_at'],
            'role' => $m['role_name'] ? [
                'name' => $m['role_name'],
                'slug' => $m['role_slug'],
                'color' => $m['role_color'],
                'badge' => $m['role_badge'],
            ] : null,
        ];
    }

    paginatedResponse($formatted, $total, $params['page'], $params['per_page']);
}

/**
 * GET /api/departments/:id/staff
 * Get department staff/leadership
 */
function getDepartmentStaff(string $identifier): void
{
    $db = getDB();

    // Find department
    $deptId = is_numeric($identifier)
        ? (int) $identifier
        : getDepartmentIdBySlug($identifier);

    if (!$deptId) {
        errorResponse('Department not found', 404);
    }

    // Get all staff members (staff flag or leader)
    $stmt = $db->prepare("
        SELECT u.uuid, u.username, u.avatar, u.last_seen,
               r.id as role_id, r.uuid as role_uuid, r.name as role_name, r.slug as role_slug,
               r.color as role_color, r.badge as role_badge, r.is_leader, r.hierarchy
        FROM user_roles ur
        JOIN users u ON ur.user_id = u.id
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.department_id = ? AND (r.is_staff = 1 OR r.is_leader = 1)
        AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY r.hierarchy ASC, u.username ASC
    ");
    $stmt->execute([$deptId]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Group by role hierarchy
    $grouped = [];
    foreach ($staff as $s) {
        $hierarchy = $s['hierarchy'];
        if (!isset($grouped[$hierarchy])) {
            $grouped[$hierarchy] = [
                'role' => [
                    'id' => (int) $s['role_id'],
                    'uuid' => $s['role_uuid'],
                    'name' => $s['role_name'],
                    'slug' => $s['role_slug'],
                    'color' => $s['role_color'],
                    'badge' => $s['role_badge'],
                    'is_leader' => (bool) $s['is_leader'],
                ],
                'members' => [],
            ];
        }

        $grouped[$hierarchy]['members'][] = [
            'uuid' => $s['uuid'],
            'username' => $s['username'],
            'avatar' => $s['avatar'],
            'last_seen' => $s['last_seen'],
        ];
    }

    // Sort by hierarchy
    ksort($grouped);

    jsonResponse([
        'success' => true,
        'staff' => array_values($grouped),
    ]);
}

/**
 * GET /api/departments/:id/stats
 * Get department statistics
 */
function getDepartmentStats(string $identifier): void
{
    $db = getDB();

    // Find department
    $deptId = is_numeric($identifier)
        ? (int) $identifier
        : getDepartmentIdBySlug($identifier);

    if (!$deptId) {
        errorResponse('Department not found', 404);
    }

    // Total members
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM user_departments WHERE department_id = ?");
    $stmt->execute([$deptId]);
    $totalMembers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // Active members (seen in last 7 days)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ud.user_id) as count
        FROM user_departments ud
        JOIN users u ON ud.user_id = u.id
        WHERE ud.department_id = ? AND u.last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$deptId]);
    $activeMembers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    // Role distribution
    $stmt = $db->prepare("
        SELECT r.name, r.color, COUNT(DISTINCT ur.user_id) as count
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.department_id = ? AND r.type = 'department'
        GROUP BY r.id, r.name, r.color
        ORDER BY count DESC
    ");
    $stmt->execute([$deptId]);
    $roleDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    jsonResponse([
        'success' => true,
        'stats' => [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'role_distribution' => $roleDistribution,
        ],
    ]);
}

/**
 * Helper: Get department ID by slug
 */
function getDepartmentIdBySlug(string $slug): ?int
{
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM departments WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    return $result ? (int) $result['id'] : null;
}