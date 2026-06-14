<?php
/**
 * Users Module
 * User profile endpoints
 */

require_once __DIR__ . '/../../helpers/database.php';
require_once __DIR__ . '/../../helpers/jwt.php';
require_once __DIR__ . '/../../helpers/logging.php';

/**
 * Handle users routes
 */
function handleRoute(string $method, string $action, ?string $id): void
{
    switch ($action) {
        case 'profile':
            if ($method === 'GET' && $id) {
                getProfile($id);
            } elseif ($method === 'PUT' || $method === 'PATCH') {
                updateProfile();
            }
            break;

        case 'avatar':
            if ($method === 'POST') {
                updateAvatar();
            }
            break;

        case 'cover':
            if ($method === 'POST') {
                updateCover();
            }
            break;

        case 'password':
            if ($method === 'PUT' || $method === 'PATCH') {
                changePassword();
            }
            break;

        case 'activity':
            if ($method === 'GET') {
                getActivity();
            }
            break;

        case 'permissions':
            if ($method === 'GET') {
                getMyPermissions();
            }
            break;

        case 'search':
            if ($method === 'GET') {
                searchUsers();
            }
            break;

        case 'list':
            if ($method === 'GET') {
                listUsers();
            }
            break;

        default:
            if ($method === 'GET' && $action === 'index') {
                getProfile($id);
            }
            break;
    }
}

/**
 * GET /api/users/:id
 * Get user profile
 */
function getProfile(string $uuid): void
{
    $db = getDB();

    // Get user basic info
    $stmt = $db->prepare("
        SELECT u.id, u.uuid, u.username, u.email, u.avatar, u.cover_photo, u.biography, u.signature,
               u.is_verified, u.is_active, u.login_count, u.created_at, u.last_seen
        FROM users u
        WHERE u.uuid = ? AND u.is_active = 1 AND u.is_banned = 0
    ");
    $stmt->execute([$uuid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$user) {
        errorResponse('User not found', 404);
    }

    // Get user roles
    $stmt = $db->prepare("
        SELECT r.id, r.uuid, r.name, r.slug, r.color, r.badge, r.type, r.is_leader, r.is_admin,
               d.id as department_id, d.code as department_code, d.name as department_name, d.color as department_color,
               ur.assigned_at
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        LEFT JOIN departments d ON ur.department_id = d.id
        WHERE ur.user_id = ? AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY r.hierarchy ASC
    ");
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Process roles
    $globalRoles = [];
    $departmentRoles = [];

    foreach ($roles as $role) {
        $roleData = [
            'id' => (int) $role['id'],
            'uuid' => $role['uuid'],
            'name' => $role['name'],
            'slug' => $role['slug'],
            'color' => $role['color'],
            'badge' => $role['badge'],
            'assigned_at' => $role['assigned_at'],
        ];

        if ($role['type'] === 'global') {
            $globalRoles[] = $roleData;
        } else {
            $deptId = $role['department_id'];
            if (!isset($departmentRoles[$deptId])) {
                $departmentRoles[$deptId] = [
                    'department' => [
                        'id' => (int) $role['department_id'],
                        'code' => $role['department_code'],
                        'name' => $role['department_name'],
                        'color' => $role['department_color'],
                    ],
                    'roles' => [],
                ];
            }
            $departmentRoles[$deptId]['roles'][] = $roleData;
        }
    }

    // Get user medals
    $stmt = $db->prepare("
        SELECT m.id, m.name, m.description, m.icon, m.color, m.type,
               um.granted_at, um.reason,
               g.username as granted_by_username
        FROM user_medals um
        JOIN permissions m ON um.medal_id = m.id
        LEFT JOIN users g ON um.granted_by = g.id
        WHERE um.user_id = ?
        ORDER BY um.granted_at DESC
    ");
    $stmt->execute([$user['id']]);
    $medals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formattedMedals = [];
    foreach ($medals as $medal) {
        $formattedMedals[] = [
            'id' => (int) $medal['id'],
            'name' => $medal['name'],
            'description' => $medal['description'],
            'icon' => $medal['icon'],
            'color' => $medal['color'],
            'type' => $medal['type'],
            'reason' => $medal['reason'],
            'granted_at' => $medal['granted_at'],
            'granted_by' => $medal['granted_by_username'],
        ];
    }

    // Format response
    $profile = [
        'id' => $user['id'],
        'uuid' => $user['uuid'],
        'username' => $user['username'],
        'avatar' => $user['avatar'],
        'cover_photo' => $user['cover_photo'],
        'biography' => $user['biography'],
        'signature' => $user['signature'],
        'is_verified' => (bool) $user['is_verified'],
        'login_count' => (int) $user['login_count'],
        'join_date' => $user['created_at'],
        'last_seen' => $user['last_seen'],
        'global_roles' => $globalRoles,
        'department_roles' => array_values($departmentRoles),
        'medals' => $formattedMedals,
    ];

    jsonResponse([
        'success' => true,
        'profile' => $profile,
    ]);
}

/**
 * PUT /api/users/profile
 * Update current user profile
 */
function updateProfile(): void
{
    $user = authenticate();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        errorResponse('Invalid JSON data');
    }

    $db = getDB();
    $updates = [];
    $params = [];

    // Update biography
    if (isset($input['biography'])) {
        $biography = sanitize(substr($input['biography'], 0, 1000));
        $updates[] = 'biography = ?';
        $params[] = $biography;
    }

    // Update signature
    if (isset($input['signature'])) {
        $signature = sanitize(substr($input['signature'], 0, 500));
        $updates[] = 'signature = ?';
        $params[] = $signature;
    }

    // Update username (if allowed and unique)
    if (isset($input['username']) && $input['username'] !== $user['username']) {
        $usernameErrors = isValidUsername($input['username']);
        if (!empty($usernameErrors)) {
            errorResponse('Invalid username', 400, $usernameErrors);
        }

        // Check if username is taken
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$input['username'], $user['id']]);
        if ($stmt->fetch()) {
            errorResponse('Username already taken', 409);
        }
        $stmt->closeCursor();

        $updates[] = 'username = ?';
        $params[] = sanitize($input['username']);
    }

    if (empty($updates)) {
        errorResponse('No fields to update', 400);
    }

    $params[] = $user['id'];
    $params[] = date('Y-m-d H:i:s');

    $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stmt->closeCursor();

    logActivity($user['id'], 'profile_update');

    // Get updated user data
    $userData = getUserWithRoles($user['id']);

    jsonResponse([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => formatUserResponse($userData),
    ]);
}

/**
 * POST /api/users/avatar
 * Upload avatar
 */
function updateAvatar(): void
{
    $user = authenticate();

    if (empty($_FILES['avatar'])) {
        errorResponse('No file uploaded', 400);
    }

    $result = uploadFile($_FILES['avatar'], UPLOAD_AVATARS_DIR, ['jpg', 'jpeg', 'png', 'gif']);

    if (!$result['success']) {
        errorResponse($result['error'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$result['url'], $user['id']]);
    $stmt->closeCursor();

    logActivity($user['id'], 'avatar_update');

    jsonResponse([
        'success' => true,
        'message' => 'Avatar updated successfully',
        'avatar' => $result['url'],
    ]);
}

/**
 * POST /api/users/cover
 * Upload cover photo
 */
function updateCover(): void
{
    $user = authenticate();

    if (empty($_FILES['cover'])) {
        errorResponse('No file uploaded', 400);
    }

    $result = uploadFile($_FILES['cover'], UPLOAD_BANNERS_DIR, ['jpg', 'jpeg', 'png']);

    if (!$result['success']) {
        errorResponse($result['error'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET cover_photo = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$result['url'], $user['id']]);
    $stmt->closeCursor();

    logActivity($user['id'], 'cover_update');

    jsonResponse([
        'success' => true,
        'message' => 'Cover photo updated successfully',
        'cover_photo' => $result['url'],
    ]);
}

/**
 * PUT /api/users/password
 * Change password
 */
function changePassword(): void
{
    $user = authenticate();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        errorResponse('Invalid JSON data');
    }

    $errors = validateRequired($input, ['current_password', 'password', 'password_confirm']);
    if (!empty($errors)) {
        errorResponse('Validation failed', 400, $errors);
    }

    $currentPassword = $input['current_password'];
    $newPassword = $input['password'];
    $confirmPassword = $input['password_confirm'];

    // Verify current password
    $db = getDB();
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!verifyPassword($currentPassword, $userData['password'])) {
        errorResponse('Current password is incorrect', 400);
    }

    // Verify new password match
    if ($newPassword !== $confirmPassword) {
        errorResponse('New passwords do not match', 400);
    }

    // Validate new password
    $passwordErrors = isValidPassword($newPassword);
    if (!empty($passwordErrors)) {
        errorResponse('Invalid password', 400, $passwordErrors);
    }

    // Update password
    $passwordHash = hashPassword($newPassword);
    $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$passwordHash, $user['id']]);
    $stmt->closeCursor();

    // Revoke all refresh tokens (force re-login on other devices)
    revokeAllUserTokens($user['id']);

    logActivity($user['id'], 'password_change');

    jsonResponse([
        'success' => true,
        'message' => 'Password changed successfully. Please login again on other devices.',
    ]);
}

/**
 * GET /api/users/activity
 * Get current user activity
 */
function getActivity(): void
{
    $user = authenticate();
    $params = getPaginationParams();

    $activities = getActivityLogs($user['id'], null, $params['per_page'], $params['offset']);

    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt->closeCursor();

    paginatedResponse($activities, $total, $params['page'], $params['per_page']);
}

/**
 * GET /api/users/permissions
 * Get current user permissions
 */
function getMyPermissions(): void
{
    $user = authenticate();

    $permissions = getUserPermissions($user['id']);

    jsonResponse([
        'success' => true,
        'permissions' => $permissions,
    ]);
}

/**
 * GET /api/users/search
 * Search users
 */
function searchUsers(): void
{
    $user = authenticate();
    $query = sanitize($_GET['q'] ?? '');
    $department = isset($_GET['department']) ? (int) $_GET['department'] : null;
    $role = isset($_GET['role']) ? sanitize($_GET['role']) : null;

    if (strlen($query) < 2) {
        errorResponse('Search query must be at least 2 characters', 400);
    }

    $params = getPaginationParams();
    $db = getDB();

    $where = ['u.is_active = 1', 'u.is_banned = 0'];
    $sqlParams = [];

    // Search by username
    if ($query) {
        $where[] = 'u.username LIKE ?';
        $sqlParams[] = '%' . $query . '%';
    }

    // Filter by department
    if ($department) {
        $where[] = 'EXISTS (SELECT 1 FROM user_departments ud WHERE ud.user_id = u.id AND ud.department_id = ?)';
        $sqlParams[] = $department;
    }

    // Filter by role
    if ($role) {
        $where[] = 'EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = u.id AND r.slug = ?)';
        $sqlParams[] = $role;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Get total count
    $countSql = "SELECT COUNT(DISTINCT u.id) as total FROM users u {$whereClause}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($sqlParams);
    $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt->closeCursor();

    // Get users
    $sql = "
        SELECT DISTINCT u.id, u.uuid, u.username, u.avatar, u.created_at, u.last_seen
        FROM users u
        {$whereClause}
        ORDER BY u.username ASC
        LIMIT ? OFFSET ?
    ";

    $sqlParams[] = $params['per_page'];
    $sqlParams[] = $params['offset'];

    $stmt = $db->prepare($sql);
    $stmt->execute($sqlParams);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formattedUsers = [];
    foreach ($users as $u) {
        $formattedUsers[] = [
            'id' => (int) $u['id'],
            'uuid' => $u['uuid'],
            'username' => $u['username'],
            'avatar' => $u['avatar'],
            'join_date' => $u['created_at'],
            'last_seen' => $u['last_seen'],
        ];
    }

    paginatedResponse($formattedUsers, $total, $params['page'], $params['per_page']);
}

/**
 * GET /api/users/list
 * List users (admin only)
 */
function listUsers(): void
{
    requireAdmin();

    $params = getPaginationParams();
    $db = getDB();

    $where = [];
    $sqlParams = [];

    // Filter by status
    $status = $_GET['status'] ?? null;
    if ($status === 'active') {
        $where[] = 'u.is_active = 1 AND u.is_banned = 0';
    } elseif ($status === 'banned') {
        $where[] = 'u.is_banned = 1';
    } elseif ($status === 'suspended') {
        $where[] = 'u.is_suspended = 1';
    }

    // Filter by department
    if (!empty($_GET['department'])) {
        $where[] = 'EXISTS (SELECT 1 FROM user_departments ud WHERE ud.user_id = u.id AND ud.department_id = ?)';
        $sqlParams[] = (int) $_GET['department'];
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM users u {$whereClause}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($sqlParams);
    $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt->closeCursor();

    // Get users
    $sql = "
        SELECT u.id, u.uuid, u.username, u.email, u.avatar, u.is_active, u.is_banned, u.is_suspended,
               u.created_at, u.last_seen, u.login_count
        FROM users u
        {$whereClause}
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $sqlParams[] = $params['per_page'];
    $sqlParams[] = $params['offset'];

    $stmt = $db->prepare($sql);
    $stmt->execute($sqlParams);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formattedUsers = [];
    foreach ($users as $u) {
        // Get user roles
        $stmt = $db->prepare("
            SELECT r.name, r.color, r.badge, d.code as department_code
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN departments d ON ur.department_id = d.id
            WHERE ur.user_id = ?
            LIMIT 3
        ");
        $stmt->execute([$u['id']]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $formattedUsers[] = [
            'id' => (int) $u['id'],
            'uuid' => $u['uuid'],
            'username' => $u['username'],
            'email' => $u['email'],
            'avatar' => $u['avatar'],
            'is_active' => (bool) $u['is_active'],
            'is_banned' => (bool) $u['is_banned'],
            'is_suspended' => (bool) $u['is_suspended'],
            'roles' => $roles,
            'join_date' => $u['created_at'],
            'last_seen' => $u['last_seen'],
            'login_count' => (int) $u['login_count'],
        ];
    }

    paginatedResponse($formattedUsers, $total, $params['page'], $params['per_page']);
}

/**
 * Format user response
 */
function formatUserResponse(array $user): array
{
    return [
        'id' => $user['id'],
        'uuid' => $user['uuid'],
        'username' => $user['username'],
        'email' => $user['email'],
        'avatar' => $user['avatar'],
        'is_admin' => $user['is_admin'],
        'is_staff' => $user['is_staff'],
        'roles' => $user['roles'],
        'departments' => $user['departments'],
        'primary_role' => $user['primary_role'],
        'primary_department' => $user['primary_department'],
    ];
}