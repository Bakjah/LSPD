<?php
/**
 * Admin API
 * User Management, System Settings, Department Management
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

    // Auth - require admin
    $authAdmin = function() use ($db) {
        $token = getJWTFromHeader();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        $payload = verifyJWT($token);
        if (!$payload || !($payload['is_admin'] ?? false)) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }
        return $payload;
    };

    // GET - Admin Dashboard
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'dashboard';

        switch ($action) {
            case 'dashboard':
                // Stats
                $stats = [];

                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND is_banned = 0");
                $stats['total_users'] = (int) $stmt->fetchColumn();
                $stmt->closeCursor();

                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_banned = 1");
                $stats['banned_users'] = (int) $stmt->fetchColumn();
                $stmt->closeCursor();

                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                $stats['online_users'] = (int) $stmt->fetchColumn();
                $stmt->closeCursor();

                $stmt = $db->query("SELECT COUNT(*) FROM topics WHERE is_deleted = 0");
                $stats['total_topics'] = (int) $stmt->fetchColumn();
                $stmt->closeCursor();

                $stmt = $db->query("SELECT COUNT(*) FROM posts WHERE is_deleted = 0");
                $stats['total_posts'] = (int) $stmt->fetchColumn();
                $stmt->closeCursor();

                $stmt = $db->query("SELECT COUNT(*) FROM departments WHERE is_active = 1");
                $stats['total_departments'] = (int) $stmt->fetchColumn();
                $stmt->closeCursor();

                // Recent registrations
                $stmt = $db->query("
                    SELECT u.id, u.uuid, u.username, u.email, u.created_at
                    FROM users u
                    WHERE u.is_active = 1
                    ORDER BY u.created_at DESC
                    LIMIT 10
                ");
                $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                // Recent activity
                $stmt = $db->query("
                    SELECT al.*, u.username
                    FROM activity_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    ORDER BY al.created_at DESC
                    LIMIT 20
                ");
                $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                echo json_encode([
                    'success' => true,
                    'stats' => $stats,
                    'recent_users' => array_map(fn($u) => [
                        'id' => (int) $u['id'],
                        'uuid' => $u['uuid'],
                        'username' => $u['username'],
                        'email' => $u['email'],
                        'created_at' => $u['created_at'],
                    ], $recentUsers),
                    'recent_activity' => array_map(fn($a) => [
                        'id' => (int) $a['id'],
                        'action' => $a['action'],
                        'details' => $a['details'],
                        'username' => $a['username'],
                        'created_at' => $a['created_at'],
                    ], $recentActivity),
                ]);
                break;

            case 'users':
                $page = max(1, (int) ($_GET['page'] ?? 1));
                $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
                $offset = ($page - 1) * $perPage;
                $search = isset($_GET['search']) ? sanitize($_GET['search']) : null;
                $status = $_GET['status'] ?? null;

                $where = ['1=1'];
                $params = [];

                if ($search) {
                    $where[] = '(u.username LIKE ? OR u.email LIKE ?)';
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                }

                if ($status === 'active') {
                    $where[] = 'u.is_active = 1 AND u.is_banned = 0';
                } elseif ($status === 'banned') {
                    $where[] = 'u.is_banned = 1';
                } elseif ($status === 'suspended') {
                    $where[] = 'u.is_suspended = 1';
                }

                $whereClause = 'WHERE ' . implode(' AND ', $where);

                $stmt = $db->prepare("SELECT COUNT(*) FROM users u {$whereClause}");
                $stmt->execute($params);
                $total = (int) $stmt->fetchColumn();
                $stmt->closeCursor();

                $params[] = $perPage;
                $params[] = $offset;

                $stmt = $db->prepare("
                    SELECT u.id, u.uuid, u.username, u.email, u.avatar, u.is_active, u.is_banned, u.is_suspended,
                           u.created_at, u.last_seen, u.login_count,
                           (SELECT GROUP_CONCAT(r.name SEPARATOR ', ')
                           FROM user_roles ur
                           JOIN roles r ON ur.role_id = r.id
                           WHERE ur.user_id = u.id
                           LIMIT 3) as roles
                    FROM users u
                    {$whereClause}
                    ORDER BY u.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute($params);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                echo json_encode([
                    'success' => true,
                    'users' => array_map(fn($u) => [
                        'id' => (int) $u['id'],
                        'uuid' => $u['uuid'],
                        'username' => $u['username'],
                        'email' => $u['email'],
                        'avatar' => $u['avatar'],
                        'is_active' => (bool) $u['is_active'],
                        'is_banned' => (bool) $u['is_banned'],
                        'is_suspended' => (bool) $u['is_suspended'],
                        'roles' => $u['roles'],
                        'created_at' => $u['created_at'],
                        'last_seen' => $u['last_seen'],
                        'login_count' => (int) $u['login_count'],
                    ], $users),
                    'pagination' => [
                        'total' => $total,
                        'page' => $page,
                        'per_page' => $perPage,
                        'total_pages' => ceil($total / $perPage),
                    ],
                ]);
                break;

            case 'departments':
                $stmt = $db->query("
                    SELECT d.*,
                           (SELECT COUNT(DISTINCT ud.user_id) FROM user_departments ud WHERE ud.department_id = d.id) as member_count
                    FROM departments d
                    ORDER BY d.sort_order ASC
                ");
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                echo json_encode([
                    'success' => true,
                    'departments' => array_map(fn($d) => [
                        'id' => (int) $d['id'],
                        'uuid' => $d['uuid'],
                        'code' => $d['code'],
                        'name' => $d['name'],
                        'slug' => $d['slug'],
                        'description' => $d['description'],
                        'color' => $d['color'],
                        'icon' => $d['icon'],
                        'is_active' => (bool) $d['is_active'],
                        'member_count' => (int) $d['member_count'],
                    ], $departments),
                ]);
                break;

            case 'settings':
                $stmt = $db->query("SELECT `key`, `value`, `type`, `group` FROM settings ORDER BY `group`, `key`");
                $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                // Group by group
                $grouped = [];
                foreach ($settings as $s) {
                    $g = $s['group'];
                    if (!isset($grouped[$g])) {
                        $grouped[$g] = [];
                    }
                    $grouped[$g][] = [
                        'key' => $s['key'],
                        'value' => $s['value'],
                        'type' => $s['type'],
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'settings' => $grouped,
                ]);
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Action not found']);
        }
        exit;
    }

    // POST - Create/Update actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $authAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'ban':
                if (empty($input['user_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE users SET is_banned = 1, banned_reason = ?, banned_at = NOW() WHERE id = ?");
                $stmt->execute([$input['reason'] ?? 'Banned by admin', $input['user_id']]);
                $stmt->closeCursor();

                echo json_encode(['success' => true, 'message' => 'User banned']);
                break;

            case 'unban':
                if (empty($input['user_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE users SET is_banned = 0, banned_reason = NULL, banned_at = NULL WHERE id = ?");
                $stmt->execute([$input['user_id']]);
                $stmt->closeCursor();

                echo json_encode(['success' => true, 'message' => 'User unbanned']);
                break;

            case 'suspend':
                if (empty($input['user_id']) || empty($input['until'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id and until required']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE users SET is_suspended = 1, suspended_until = ?, suspended_reason = ? WHERE id = ?");
                $stmt->execute([$input['until'], $input['reason'] ?? 'Suspended', $input['user_id']]);
                $stmt->closeCursor();

                echo json_encode(['success' => true, 'message' => 'User suspended']);
                break;

            case 'unsuspend':
                if (empty($input['user_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }

                $stmt = $db->prepare("UPDATE users SET is_suspended = 0, suspended_until = NULL, suspended_reason = NULL WHERE id = ?");
                $stmt->execute([$input['user_id']]);
                $stmt->closeCursor();

                echo json_encode(['success' => true, 'message' => 'User unsuspended']);
                break;

            case 'delete':
                if (empty($input['user_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }

                // Soft delete - deactivate
                $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                $stmt->execute([$input['user_id']]);
                $stmt->closeCursor();

                echo json_encode(['success' => true, 'message' => 'User deactivated']);
                break;

            case 'update_setting':
                if (empty($input['key']) || !isset($input['value'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'key and value required']);
                    exit;
                }

                $key = sanitize($input['key']);
                $value = $input['value'];
                $type = gettype($value);

                if ($type === 'boolean') {
                    $value = $value ? '1' : '0';
                    $type = 'bool';
                } elseif ($type === 'integer') {
                    $type = 'int';
                } else {
                    $type = 'string';
                    $value = (string) $value;
                }

                $stmt = $db->prepare("UPDATE settings SET `value` = ?, `type` = ?, updated_at = NOW() WHERE `key` = ?");
                $stmt->execute([$value, $type, $key]);
                $stmt->closeCursor();

                echo json_encode(['success' => true, 'message' => 'Setting updated']);
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Action not found']);
        }
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
