<?php
/**
 * JWT Helper
 * JSON Web Token implementation
 */

require_once __DIR__ . '/database.php';

/**
 * Create JWT Token
 */
function createJWT(array $payload): string
{
    $secret = getSetting('jwt_secret', 'default_secret_change_me');
    $expiresIn = getSetting('jwt_access_expires', 900);

    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256',
    ];

    $payload['iat'] = time();
    $payload['exp'] = time() + $expiresIn;

    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true);
    $signatureEncoded = base64UrlEncode($signature);

    return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
}

/**
 * Verify and decode JWT Token
 */
function verifyJWT(string $token): ?array
{
    $secret = getSetting('jwt_secret', 'default_secret_change_me');

    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        return null;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    // Verify signature
    $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $secret, true);
    $expectedSignatureEncoded = base64UrlEncode($expectedSignature);

    if (!hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
        return null;
    }

    // Decode payload
    $payload = json_decode(base64UrlDecode($payloadEncoded), true);

    if (!$payload) {
        return null;
    }

    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return null;
    }

    return $payload;
}

/**
 * Get JWT from Authorization header
 */
function getJWTFromHeader(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($header)) {
        return null;
    }

    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Create Refresh Token
 */
function createRefreshToken(int $userId): string
{
    $token = generateToken(32);
    $expiresIn = getSetting('jwt_refresh_expires', 604800);
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO refresh_tokens (user_id, token, expires_at, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        hash('sha256', $token),
        $expiresAt,
        getClientIP(),
        getUserAgent(),
    ]);

    return $token;
}

/**
 * Verify Refresh Token
 */
function verifyRefreshToken(string $token): ?array
{
    $db = getDB();
    $stmt = $db->prepare("
        SELECT rt.*, u.id as user_id, u.uuid, u.username, u.email, u.is_active, u.is_banned
        FROM refresh_tokens rt
        JOIN users u ON rt.user_id = u.id
        WHERE rt.token = ? AND rt.revoked_at IS NULL AND rt.expires_at > NOW()
    ");
    $stmt->execute([hash('sha256', $token)]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return null;
    }

    // Check if user is still valid
    if ($result['is_active'] == 0 || $result['is_banned'] == 1) {
        return null;
    }

    return $result;
}

/**
 * Revoke Refresh Token
 */
function revokeRefreshToken(string $token): bool
{
    $db = getDB();
    $stmt = $db->prepare("UPDATE refresh_tokens SET revoked_at = NOW() WHERE token = ?");
    $stmt->execute([hash('sha256', $token)]);

    return $stmt->rowCount() > 0;
}

/**
 * Revoke all refresh tokens for user
 */
function revokeAllUserTokens(int $userId): bool
{
    $db = getDB();
    $stmt = $db->prepare("UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = ?");
    $stmt->execute([$userId]);

    return true;
}

/**
 * Clean expired refresh tokens
 */
function cleanExpiredTokens(): int
{
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM refresh_tokens WHERE expires_at < NOW() OR revoked_at IS NOT NULL");
    $stmt->execute();

    return $stmt->rowCount();
}

/**
 * Base64 URL encode
 */
function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL decode
 */
function base64UrlDecode(string $data): string
{
    $padding = strlen($data) % 4;
    if ($padding) {
        $data .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Get bearer token response
 */
function getAuthTokenResponse(int $userId): array
{
    $user = getUserWithRoles($userId);

    if (!$user) {
        return null;
    }

    $accessToken = createJWT([
        'sub' => $user['id'],
        'uuid' => $user['uuid'],
        'username' => $user['username'],
        'email' => $user['email'],
        'roles' => $user['roles'],
        'departments' => $user['departments'],
        'is_admin' => $user['is_admin'],
    ]);

    $refreshToken = createRefreshToken($userId);

    return [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'expires_in' => getSetting('jwt_access_expires', 900),
    ];
}

/**
 * Get user with roles from database
 */
function getUserWithRoles(int $userId): ?array
{
    $db = getDB();

    // Get user basic info
    $stmt = $db->prepare("
        SELECT id, uuid, username, email, avatar, is_active, is_banned
        FROM users
        WHERE id = ? AND is_active = 1 AND is_banned = 0
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return null;
    }

    // Get user roles
    $stmt = $db->prepare("
        SELECT r.id, r.uuid, r.name, r.slug, r.color, r.badge, r.type, r.is_leader, r.is_admin,
               d.id as department_id, d.code as department_code, d.name as department_name
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        LEFT JOIN departments d ON ur.department_id = d.id
        WHERE ur.user_id = ? AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY r.hierarchy ASC
    ");
    $stmt->execute([$userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $user['roles'] = [];
    $user['departments'] = [];
    $user['is_admin'] = false;
    $user['is_staff'] = false;
    $user['primary_role'] = null;
    $user['primary_department'] = null;

    foreach ($roles as $role) {
        $roleData = [
            'id' => (int) $role['id'],
            'uuid' => $role['uuid'],
            'name' => $role['name'],
            'slug' => $role['slug'],
            'color' => $role['color'],
            'badge' => $role['badge'],
            'type' => $role['type'],
            'is_leader' => (bool) $role['is_leader'],
        ];

        $user['roles'][] = $roleData;

        if ($role['type'] === 'global' && $role['is_admin']) {
            $user['is_admin'] = true;
        }

        if ($role['is_leader'] || $role['is_admin']) {
            $user['is_staff'] = true;
        }

        if ($role['department_id']) {
            $deptId = (int) $role['department_id'];
            if (!isset($user['departments'][$deptId])) {
                $user['departments'][$deptId] = [
                    'id' => $deptId,
                    'code' => $role['department_code'],
                    'name' => $role['department_name'],
                    'roles' => [],
                ];
            }
            $user['departments'][$deptId]['roles'][] = $roleData;
        }

        // Set primary role as highest hierarchy
        if (!$user['primary_role'] || $role['hierarchy'] < $user['primary_role']['hierarchy']) {
            $user['primary_role'] = $roleData;
            $user['primary_department'] = $role['department_id'] ? [
                'id' => (int) $role['department_id'],
                'code' => $role['department_code'],
                'name' => $role['department_name'],
            ] : null;
        }
    }

    $user['departments'] = array_values($user['departments']);

    return $user;
}