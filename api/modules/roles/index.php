<?php
/**
 * Roles Module
 * Dynamic role management endpoints
 */

require_once __DIR__ . '/../../helpers/database.php';
require_once __DIR__ . '/../../helpers/jwt.php';
require_once __DIR__ . '/../../helpers/logging.php';

/**
 * Handle roles routes
 */
function handleRoute(string $method, string $action, ?string $id): void
{
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                listRoles();
            }
            break;

        case 'create':
            if ($method === 'POST') {
                createRole();
            }
            break;

        case 'permissions':
            if ($method === 'GET' && $id) {
                getRolePermissions($id);
            }
            break;

        case 'assign':
            if ($method === 'POST') {
                assignRole();
            }
            break;

        case 'remove':
            if ($method === 'DELETE') {
                removeRole();
            }
            break;

        default:
            if ($method === 'GET' && ($action === 'index' || is_numeric($action))) {
                getRole($action === 'index' ? $id : $action);
            } elseif ($method === 'PUT' || $method === 'PATCH') {
                updateRole($id);
            } elseif ($method === 'DELETE') {
                deleteRole($id);
            }
            break;
    }
}

/**
 * GET /api/roles
 * List all roles
 */
function listRoles(): void
{
    $db = getDB();

    $department = isset($_GET['department']) ? (int) $_GET['department'] : null;
    $type = sanitize($_GET['type'] ?? '');

    $where = [];
    $sqlParams = [];

    if ($department) {
        $where[] = '(r.department_id = ? OR r.department_id IS NULL)';
        $sqlParams[] = $department;
    }

    if ($type) {
        $where[] = 'r.type = ?';
        $sqlParams[] = $type;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT r.id, r.uuid, r.name, r.slug, r.description, r.department_id, r.type,
               r.is_leader, r.is_staff, r.is_admin, r.color, r.badge, r.icon, r.hierarchy, r.sort_order,
               d.code as department_code, d.name as department_name, d.color as department_color,
               (SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur WHERE ur.role_id = r.id) as member_count
        FROM roles r
        LEFT JOIN departments d ON r.department_id = d.id
        {$whereClause}
        ORDER BY r.type ASC, r.hierarchy ASC, r.sort_order ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($sqlParams);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $formatted = [];
    foreach ($roles as $role) {
        $formatted[] = [
            'id' => (int) $role['id'],
            'uuid' => $role['uuid'],
            'name' => $role['name'],
            'slug' => $role['slug'],
            'description' => $role['description'],
            'type' => $role['type'],
            'is_leader' => (bool) $role['is_leader'],
            'is_staff' => (bool) $role['is_staff'],
            'is_admin' => (bool) $role['is_admin'],
            'color' => $role['color'],
            'badge' => $role['badge'],
            'icon' => $role['icon'],
            'hierarchy' => (int) $role['hierarchy'],
            'sort_order' => (int) $role['sort_order'],
            'department' => $role['department_id'] ? [
                'id' => (int) $role['department_id'],
                'code' => $role['department_code'],
                'name' => $role['department_name'],
                'color' => $role['department_color'],
            ] : null,
            'member_count' => (int) $role['member_count'],
        ];
    }

    jsonResponse([
        'success' => true,
        'roles' => $formatted,
    ]);
}

/**
 * GET /api/roles/:id
 * Get single role
 */
function getRole(string $identifier): void
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT r.*, d.code as department_code, d.name as department_name, d.color as department_color
        FROM roles r
        LEFT JOIN departments d ON r.department_id = d.id
        WHERE r.id = ? OR r.slug = ?
    ");
    $stmt->execute([$identifier, $identifier]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$role) {
        errorResponse('Role not found', 404);
    }

    // Get role permissions
    $stmt = $db->prepare("
        SELECT p.id, p.uuid, p.name, p.key, p.description, p.group
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ?
        ORDER BY p.group ASC, p.sort_order ASC
    ");
    $stmt->execute([$role['id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Get role members
    $stmt = $db->prepare("
        SELECT u.uuid, u.username, u.avatar, ur.assigned_at
        FROM user_roles ur
        JOIN users u ON ur.user_id = u.id
        WHERE ur.role_id = ? AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY u.username ASC
    ");
    $stmt->execute([$role['id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    jsonResponse([
        'success' => true,
        'role' => [
            'id' => (int) $role['id'],
            'uuid' => $role['uuid'],
            'name' => $role['name'],
            'slug' => $role['slug'],
            'description' => $role['description'],
            'type' => $role['type'],
            'is_leader' => (bool) $role['is_leader'],
            'is_staff' => (bool) $role['is_staff'],
            'is_admin' => (bool) $role['is_admin'],
            'color' => $role['color'],
            'badge' => $role['badge'],
            'icon' => $role['icon'],
            'hierarchy' => (int) $role['hierarchy'],
            'sort_order' => (int) $role['sort_order'],
            'department' => $role['department_id'] ? [
                'id' => (int) $role['department_id'],
                'code' => $role['department_code'],
                'name' => $role['department_name'],
                'color' => $role['department_color'],
            ] : null,
            'permissions' => $permissions,
            'members' => array_map(fn($m) => [
                'uuid' => $m['uuid'],
                'username' => $m['username'],
                'avatar' => $m['avatar'],
                'assigned_at' => $m['assigned_at'],
            ], $members),
        ],
    ]);
}

/**
 * POST /api/roles/create
 * Create new role
 */
function createRole(): void
{
    $user = requireDepartmentLeader();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        errorResponse('Invalid JSON data');
    }

    $errors = validateRequired($input, ['name', 'department_id']);
    if (!empty($errors)) {
        errorResponse('Validation failed', 400, $errors);
    }

    $name = sanitize($input['name']);
    $departmentId = (int) $input['department_id'];
    $description = sanitize($input['description'] ?? '');
    $color = sanitize($input['color'] ?? '#94A3B8');
    $badge = sanitize($input['badge'] ?? '');
    $icon = sanitize($input['icon'] ?? '');
    $hierarchy = (int) ($input['hierarchy'] ?? 50);
    $isLeader = (bool) ($input['is_leader'] ?? false);
    $isStaff = (bool) ($input['is_staff'] ?? false);
    $permissions = $input['permissions'] ?? [];

    // Verify department exists
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM departments WHERE id = ? AND is_active = 1");
    $stmt->execute([$departmentId]);
    if (!$stmt->fetch()) {
        errorResponse('Department not found', 404);
    }
    $stmt->closeCursor();

    // Check if user is leader of this department
    if (!isDepartmentLeader($user['id'], $departmentId)) {
        errorResponse('You must be a department leader to create roles', 403);
    }

    // Check for duplicate slug
    $slug = slugify($name);
    $stmt = $db->prepare("SELECT id FROM roles WHERE slug = ? AND department_id = ?");
    $stmt->execute([$slug, $departmentId]);
    if ($stmt->fetch()) {
        errorResponse('A role with this name already exists in this department', 409);
    }
    $stmt->closeCursor();

    // Create role
    $uuid = generateUUID();

    $stmt = $db->prepare("
        INSERT INTO roles (uuid, name, slug, description, department_id, type, is_leader, is_staff, color, badge, icon, hierarchy)
        VALUES (?, ?, ?, ?, ?, 'department', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$uuid, $name, $slug, $description, $departmentId, $isLeader, $isStaff, $color, $badge, $icon, $hierarchy]);
    $roleId = (int) $db->lastInsertId();
    $stmt->closeCursor();

    // Assign permissions
    if (!empty($permissions)) {
        $permPlaceholders = array_fill(0, count($permissions), '(?, ?)');
        $stmt = $db->prepare("
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT ?, p.id FROM permissions p WHERE p.uuid IN (" . implode(',', array_fill(0, count($permissions), '?')) . ")
        ");
        $params = [];
        foreach ($permissions as $uuid) {
            $params[] = $roleId;
            $params[] = $uuid;
        }
        $stmt->execute($params);
        $stmt->closeCursor();
    }

    logActivity($user['id'], 'role_created', 'role', $roleId, "Created role: {$name}");

    jsonResponse([
        'success' => true,
        'message' => 'Role created successfully',
        'role' => [
            'id' => $roleId,
            'uuid' => $uuid,
            'name' => $name,
            'slug' => $slug,
        ],
    ], 201);
}

/**
 * PUT /api/roles/:id
 * Update role
 */
function updateRole(?string $identifier): void
{
    $user = authenticate();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !$identifier) {
        errorResponse('Invalid request');
    }

    $roleId = (int) $identifier;

    // Get role
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$role) {
        errorResponse('Role not found', 404);
    }

    // Check permission
    if ($role['type'] === 'global') {
        requireAdmin();
    } else {
        if (!isDepartmentLeader($user['id'], $role['department_id'])) {
            errorResponse('You must be a department leader to edit this role', 403);
        }
    }

    $updates = [];
    $params = [];

    $fields = ['name', 'description', 'color', 'badge', 'icon', 'hierarchy', 'is_leader', 'is_staff'];

    foreach ($fields as $field) {
        if (isset($input[$field])) {
            $updates[] = "{$field} = ?";
            $params[] = $input[$field];
        }
    }

    if (isset($input['permissions'])) {
        // Update permissions
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        $stmt->closeCursor();

        $permissions = $input['permissions'];
        if (!empty($permissions)) {
            $stmt = $db->prepare("
                INSERT INTO role_permissions (role_id, permission_id)
                SELECT ?, p.id FROM permissions p WHERE p.uuid IN (" . implode(',', array_fill(0, count($permissions), '?')) . ")
            ");
            $params2 = [];
            foreach ($permissions as $uuid) {
                $params2[] = $roleId;
                $params2[] = $uuid;
            }
            $stmt->execute($params2);
            $stmt->closeCursor();
        }
    }

    if (!empty($updates)) {
        $params[] = $roleId;
        $sql = "UPDATE roles SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $stmt->closeCursor();
    }

    logActivity($user['id'], 'role_updated', 'role', $roleId, "Updated role: {$role['name']}");

    jsonResponse([
        'success' => true,
        'message' => 'Role updated successfully',
    ]);
}

/**
 * DELETE /api/roles/:id
 * Delete role
 */
function deleteRole(?string $identifier): void
{
    $user = authenticate();

    if (!$identifier) {
        errorResponse('Role ID required', 400);
    }

    $roleId = (int) $identifier;

    // Get role
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$role) {
        errorResponse('Role not found', 404);
    }

    // Cannot delete global roles
    if ($role['type'] === 'global') {
        errorResponse('Cannot delete global roles', 403);
    }

    // Check permission
    if (!isDepartmentLeader($user['id'], $role['department_id'])) {
        errorResponse('You must be a department leader to delete this role', 403);
    }

    // Check if role has members
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?");
    $stmt->execute([$roleId]);
    $memberCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt->closeCursor();

    if ($memberCount > 0) {
        errorResponse("Cannot delete role with {$memberCount} members. Remove members first.", 400);
    }

    // Delete role
    $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $stmt->closeCursor();

    logActivity($user['id'], 'role_deleted', 'role', $roleId, "Deleted role: {$role['name']}");

    jsonResponse([
        'success' => true,
        'message' => 'Role deleted successfully',
    ]);
}

/**
 * GET /api/roles/:id/permissions
 * Get role permissions
 */
function getRolePermissions(string $identifier): void
{
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM roles WHERE id = ? OR slug = ?");
    $stmt->execute([$identifier, $identifier]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$role) {
        errorResponse('Role not found', 404);
    }

    // Get role permissions
    $stmt = $db->prepare("
        SELECT p.uuid, p.name, p.key, p.description, p.group
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ?
        ORDER BY p.group ASC, p.sort_order ASC
    ");
    $stmt->execute([$role['id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    jsonResponse([
        'success' => true,
        'permissions' => $permissions,
    ]);
}

/**
 * POST /api/roles/assign
 * Assign role to user
 */
function assignRole(): void
{
    $user = authenticate();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        errorResponse('Invalid JSON data');
    }

    $errors = validateRequired($input, ['user_id', 'role_id']);
    if (!empty($errors)) {
        errorResponse('Validation failed', 400, $errors);
    }

    $targetUserId = (int) $input['user_id'];
    $roleId = (int) $input['role_id'];
    $departmentId = isset($input['department_id']) ? (int) $input['department_id'] : null;
    $expiresAt = $input['expires_at'] ?? null;

    // Get role
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$role) {
        errorResponse('Role not found', 404);
    }

    // Check permission
    if ($role['type'] === 'global') {
        requireAdmin();
    } else {
        $deptId = $departmentId ?? $role['department_id'];
        if (!isDepartmentLeader($user['id'], $deptId)) {
            errorResponse('You must be a department leader to assign this role', 403);
        }
    }

    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$targetUserId]);
    if (!$stmt->fetch()) {
        errorResponse('User not found', 404);
    }
    $stmt->closeCursor();

    // Check if already has role
    $stmt = $db->prepare("
        SELECT id FROM user_roles
        WHERE user_id = ? AND role_id = ? AND (department_id = ? OR (department_id IS NULL AND ? IS NULL))
    ");
    $stmt->execute([$targetUserId, $roleId, $departmentId, $departmentId]);
    if ($stmt->fetch()) {
        errorResponse('User already has this role', 409);
    }
    $stmt->closeCursor();

    // Assign role
    $stmt = $db->prepare("
        INSERT INTO user_roles (user_id, role_id, department_id, assigned_by, assigned_at, expires_at)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$targetUserId, $roleId, $departmentId, $user['id'], $expiresAt]);
    $stmt->closeCursor();

    // Add to department if not already
    if ($role['type'] === 'department' && $role['department_id']) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO user_departments (user_id, department_id, is_primary)
            VALUES (?, ?, 0)
        ");
        $stmt->execute([$targetUserId, $role['department_id']]);
        $stmt->closeCursor();
    }

    logActivity($user['id'], 'role_assigned', 'user', $targetUserId, "Assigned role: {$role['name']}");

    jsonResponse([
        'success' => true,
        'message' => 'Role assigned successfully',
    ]);
}

/**
 * DELETE /api/roles/remove
 * Remove role from user
 */
function removeRole(): void
{
    $user = authenticate();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        errorResponse('Invalid JSON data');
    }

    $errors = validateRequired($input, ['user_id', 'role_id']);
    if (!empty($errors)) {
        errorResponse('Validation failed', 400, $errors);
    }

    $targetUserId = (int) $input['user_id'];
    $roleId = (int) $input['role_id'];
    $departmentId = isset($input['department_id']) ? (int) $input['department_id'] : null;

    // Get role
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$role) {
        errorResponse('Role not found', 404);
    }

    // Check permission
    if ($role['type'] === 'global') {
        requireAdmin();
    } else {
        $deptId = $departmentId ?? $role['department_id'];
        if (!isDepartmentLeader($user['id'], $deptId)) {
            errorResponse('You must be a department leader to remove this role', 403);
        }
    }

    // Remove role
    $stmt = $db->prepare("
        DELETE FROM user_roles
        WHERE user_id = ? AND role_id = ? AND (department_id = ? OR (department_id IS NULL AND ? IS NULL))
    ");
    $stmt->execute([$targetUserId, $roleId, $departmentId, $departmentId]);
    $stmt->closeCursor();

    logActivity($user['id'], 'role_removed', 'user', $targetUserId, "Removed role: {$role['name']}");

    jsonResponse([
        'success' => true,
        'message' => 'Role removed successfully',
    ]);
}