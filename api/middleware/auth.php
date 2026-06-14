<?php
/**
 * Authentication Middleware
 * JWT Authentication and Authorization
 */

require_once __DIR__ . '/../helpers/jwt.php';

/**
 * Authenticate request using JWT
 * Returns user data if authenticated, exits with 401 if not
 */
function authenticate(): ?array
{
    $token = getJWTFromHeader();

    if (!$token) {
        errorResponse('Authentication required. Please provide a valid Bearer token.', 401);
    }

    $payload = verifyJWT($token);

    if (!$payload) {
        errorResponse('Invalid or expired token. Please login again.', 401);
    }

    // Verify user still exists and is valid
    $user = getUserWithRoles($payload['sub']);

    if (!$user) {
        errorResponse('User not found or account has been disabled.', 401);
    }

    return $user;
}

/**
 * Optional authentication - doesn't exit if not authenticated
 */
function optionalAuth(): ?array
{
    $token = getJWTFromHeader();

    if (!$token) {
        return null;
    }

    $payload = verifyJWT($token);

    if (!$payload) {
        return null;
    }

    return getUserWithRoles($payload['sub']);
}

/**
 * Require specific permission
 */
function requirePermission(string $permission): array
{
    $user = authenticate();

    if (!hasPermission($user['id'], $permission)) {
        errorResponse('You do not have permission to perform this action.', 403);
    }

    return $user;
}

/**
 * Require admin role
 */
function requireAdmin(): array
{
    $user = authenticate();

    if (!($user['is_admin'] ?? false)) {
        errorResponse('Administrator access required.', 403);
    }

    return $user;
}

/**
 * Require staff role
 */
function requireStaff(): array
{
    $user = authenticate();

    if (!($user['is_staff'] ?? false)) {
        errorResponse('Staff access required.', 403);
    }

    return $user;
}

/**
 * Require department leader role
 */
function requireDepartmentLeader(int $departmentId = null): array
{
    $user = authenticate();

    $isLeader = false;

    foreach ($user['departments'] as $dept) {
        if ($departmentId && $dept['id'] !== $departmentId) {
            continue;
        }

        foreach ($dept['roles'] as $role) {
            if ($role['is_leader']) {
                $isLeader = true;
                break 2;
            }
        }
    }

    if (!$isLeader) {
        errorResponse('Department leader access required.', 403);
    }

    return $user;
}

/**
 * Check if user has specific permission
 */
function hasPermission(int $userId, string $permissionKey): bool
{
    static $cache = [];

    $cacheKey = "{$userId}:{$permissionKey}";

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT 1
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = ?
          AND p.`key` = ?
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$userId, $permissionKey]);
    $has = $stmt->fetch() !== false;
    $stmt->closeCursor();

    $cache[$cacheKey] = $has;

    return $has;
}

/**
 * Check if user has any of the specified permissions
 */
function hasAnyPermission(int $userId, array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (hasPermission($userId, $permission)) {
            return true;
        }
    }

    return false;
}

/**
 * Check if user has all of the specified permissions
 */
function hasAllPermissions(int $userId, array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (!hasPermission($userId, $permission)) {
            return false;
        }
    }

    return true;
}

/**
 * Get user permissions
 */
function getUserPermissions(int $userId): array
{
    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT p.`key`
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = ?
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
    ");
    $stmt->execute([$userId]);

    $permissions = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions[] = $row['key'];
    }

    $stmt->closeCursor();

    return $permissions;
}

/**
 * Check if user belongs to department
 */
function isInDepartment(int $userId, int $departmentId): bool
{
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 1 FROM user_departments
        WHERE user_id = ? AND department_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $departmentId]);

    return $stmt->fetch() !== false;
}

/**
 * Check if user is leader of department
 */
function isDepartmentLeader(int $userId, int $departmentId): bool
{
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 1
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ?
          AND ur.department_id = ?
          AND r.is_leader = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$userId, $departmentId]);

    return $stmt->fetch() !== false;
}

/**
 * Get user ID from JWT token
 */
function getUserIdFromToken(): ?int
{
    $token = getJWTFromHeader();

    if (!$token) {
        return null;
    }

    $payload = verifyJWT($token);

    if (!$payload) {
        return null;
    }

    return $payload['sub'] ?? null;
}