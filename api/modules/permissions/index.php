<?php
/**
 * Permissions Module
 * Permission management endpoints
 */

require_once __DIR__ . '/../../helpers/database.php';
require_once __DIR__ . '/../../helpers/jwt.php';
require_once __DIR__ . '/../../helpers/logging.php';

/**
 * Handle permissions routes
 */
function handleRoute(string $method, string $action, ?string $id): void
{
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                listPermissions();
            }
            break;

        case 'groups':
            if ($method === 'GET') {
                getPermissionGroups();
            }
            break;

        default:
            if ($method === 'GET') {
                listPermissions();
            }
            break;
    }
}

/**
 * GET /api/permissions
 * List all permissions
 */
function listPermissions(): void
{
    requireAdmin();

    $db = getDB();
    $group = sanitize($_GET['group'] ?? '');

    $where = [];
    $sqlParams = [];

    if ($group) {
        $where[] = 'p.group = ?';
        $sqlParams[] = $group;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT p.id, p.uuid, p.name, p.key, p.description, p.group, p.sort_order,
               (SELECT COUNT(*) FROM role_permissions rp WHERE rp.permission_id = p.id) as role_count
        FROM permissions p
        {$whereClause}
        ORDER BY p.group ASC, p.sort_order ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($sqlParams);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Group by permission group
    $grouped = [];
    foreach ($permissions as $perm) {
        $g = $perm['group'];
        if (!isset($grouped[$g])) {
            $grouped[$g] = [
                'name' => ucfirst($g),
                'permissions' => [],
            ];
        }
        $grouped[$g]['permissions'][] = [
            'id' => (int) $perm['id'],
            'uuid' => $perm['uuid'],
            'name' => $perm['name'],
            'key' => $perm['key'],
            'description' => $perm['description'],
            'role_count' => (int) $perm['role_count'],
        ];
    }

    jsonResponse([
        'success' => true,
        'permissions' => array_values($grouped),
    ]);
}

/**
 * GET /api/permissions/groups
 * Get permission groups
 */
function getPermissionGroups(): void
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT DISTINCT `group`, COUNT(*) as count
        FROM permissions
        GROUP BY `group`
        ORDER BY `group` ASC
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    jsonResponse([
        'success' => true,
        'groups' => array_map(fn($g) => [
            'name' => $g['group'],
            'count' => (int) $g['count'],
        ], $groups),
    ]);
}
