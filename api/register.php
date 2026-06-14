<?php
/**
 * Register API — User registration with email verification
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$username = trim($input['username'] ?? '');
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$confirm  = $input['confirm_password'] ?? '';

// Validate input
$valid = validateUsername($username);
if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);

$valid = validateEmail($email);
if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);

$valid = validatePassword($password);
if (!$valid[0]) jsonResponse(['error' => $valid[1]], 400);

if ($password !== $confirm) jsonResponse(['error' => 'Passwords do not match'], 400);

if (!getSetting('registration_enabled', true)) {
    jsonResponse(['error' => 'Registration is currently disabled'], 403);
}

$db = getDB();

// Check username uniqueness
$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    jsonResponse(['error' => 'Username is already taken'], 409);
}
$stmt->close();

// Check email uniqueness
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    jsonResponse(['error' => 'Email is already registered'], 409);
}
$stmt->close();

// Get default role
$defaultRoleName = getSetting('default_role', 'Community Member');
$defaultDeptCode = getSetting('default_department', 'COMM');

$roleRow = $db->query("SELECT id FROM roles WHERE name = '" . $db->real_escape_string($defaultRoleName) . "' LIMIT 1")->fetch_assoc();
$deptRow = $db->query("SELECT id FROM departments WHERE code = '" . $db->real_escape_string($defaultDeptCode) . "' LIMIT 1")->fetch_assoc();

$rankId = $roleRow['id'] ?? null;
$deptId = $deptRow['id'] ?? null;

// Create user
$hash       = password_hash($password, PASSWORD_DEFAULT);
$uuid       = generateUUID();
$verifyToken = generateRandomToken(64);

$stmt = $db->prepare("INSERT INTO users (uuid, username, email, password, verify_token, department_id, rank_id, join_date, last_seen, is_active) VALUES (?,?,?,?,?,?,?,NOW(),NOW(),1)");
$stmt->bind_param('sssssii', $uuid, $username, $email, $hash, $verifyToken, $deptId, $rankId);
$stmt->execute();
$userId = $stmt->insert_id;
$stmt->close();

if (!$userId) jsonResponse(['error' => 'Failed to create user'], 500);

// Assign default role
if ($rankId) {
    $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?,?)");
    $stmt->bind_param('ii', $userId, $rankId);
    $stmt->execute();
    $stmt->close();
}

logActivity($userId, 'register', "User registered: $username");

if (getSetting('email_verification_required', true)) {
    // Send verification email
    $verifyUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/LSPD/verify.php?token=' . $verifyToken;
    $subject = 'Verify your LSPD Forum account';
    $message = "Hello $username,\n\n";
    $message .= "Welcome to LSPD Forum! Please click the link below to verify your account:\n\n";
    $message .= "$verifyUrl\n\n";
    $message .= "This link expires in 24 hours.\n\n";
    $message .= "If you did not create this account, please ignore this email.\n\n";
    $message .= "Best regards,\nLSPD Forum Team";

    $headers = 'From: noreply@lspdforum.com' . "\r\n" .
               'Reply-To: noreply@lspdforum.com' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    @mail($email, $subject, $message, $headers);

    jsonResponse([
        'message' => 'Registration successful! Please check your email to verify your account.',
        'userId'  => $userId,
    ]);
} else {
    // Auto-verify if email verification is disabled
    $stmt = $db->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    jsonResponse([
        'message' => 'Registration successful!',
        'userId'  => $userId,
    ]);
}