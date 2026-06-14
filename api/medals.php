<?php
/**
 * Medals API
 * GET /api/medals.php - List medals
 * POST /api/medals.php - Award medal (leader only)
 * DELETE /api/medals.php - Remove medal (leader only)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/logging.php';

header('Content-Type: application/json; charset=utf-8');
// CORS handled by applyCORS()
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
// CORS handled by applyCORS()

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $db = getDB();

    // Auth helper
    $authUser = function() use ($db) {
        $token = getJWTFromHeader();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }

        $payload = verifyJWT($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        $user = getUserWithRoles($payload['sub']);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        return $user;
    };

    // Check if user is department leader
    $isLeader = function($user, $departmentId = null) use ($db) {
        foreach ($user['departments'] as $dept) {
            if ($departmentId && $dept['id'] !== $departmentId) continue;
            foreach ($dept['roles'] as $role) {
                if ($role['is_leader']) return true;
            }
        }
        return false;
    };

    // GET - List medals
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $department = isset($_GET['department']) ? (int) $_GET['department'] : null;
        $userUuid = isset($_GET['user']) ? sanitize($_GET['user']) : null;

        // If querying by user, show their medals
        if ($userUuid) {
            $stmt = $db->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmt->execute([$userUuid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$user) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                exit;
            }

            $stmt = $db->prepare("
                SELECT m.*, um.granted_at, um.reason,
                       g.username as granted_by_username
                FROM medals m
                JOIN user_medals um ON m.id = um.medal_id
                LEFT JOIN users g ON um.granted_by = g.id
                WHERE um.user_id = ?
                ORDER BY um.granted_at DESC
            ");
            $stmt->execute([$user['id']]);
            $medals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            echo json_encode([
                'success' => true,
                'user_uuid' => $userUuid,
                'medals' => array_map(fn($m) => [
                    'id' => (int) $m['id'],
                    'name' => $m['name'],
                    'description' => $m['description'],
                    'icon' => $m['icon'],
                    'color' => $m['color'],
                    'type' => $m['type'],
                    'granted_at' => $m['granted_at'],
                    'reason' => $m['reason'],
                    'granted_by' => $m['granted_by_username'],
                ], $medals),
            ]);
            exit;
        }

        // List all medals
        $where = [];
        $params = [];

        if ($department) {
            $where[] = 'm.department_id = ?';
            $params[] = $department;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $db->prepare("
            SELECT m.*, d.name as department_name, d.code as department_code, d.color as department_color,
                   (SELECT COUNT(*) FROM user_medals WHERE medal_id = m.id) as award_count
            FROM medals m
            LEFT JOIN departments d ON m.department_id = d.id
            {$whereClause}
            ORDER BY m.type ASC, m.sort_order ASC
        ");
        $stmt->execute($params);
        $medals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Group by type
        $grouped = [];
        foreach ($medals as $medal) {
            $type = $medal['type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [
                    'type' => $type,
                    'name' => ucfirst(str_replace('_', ' ', $type)),
                    'medals' => [],
                ];
            }
            $grouped[$type]['medals'][] = [
                'id' => (int) $medal['id'],
                'uuid' => $medal['uuid'],
                'name' => $medal['name'],
                'description' => $medal['description'],
                'icon' => $medal['icon'],
                'color' => $medal['color'],
                'department' => $medal['department_id'] ? [
                    'name' => $medal['department_name'],
                    'code' => $medal['department_code'],
                    'color' => $medal['department_color'],
                ] : null,
                'award_count' => (int) $medal['award_count'],
            ];
        }

        echo json_encode([
            'success' => true,
            'medals' => array_values($grouped),
        ]);
        exit;
    }

    // POST - Award medal
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $authUser();

        // Check if user is admin or leader
        $isAdmin = $user['is_admin'] ?? false;
        $isDeptLeader = $isLeader($user);

        if (!$isAdmin && !$isDeptLeader) {
            http_response_code(403);
            echo json_encode(['error' => 'Only administrators or department leaders can award medals']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }

        $errors = [];
        if (empty($input['user_id'])) $errors[] = 'user_id is required';
        if (empty($input['medal_id'])) $errors[] = 'medal_id is required';

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
            exit;
        }

        $targetUserId = (int) $input['user_id'];
        $medalId = (int) $input['medal_id'];
        $reason = isset($input['reason']) ? sanitize($input['reason']) : null;

        // Check target user exists
        $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Check medal exists
        $stmt = $db->prepare("SELECT id, name FROM medals WHERE id = ?");
        $stmt->execute([$medalId]);
        $medal = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$medal) {
            http_response_code(404);
            echo json_encode(['error' => 'Medal not found']);
            exit;
        }

        // Check if already awarded
        $stmt = $db->prepare("SELECT id FROM user_medals WHERE user_id = ? AND medal_id = ?");
        $stmt->execute([$targetUserId, $medalId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Medal already awarded to this user']);
            $stmt->closeCursor();
            exit;
        }
        $stmt->closeCursor();

        // Award medal
        $stmt = $db->prepare("
            INSERT INTO user_medals (user_id, medal_id, granted_by, reason, granted_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$targetUserId, $medalId, $user['id'], $reason]);
        $awardId = $db->lastInsertId();
        $stmt->closeCursor();

        // Log activity
        logActivity($user['id'], 'medal_awarded', 'medal', $awardId, "Awarded {$medal['name']} to {$targetUser['username']}");

        echo json_encode([
            'success' => true,
            'message' => 'Medal awarded successfully',
            'award' => [
                'id' => (int) $awardId,
                'user' => $targetUser['username'],
                'medal' => $medal['name'],
            ],
        ], 201);
        exit;
    }

    // DELETE - Remove medal
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $user = $authUser();

        // Check if user is admin or leader
        $isAdmin = $user['is_admin'] ?? false;
        $isDeptLeader = $isLeader($user);

        if (!$isAdmin && !$isDeptLeader) {
            http_response_code(403);
            echo json_encode(['error' => 'Only administrators or department leaders can remove medals']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['user_id']) || empty($input['medal_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id and medal_id required']);
            exit;
        }

        $targetUserId = (int) $input['user_id'];
        $medalId = (int) $input['medal_id'];

        $stmt = $db->prepare("DELETE FROM user_medals WHERE user_id = ? AND medal_id = ?");
        $stmt->execute([$targetUserId, $medalId]);
        $stmt->closeCursor();

        logActivity($user['id'], 'medal_removed', 'medal', $medalId, "Removed medal from user {$targetUserId}");

        echo json_encode([
            'success' => true,
            'message' => 'Medal removed successfully',
        ]);
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
