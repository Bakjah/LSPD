<?php
/**
 * Forgot Password API
 */
require_once __DIR__ . '/config.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'A valid email address is required.'], 400);
}

if (isRateLimited('forgot_' . md5($email), 3, 3600)) {
    jsonResponse(['error' => 'Too many requests. Please try again later.'], 429);
}

$db = getDB();
$stmt = $db->prepare("SELECT id, username, email FROM users WHERE email = ? AND is_banned = 0 LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    // Don't reveal whether email exists
    jsonResponse(['message' => 'If an account with that email exists, a reset link has been sent.']);
}

$token = generateRandomToken(48);
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

$stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $user['id'], $token, $expiresAt);
$stmt->execute();
$stmt->close();

// In production, send email here. For now, log the token.
$resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../reset.php?token=' . $token;

// Log for development
error_log("Password reset for {$user['username']}: {$resetUrl}");

jsonResponse(['message' => 'If an account with that email exists, a reset link has been sent.']);
