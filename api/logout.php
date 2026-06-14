<?php
/**
 * Logout API — Secure session termination
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

startSession();

if (!empty($_SESSION['user']['id'])) {
    $userId = $_SESSION['user']['id'];
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    logActivity($userId, 'logout', "User logged out");
}

if (isset($_COOKIE[COOKIE_NAME])) {
    setcookie(COOKIE_NAME, '', time() - 3600, '/');
}

session_destroy();
session_write_close();

jsonResponse(['message' => 'Logged out successfully']);
