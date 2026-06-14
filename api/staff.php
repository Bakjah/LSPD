<?php
/**
 * Staff Directory API
 * GET /api/staff.php - Get staff roster
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/database.php';

header('Content-Type: application/json; charset=utf-8');
// CORS handled by applyCORS()
header('Access-Control-Allow-Methods: GET, OPTIONS');
// CORS handled by applyCORS()

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();

    $department = isset($_GET['department']) ? strtoupper(sanitize($_GET['department'])) : null;

    // Get departments
    $deptWhere = '';
    $deptParams = [];
    if ($department) {
        $deptWhere = 'WHERE code = ?';
        $deptParams[] = $department;
    }

    $stmt = $db->prepare("
        SELECT id, code, name, slug, color, icon
        FROM departments
        {$deptWhere}
        ORDER BY sort_order ASC
    ");
    $stmt->execute($deptParams);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $result = [];

    foreach ($departments as $dept) {
        // Get leadership (is_leader = 1)
        $stmt = $db->prepare("
            SELECT u.uuid, u.username, u.avatar, u.last_seen,
                   r.name as role_name, r.badge, r.color, r.hierarchy
            FROM user_roles ur
            JOIN users u ON ur.user_id = u.id
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.department_id = ? AND r.is_leader = 1
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            ORDER BY r.hierarchy ASC, u.username ASC
        ");
        $stmt->execute([$dept['id']]);
        $leadership = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Get all staff (is_staff = 1)
        $stmt = $db->prepare("
            SELECT u.uuid, u.username, u.avatar, u.last_seen,
                   r.name as role_name, r.badge, r.color, r.hierarchy, r.is_leader
            FROM user_roles ur
            JOIN users u ON ur.user_id = u.id
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.department_id = ? AND (r.is_staff = 1 OR r.is_leader = 1)
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            ORDER BY r.hierarchy ASC, u.username ASC
        ");
        $stmt->execute([$dept['id']]);
        $allStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Group by hierarchy
        $hierarchyGroups = [];
        foreach ($allStaff as $staff) {
            $key = (int) $staff['hierarchy'];
            if (!isset($hierarchyGroups[$key])) {
                $hierarchyGroups[$key] = [
                    'role' => [
                        'name' => $staff['role_name'],
                        'badge' => $staff['badge'],
                        'color' => $staff['color'],
                        'is_leader' => (bool) $staff['is_leader'],
                    ],
                    'members' => [],
                ];
            }
            $hierarchyGroups[$key]['members'][] = [
                'uuid' => $staff['uuid'],
                'username' => $staff['username'],
                'avatar' => $staff['avatar'],
                'last_seen' => $staff['last_seen'],
                'is_leader' => (bool) $staff['is_leader'],
            ];
        }

        ksort($hierarchyGroups);

        $result[] = [
            'department' => [
                'id' => (int) $dept['id'],
                'code' => $dept['code'],
                'name' => $dept['name'],
                'slug' => $dept['slug'],
                'color' => $dept['color'],
                'icon' => $dept['icon'],
            ],
            'leadership' => array_map(function($l) {
                return [
                    'uuid' => $l['uuid'],
                    'username' => $l['username'],
                    'avatar' => $l['avatar'],
                    'last_seen' => $l['last_seen'],
                    'role' => $l['role_name'],
                    'badge' => $l['badge'],
                    'color' => $l['color'],
                ];
            }, $leadership),
            'roster' => array_values($hierarchyGroups),
            'total_staff' => count($allStaff),
        ];
    }

    echo json_encode([
        'success' => true,
        'staff_directory' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'An error occurred',
    ]);
}
