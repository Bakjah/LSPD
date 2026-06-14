<?php
/**
 * Password Reset API
 */
require_once __DIR__ . '/config.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$token = trim($data['token'] ?? '');
$password = $data['password'] ?? '';

if (empty($token) || empty($password)) {
    jsonResponse(['error' => 'Token and new password are required.'], 400);
}

$pwValidation = validatePassword($password);
if (!$pwValidation['valid']) {
    jsonResponse(['error' => $pwValidation['errors'][0]], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$reset = $result->fetch_assoc();
$stmt->close();

if (!$reset) {
    jsonResponse(['error' => 'Invalid or expired reset token.'], 400);
}

$userId = $reset['user_id'];
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param('si', $hashedPassword, $userId);
$stmt->execute();
$stmt->close();

$stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();
$stmt->close();

// Invalidate all sessions for this user
$stmt = $db->prepare("DELETE FROM sessions WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

logActivity($userId, 'password_reset', 'Password reset via email link');

jsonResponse(['message' => 'Password reset successful. You can now login with your new password.']);
