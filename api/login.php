<?php
/**
 * Login API — Secure login with rate limiting and remember me
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$usernameOrEmail = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$remember = (bool) ($input['remember'] ?? false);

if (!$usernameOrEmail || !$password) {
    jsonResponse(['error' => 'Username/email and password are required'], 400);
}

// Rate limiting by IP
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('login_ip_' . md5($clientIp), MAX_LOGIN_ATTEMPTS, RATE_LIMIT_WINDOW)) {
    logLogin(0, $usernameOrEmail, 'failed', 'Rate limit exceeded');
    jsonResponse(['error' => 'Too many login attempts. Please try again later.'], 429);
}

$db = getDB();

// Find user by username or email
$stmt = $db->prepare("
    SELECT u.*, r.name as rank_name, r.color as rank_color, r.badge as rank_badge,
           r.is_staff as role_is_staff, d.name as dept_name, d.code as dept_code
    FROM users u
    LEFT JOIN roles r ON u.rank_id = r.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.username = ? OR u.email = ? LIMIT 1
");
$stmt->bind_param('ss', $usernameOrEmail, $usernameOrEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logLogin(0, $usernameOrEmail, 'failed', 'User not found');
    jsonResponse(['error' => 'Invalid username or password'], 401);
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if account is locked
if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    logLogin($user['id'], $user['username'], 'locked', 'Account locked');
    jsonResponse(['error' => 'Account is temporarily locked due to too many failed login attempts.'], 423);
}

// Check if banned
if ($user['is_banned']) {
    logLogin($user['id'], $user['username'], 'banned');
    jsonResponse(['error' => 'Account has been banned. Reason: ' . ($user['banned_reason'] ?? 'Violation of terms')], 403);
}

// Check if inactive
if (!$user['is_active']) {
    logLogin($user['id'], $user['username'], 'failed', 'Account inactive');
    jsonResponse(['error' => 'Account is not active'], 403);
}

// Verify password
if (!password_verify($password, $user['password'])) {
    $failedCount = $user['failed_logins'] + 1;
    $lockUntil = null;
    if ($failedCount >= MAX_LOGIN_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
    }
    $stmt = $db->prepare("UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?");
    $stmt->bind_param('isi', $failedCount, $lockUntil, $user['id']);
    $stmt->execute();
    $stmt->close();
    logLogin($user['id'], $user['username'], 'failed', 'Wrong password');
    jsonResponse(['error' => 'Invalid username or password'], 401);
}

// Reset failed login counter
$stmt = $db->prepare("UPDATE users SET failed_logins = 0, locked_until = NULL, login_count = login_count + 1, last_seen = NOW() WHERE id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

// Regenerate session ID
startSession();
session_regenerate_id(true);

// Build session user data
$sessionUser = [
    'id'         => (int) $user['id'],
    'uuid'       => $user['uuid'],
    'username'   => $user['username'],
    'email'      => $user['email'],
    'role'       => $user['rank_name'] ?? 'Community Member',
    'role_color' => $user['rank_color'] ?? '#6B7280',
    'role_badge' => $user['rank_badge'] ?? '',
    'dept'       => $user['dept_name'] ?? '',
    'dept_code'  => $user['dept_code'] ?? '',
    'avatar'     => $user['avatar'] ?? '',
    'is_staff'   => (bool) ($user['role_is_staff'] ?? false),
    'rank_id'    => (int) ($user['rank_id'] ?? 0),
    'dept_id'    => (int) ($user['department_id'] ?? 0),
];

$_SESSION['user'] = $sessionUser;
$_SESSION['login_time'] = time();

// Set remember me cookie
if ($remember) {
    $token = generateRandomToken(64);
    $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
    $stmt->bind_param('si', $token, $user['id']);
    $stmt->execute();
    $stmt->close();

    setcookie(COOKIE_NAME, $token, [
        'expires'  => time() + REMEMBER_LIFETIME,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

logLogin($user['id'], $user['username'], 'success');
logActivity($user['id'], 'login', "User logged in from IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));

jsonResponse([
    'user'  => $sessionUser,
    'token' => $remember ? generateCSRFToken() : null,
]);