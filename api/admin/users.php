<?php
/**
 * Admin Users API — Full user management
 */
require_once __DIR__ . '/config.php';

$db = getDB();
$user = requireAdmin();

// GET: List users
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $perPage  = 20;
    $offset   = ($page - 1) * $perPage;
    $search   = trim($_GET['search'] ?? '');
    $role     = trim($_GET['role'] ?? '');
    $dept     = trim($_GET['dept'] ?? '');
    $status   = $_GET['status'] ?? ''; // active, banned, suspended

    $conditions = "1=1";
    $params = [];
    $types = '';

    if ($search) {
        $conditions .= " AND (u.username LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    if ($status === 'banned') {
        $conditions .= " AND u.is_banned = 1";
    } elseif ($status === 'suspended') {
        $conditions .= " AND u.is_suspended = 1";
    } elseif ($status === 'active') {
        $conditions .= " AND u.is_active = 1 AND u.is_banned = 0";
    }

    if ($dept) {
        $conditions .= " AND d.code = ?";
        $params[] = $dept;
        $types .= 's';
    }

    $countSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE $conditions";
    $countStmt = $db->prepare($countSql);
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $usersSql = "
        SELECT u.id, u.uuid, u.username, u.email, u.avatar, u.is_active, u.is_banned, u.is_suspended,
               u.total_threads, u.total_posts, u.join_date, u.last_seen, u.is_verified,
               r.name as rank_name, r.color as rank_color, r.badge as rank_badge,
               d.name as dept_name, d.code as dept_code
        FROM users u
        LEFT JOIN roles r ON u.rank_id = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE $conditions
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';
    $usersStmt = $db->prepare($usersSql);
    $usersStmt->bind_param($types, ...$params);
    $usersStmt->execute();
    $usersResult = $usersStmt->get_result();

    $users = [];
    while ($u = $usersResult->fetch_assoc()) {
        $users[] = [
            'id'           => (int) $u['id'],
            'uuid'         => $u['uuid'],
            'username'     => $u['username'],
            'email'        => $u['email'],
            'avatar'       => $u['avatar'],
            'is_active'    => (bool) $u['is_active'],
            'is_banned'    => (bool) $u['is_banned'],
            'is_suspended' => (bool) $u['is_suspended'],
            'is_verified'  => (bool) $u['is_verified'],
            'total_threads'=> (int) $u['total_threads'],
            'total_posts'  => (int) $u['total_posts'],
            'join_date'    => $u['join_date'],
            'last_seen'    => $u['last_seen'],
            'rank'         => $u['rank_name'],
            'rank_color'   => $u['rank_color'],
            'rank_badge'   => $u['rank_badge'],
            'dept'         => $u['dept_name'],
            'dept_code'    => $u['dept_code'],
        ];
    }
    $usersStmt->close();

    // Stats
    $stats = [
        'total'    => (int) $db->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'],
        'active'   => (int) $db->query("SELECT COUNT(*) as c FROM users WHERE is_active=1 AND is_banned=0")->fetch_assoc()['c'],
        'banned'   => (int) $db->query("SELECT COUNT(*) as c FROM users WHERE is_banned=1")->fetch_assoc()['c'],
        'suspended'=> (int) $db->query("SELECT COUNT(*) as c FROM users WHERE is_suspended=1")->fetch_assoc()['c'],
        'threads'  => (int) $db->query("SELECT COUNT(*) as c FROM threads WHERE is_deleted=0")->fetch_assoc()['c'],
        'posts'    => (int) $db->query("SELECT COUNT(*) as c FROM posts WHERE is_deleted=0")->fetch_assoc()['c'],
    ];

    jsonResponse([
        'users'      => $users,
        'stats'      => $stats,
        'pagination' => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => ceil($total / $perPage),
        ],
    ]);
}

// POST: Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $rankName = trim($input['rank'] ?? '');
    $deptCode = trim($input['department'] ?? '');

    $valid = validateUsername($username);
    if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);

    $valid = validateEmail($email);
    if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);

    $valid = validatePassword($password);
    if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);

    // Check uniqueness
    $check = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->bind_param('ss', $username, $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) jsonResponse(['error' => 'Username or email already exists'], 409);
    $check->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $uuid = generateUUID();

    // Get rank and dept IDs
    $rankId = null;
    $deptId = null;
    if ($rankName) {
        $r = $db->query("SELECT id FROM roles WHERE name='" . $db->real_escape_string($rankName) . "' LIMIT 1")->fetch_assoc();
        $rankId = $r['id'] ?? null;
    }
    if ($deptCode) {
        $d = $db->query("SELECT id FROM departments WHERE code='" . $db->real_escape_string($deptCode) . "' LIMIT 1")->fetch_assoc();
        $deptId = $d['id'] ?? null;
    }

    $stmt = $db->prepare("INSERT INTO users (uuid, username, email, password, rank_id, department_id, join_date, last_seen, is_verified) VALUES (?,?,?,?,?,?,NOW(),NOW(),1)");
    $stmt->bind_param('ssssii', $uuid, $username, $email, $hash, $rankId, $deptId);
    $stmt->execute();
    $newUserId = $stmt->insert_id;
    $stmt->close();

    if ($rankId) {
        $roleStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?,?)");
        $roleStmt->bind_param('ii', $newUserId, $rankId);
        $roleStmt->execute();
        $roleStmt->close();
    }

    logActivity($user['id'], 'create_user', "Created user: $username (ID: $newUserId)");

    jsonResponse(['message' => 'User created successfully', 'user_id' => $newUserId], 201);
}

// PUT: Update user
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $targetId = (int) ($_GET['id'] ?? 0);
    if (!$targetId) jsonResponse(['error' => 'User ID is required'], 400);

    $input = json_decode(file_get_contents('php://input'), true);

    $fields = [];
    $params = [];
    $types  = '';

    if (isset($input['username'])) {
        $valid = validateUsername($input['username']);
        if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);
        $fields[] = 'username = ?';
        $params[] = trim($input['username']);
        $types .= 's';
    }

    if (isset($input['email'])) {
        $valid = validateEmail($input['email']);
        if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);
        $fields[] = 'email = ?';
        $params[] = trim($input['email']);
        $types .= 's';
    }

    if (isset($input['password']) && $input['password']) {
        $valid = validatePassword($input['password']);
        if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);
        $fields[] = 'password = ?';
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
        $types .= 's';
    }

    if (isset($input['rank'])) {
        $r = $db->query("SELECT id FROM roles WHERE name='" . $db->real_escape_string($input['rank']) . "' LIMIT 1")->fetch_assoc();
        $fields[] = 'rank_id = ?';
        $params[] = $r['id'] ?? null;
        $types .= 'i';
    }

    if (isset($input['department'])) {
        $d = $db->query("SELECT id FROM departments WHERE code='" . $db->real_escape_string($input['department']) . "' LIMIT 1")->fetch_assoc();
        $fields[] = 'department_id = ?';
        $params[] = $d['id'] ?? null;
        $types .= 'i';
    }

    if (isset($input['is_active'])) {
        $fields[] = 'is_active = ?';
        $params[] = $input['is_active'] ? 1 : 0;
        $types .= 'i';
    }

    if (empty($fields)) jsonResponse(['error' => 'No fields to update'], 400);

    $params[] = $targetId;
    $types .= 'i';

    $stmt = $db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    // Update role assignment if rank changed
    if (isset($input['rank'])) {
        $r = $db->query("SELECT id FROM roles WHERE name='" . $db->real_escape_string($input['rank']) . "' LIMIT 1")->fetch_assoc();
        if ($r) {
            $db->query("DELETE FROM user_roles WHERE user_id = $targetId");
            $roleStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?,?)");
            $roleStmt->bind_param('ii', $targetId, $r['id']);
            $roleStmt->execute();
            $roleStmt->close();
        }
    }

    logActivity($user['id'], 'update_user', "Updated user ID: $targetId");

    jsonResponse(['message' => 'User updated successfully']);
}

// DELETE: Delete user
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $targetId = (int) ($_GET['id'] ?? 0);
    if (!$targetId) jsonResponse(['error' => 'User ID is required'], 400);
    if ($targetId === $user['id']) jsonResponse(['error' => 'Cannot delete your own account'], 400);

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $stmt->close();

    logActivity($user['id'], 'delete_user', "Deleted user ID: $targetId");

    jsonResponse(['message' => 'User deleted successfully']);
}
