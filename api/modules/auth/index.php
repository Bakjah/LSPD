<?php
/**
 * Auth Module
 * Authentication endpoints: register, login, logout, me, refresh
 */

require_once __DIR__ . '/../../helpers/database.php';
require_once __DIR__ . '/../../helpers/jwt.php';
require_once __DIR__ . '/../../helpers/logging.php';

/**
 * Handle auth routes
 */
function handleRoute(string $method, string $action, ?string $id): void
{
    switch ($action) {
        case 'register':
            if ($method === 'POST') {
                handleRegister();
            }
            break;

        case 'login':
            if ($method === 'POST') {
                handleLogin();
            }
            break;

        case 'logout':
            if ($method === 'POST') {
                handleLogout();
            }
            break;

        case 'me':
            if ($method === 'GET') {
                handleMe();
            }
            break;

        case 'refresh':
            if ($method === 'POST') {
                handleRefresh();
            }
            break;

        case 'forgot':
            if ($method === 'POST') {
                handleForgotPassword();
            }
            break;

        case 'reset':
            if ($method === 'POST') {
                handleResetPassword();
            }
            break;

        default:
            http_response_code(404);
            jsonResponse(['error' => 'Auth endpoint not found']);
    }
}

/**
 * POST /api/auth/register
 * Register new user
 */
function handleRegister(): void
{
    // Check if registration is allowed
    if (!getSetting('allow_registration', true)) {
        errorResponse('Registration is currently disabled', 403);
    }

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        errorResponse('Invalid JSON data');
    }

    // Validate required fields
    $errors = validateRequired($input, ['username', 'email', 'password', 'password_confirm']);

    if (!empty($errors)) {
        errorResponse('Validation failed', 400, $errors);
    }

    $username = sanitize($input['username']);
    $email = sanitize($input['email']);
    $password = $input['password'];
    $passwordConfirm = $input['password_confirm'];

    // Validate username
    $usernameErrors = isValidUsername($username);
    if (!empty($usernameErrors)) {
        errorResponse('Invalid username', 400, $usernameErrors);
    }

    // Validate email
    if (!isValidEmail($email)) {
        errorResponse('Invalid email address', 400);
    }

    // Validate password match
    if ($password !== $passwordConfirm) {
        errorResponse('Passwords do not match', 400);
    }

    // Validate password
    $passwordErrors = isValidPassword($password);
    if (!empty($passwordErrors)) {
        errorResponse('Invalid password', 400, $passwordErrors);
    }

    $db = getDB();

    // Check if username exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);

    if ($stmt->fetch()) {
        errorResponse('Username already taken', 409);
    }

    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        errorResponse('Email already registered', 409);
    }

    // Create user
    $uuid = generateUUID();
    $passwordHash = hashPassword($password);

    $stmt = $db->prepare("
        INSERT INTO users (uuid, username, email, password, is_verified, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, 1, NOW())
    ");
    $stmt->execute([$uuid, $username, $email, $passwordHash]);
    $userId = (int) $db->lastInsertId();
    $stmt->closeCursor();

    // Assign default role (Community Member)
    $stmt = $db->prepare("SELECT id FROM roles WHERE slug = 'community-member' LIMIT 1");
    $stmt->execute();
    $defaultRole = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($defaultRole) {
        $stmt = $db->prepare("
            INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
            VALUES (?, ?, NULL, NOW())
        ");
        $stmt->execute([$userId, $defaultRole['id']]);
        $stmt->closeCursor();
    }

    // Log activity
    logActivity($userId, 'user_register');

    // Get token response
    $tokens = getAuthTokenResponse($userId);

    if (!$tokens) {
        errorResponse('Failed to create session', 500);
    }

    // Get user data
    $user = getUserWithRoles($userId);

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful',
        'user' => formatUserResponse($user),
        'tokens' => $tokens,
    ], 201);
}

/**
 * POST /api/auth/login
 * User login
 */
function handleLogin(): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        errorResponse('Invalid JSON data');
    }

    $identifier = sanitize($input['username'] ?? $input['email'] ?? '');
    $password = $input['password'] ?? '';
    $rememberMe = (bool) ($input['remember_me'] ?? false);

    if (empty($identifier) || empty($password)) {
        errorResponse('Username/email and password are required', 400);
    }

    // Check rate limiting
    if (isRateLimited($identifier)) {
        $lockoutDuration = getSetting('lockout_duration', 300);
        logLoginAttempt(null, $identifier, null, 'locked', 'Too many failed attempts');
        errorResponse("Too many login attempts. Please try again after {$lockoutDuration} seconds.", 429);
    }

    $db = getDB();

    // Find user by username or email
    $stmt = $db->prepare("
        SELECT id, uuid, username, email, password, is_active, is_banned, is_suspended, suspended_until, failed_logins, locked_until
        FROM users
        WHERE username = ? OR email = ?
    ");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Check if user exists
    if (!$user) {
        recordFailedLogin($identifier);
        logLoginAttempt(null, $identifier, null, 'failed', 'User not found');
        errorResponse('Invalid credentials', 401);
    }

    // Check if account is locked
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        logLoginAttempt($user['id'], $user['username'], $user['email'], 'locked', 'Account locked');
        errorResponse('Account is temporarily locked. Please try again later.', 423);
    }

    // Check if account is banned
    if ($user['is_banned']) {
        logLoginAttempt($user['id'], $user['username'], $user['email'], 'banned', 'Account banned');
        errorResponse('This account has been banned', 403);
    }

    // Check if account is suspended
    if ($user['is_suspended'] && strtotime($user['suspended_until']) > time()) {
        logLoginAttempt($user['id'], $user['username'], $user['email'], 'locked', 'Account suspended');
        errorResponse('This account is suspended until ' . $user['suspended_until'], 403);
    }

    // Check if account is active
    if (!$user['is_active']) {
        logLoginAttempt($user['id'], $user['username'], $user['email'], 'failed', 'Account inactive');
        errorResponse('This account has been deactivated', 403);
    }

    // Verify password
    if (!verifyPassword($password, $user['password'])) {
        $failedAttempts = $user['failed_logins'] + 1;
        $maxAttempts = getSetting('max_login_attempts', 5);

        // Update failed login count
        $stmt = $db->prepare("UPDATE users SET failed_logins = ? WHERE id = ?");
        $stmt->execute([$failedAttempts, $user['id']]);
        $stmt->closeCursor();

        // Lock account if max attempts reached
        if ($failedAttempts >= $maxAttempts) {
            $lockoutDuration = getSetting('lockout_duration', 300);
            $lockedUntil = date('Y-m-d H:i:s', time() + $lockoutDuration);

            $stmt = $db->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
            $stmt->execute([$lockedUntil, $user['id']]);
            $stmt->closeCursor();

            recordFailedLogin($identifier);
            logLoginAttempt($user['id'], $user['username'], $user['email'], 'locked', 'Max attempts reached');
            errorResponse("Too many failed login attempts. Account locked for {$lockoutDuration} seconds.", 423);
        }

        recordFailedLogin($identifier);
        logLoginAttempt($user['id'], $user['username'], $user['email'], 'failed', 'Invalid password');
        errorResponse('Invalid credentials', 401);
    }

    // Login successful - reset failed attempts
    $stmt = $db->prepare("
        UPDATE users
        SET failed_logins = 0, locked_until = NULL, login_count = login_count + 1, last_seen = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
    $stmt->closeCursor();

    // Clear failed login cache
    clearFailedLogins($identifier);

    // Log successful login
    logLoginAttempt($user['id'], $user['username'], $user['email'], 'success');
    logActivity($user['id'], 'user_login');

    // Get token response
    $tokens = getAuthTokenResponse($user['id']);

    if (!$tokens) {
        errorResponse('Failed to create session', 500);
    }

    // Get user data
    $userData = getUserWithRoles($user['id']);

    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'user' => formatUserResponse($userData),
        'tokens' => $tokens,
    ]);
}

/**
 * POST /api/auth/logout
 * User logout
 */
function handleLogout(): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $refreshToken = $input['refresh_token'] ?? null;

    // Get current user for logging
    $user = optionalAuth();

    if ($user) {
        logActivity($user['id'], 'user_logout');
    }

    // Revoke refresh token if provided
    if ($refreshToken) {
        revokeRefreshToken($refreshToken);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Logout successful',
    ]);
}

/**
 * GET /api/auth/me
 * Get current user
 */
function handleMe(): void
{
    $user = authenticate();

    $userData = getUserWithRoles($user['id']);

    if (!$userData) {
        errorResponse('User not found', 404);
    }

    jsonResponse([
        'success' => true,
        'user' => formatUserResponse($userData),
    ]);
}

/**
 * POST /api/auth/refresh
 * Refresh access token
 */
function handleRefresh(): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['refresh_token'])) {
        errorResponse('Refresh token is required', 400);
    }

    $refreshToken = $input['refresh_token'];

    // Verify refresh token
    $tokenData = verifyRefreshToken($refreshToken);

    if (!$tokenData) {
        errorResponse('Invalid or expired refresh token', 401);
    }

    // Revoke old refresh token (token rotation)
    revokeRefreshToken($refreshToken);

    // Log activity
    logActivity($tokenData['user_id'], 'token_refresh');

    // Create new tokens
    $tokens = getAuthTokenResponse($tokenData['user_id']);

    if (!$tokens) {
        errorResponse('Failed to refresh session', 500);
    }

    // Get user data
    $userData = getUserWithRoles($tokenData['user_id']);

    jsonResponse([
        'success' => true,
        'message' => 'Token refreshed successfully',
        'user' => formatUserResponse($userData),
        'tokens' => $tokens,
    ]);
}

/**
 * POST /api/auth/forgot
 * Request password reset
 */
function handleForgotPassword(): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['email'])) {
        errorResponse('Email is required', 400);
    }

    $email = sanitize($input['email']);

    if (!isValidEmail($email)) {
        errorResponse('Invalid email address', 400);
    }

    $db = getDB();

    // Find user by email
    $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Always return success to prevent email enumeration
    jsonResponse([
        'success' => true,
        'message' => 'If the email exists, a reset link will be sent',
    ]);

    if (!$user) {
        return;
    }

    // Generate reset token
    $token = generateToken(32);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->execute([hash('sha256', $token), $expiresAt, $user['id']]);
    $stmt->closeCursor();

    // In production, send email here
    // For now, just log it
    logActivity($user['id'], 'password_reset_requested');
}

/**
 * POST /api/auth/reset
 * Reset password with token
 */
function handleResetPassword(): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        errorResponse('Invalid JSON data');
    }

    $errors = validateRequired($input, ['token', 'password', 'password_confirm']);

    if (!empty($errors)) {
        errorResponse('Validation failed', 400, $errors);
    }

    $token = $input['token'];
    $password = $input['password'];
    $passwordConfirm = $input['password_confirm'];

    if ($password !== $passwordConfirm) {
        errorResponse('Passwords do not match', 400);
    }

    $passwordErrors = isValidPassword($password);
    if (!empty($passwordErrors)) {
        errorResponse('Invalid password', 400, $passwordErrors);
    }

    $db = getDB();

    // Find user by reset token
    $stmt = $db->prepare("
        SELECT id, username FROM users
        WHERE reset_token = ? AND reset_expires > NOW()
    ");
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$user) {
        errorResponse('Invalid or expired reset token', 400);
    }

    // Update password
    $passwordHash = hashPassword($password);

    $stmt = $db->prepare("
        UPDATE users
        SET password = ?, reset_token = NULL, reset_expires = NULL
        WHERE id = ?
    ");
    $stmt->execute([$passwordHash, $user['id']]);
    $stmt->closeCursor();

    // Revoke all refresh tokens
    revokeAllUserTokens($user['id']);

    // Log activity
    logActivity($user['id'], 'password_reset');

    jsonResponse([
        'success' => true,
        'message' => 'Password reset successful. Please login with your new password.',
    ]);
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