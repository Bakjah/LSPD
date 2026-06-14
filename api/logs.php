<?php
/**
 * Logs API
 * Activity Logs & Login Logs
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/jwt.php';

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

    // Auth - require staff
    $token = getJWTFromHeader();
    $isStaff = false;
    if ($token) {
        $payload = verifyJWT($token);
        if ($payload) {
            $user = getUserWithRoles($payload['sub']);
            $isStaff = $user['is_staff'] ?? false;
        }
    }

    $type = $_GET['type'] ?? 'activity'; // activity, login
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    if ($type === 'login') {
        // Login logs (admin only)
        if (!$isStaff) {
            http_response_code(403);
            echo json_encode(['error' => 'Staff access required']);
            exit;
        }

        $where = ['1=1'];
        $params = [];

        // Filter by status
        if (isset($_GET['status']) && $_GET['status']) {
            $where[] = 'll.status = ?';
            $params[] = sanitize($_GET['status']);
        }

        // Filter by username
        if (isset($_GET['username']) && $_GET['username']) {
            $where[] = 'll.username LIKE ?';
            $params[] = '%' . sanitize($_GET['username']) . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Count
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_logs ll {$whereClause}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare("
            SELECT ll.*
            FROM login_logs ll
            {$whereClause}
            ORDER BY ll.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Stats
        $stmt = $db->query("
            SELECT status, COUNT(*) as count
            FROM login_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status
        ");
        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int) $row['count'];
        }
        $stmt->closeCursor();

        echo json_encode([
            'success' => true,
            'logs' => array_map(fn($l) => [
                'id' => (int) $l['id'],
                'user_id' => $l['user_id'] ? (int) $l['user_id'] : null,
                'username' => $l['username'],
                'email' => $l['email'],
                'status' => $l['status'],
                'ip_address' => $l['ip_address'],
                'reason' => $l['reason'],
                'created_at' => $l['created_at'],
            ], $logs),
            'stats' => $stats,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    } else {
        // Activity logs
        $where = ['1=1'];
        $params = [];

        // Filter by action
        if (isset($_GET['action']) && $_GET['action']) {
            $where[] = 'al.action = ?';
            $params[] = sanitize($_GET['action']);
        }

        // Filter by user
        if (isset($_GET['user']) && $_GET['user']) {
            $where[] = 'al.user_id = ?';
            $params[] = (int) $_GET['user'];
        }

        // Staff see all, users see only their own
        if (!$isStaff) {
            if ($token) {
                $payload = verifyJWT($token);
                if ($payload) {
                    $where[] = 'al.user_id = ?';
                    $params[] = $payload['sub'];
                } else {
                    $where[] = '1=0';
                }
            } else {
                $where[] = '1=0';
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Count
        $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs al {$whereClause}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare("
            SELECT al.*, u.username, u.avatar
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$whereClause}
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        echo json_encode([
            'success' => true,
            'logs' => array_map(fn($l) => [
                'id' => (int) $l['id'],
                'user_id' => $l['user_id'] ? (int) $l['user_id'] : null,
                'username' => $l['username'],
                'avatar' => $l['avatar'],
                'action' => $l['action'],
                'entity_type' => $l['entity_type'],
                'entity_id' => $l['entity_id'] ? (int) $l['entity_id'] : null,
                'details' => $l['details'],
                'ip_address' => $l['ip_address'],
                'created_at' => $l['created_at'],
            ], $logs),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'An error occurred',
    ]);
}
