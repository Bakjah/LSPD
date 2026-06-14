<?php
/**
 * Logging Helper
 * Activity and Login Logging
 */

require_once __DIR__ . '/database.php';

/**
 * Log user activity
 */
function logActivity(int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $action,
        $entityType,
        $entityId,
        $details,
        getClientIP(),
        getUserAgent(),
    ]);
    $stmt->closeCursor();
}

/**
 * Log login attempt
 */
function logLoginAttempt(?int $userId, ?string $username, ?string $email, string $status, ?string $reason = null): void
{
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO login_logs (user_id, username, email, status, ip_address, user_agent, reason)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $username,
        $email,
        $status,
        getClientIP(),
        getUserAgent(),
        $reason,
    ]);
    $stmt->closeCursor();
}

/**
 * Get activity logs
 */
function getActivityLogs(?int $userId = null, ?string $action = null, int $limit = 50, int $offset = 0): array
{
    $db = getDB();

    $where = [];
    $params = [];

    if ($userId !== null) {
        $where[] = 'al.user_id = ?';
        $params[] = $userId;
    }

    if ($action !== null) {
        $where[] = 'al.action = ?';
        $params[] = $action;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT al.*, u.username
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        {$whereClause}
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $logs = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $logs[] = [
            'id' => (int) $row['id'],
            'user_id' => $row['user_id'] ? (int) $row['user_id'] : null,
            'username' => $row['username'],
            'action' => $row['action'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'] ? (int) $row['entity_id'] : null,
            'details' => $row['details'],
            'ip_address' => $row['ip_address'],
            'created_at' => $row['created_at'],
        ];
    }

    $stmt->closeCursor();

    return $logs;
}

/**
 * Get login logs
 */
function getLoginLogs(?int $userId = null, ?string $status = null, int $limit = 50, int $offset = 0): array
{
    $db = getDB();

    $where = [];
    $params = [];

    if ($userId !== null) {
        $where[] = 'll.user_id = ?';
        $params[] = $userId;
    }

    if ($status !== null) {
        $where[] = 'll.status = ?';
        $params[] = $status;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT ll.*
        FROM login_logs ll
        {$whereClause}
        ORDER BY ll.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $logs = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $logs[] = [
            'id' => (int) $row['id'],
            'user_id' => $row['user_id'] ? (int) $row['user_id'] : null,
            'username' => $row['username'],
            'email' => $row['email'],
            'status' => $row['status'],
            'ip_address' => $row['ip_address'],
            'reason' => $row['reason'],
            'created_at' => $row['created_at'],
        ];
    }

    $stmt->closeCursor();

    return $logs;
}

/**
 * Get recent failed login attempts
 */
function getFailedLoginAttempts(string $identifier, int $window = 300): int
{
    $cacheFile = sys_get_temp_dir() . "/lspd_failed_{$identifier}.json";
    $data = [];

    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true) ?? [];
    }

    $now = time();
    $data = array_filter($data, fn($t) => ($now - $t) < $window);

    return count($data);
}

/**
 * Record failed login attempt
 */
function recordFailedLogin(string $identifier): void
{
    $cacheFile = sys_get_temp_dir() . "/lspd_failed_{$identifier}.json";
    $data = [];

    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true) ?? [];
    }

    $data[] = time();
    file_put_contents($cacheFile, json_encode($data));
}

/**
 * Clear failed login attempts
 */
function clearFailedLogins(string $identifier): void
{
    $cacheFile = sys_get_temp_dir() . "/lspd_failed_{$identifier}.json";

    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
}