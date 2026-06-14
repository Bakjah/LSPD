<?php
/**
 * Me API — Returns current authenticated user info
 */
require_once __DIR__ . '/config.php';

startSession();

if (empty($_SESSION['user'])) {
    jsonResponse(['user' => null, 'authenticated' => false]);
}

$user = requireAuth();
$user['permissions'] = getUserPermissions($user['id']);

jsonResponse([
    'user'          => $user,
    'authenticated' => true,
]);
