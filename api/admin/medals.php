<?php
/**
 * Medal API — Grant and manage user medals
 */
require_once __DIR__ . '/config.php';

$db = getDB();
$user = requireAdmin();

// GET: List medals
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $medals = $db->query("SELECT * FROM medals ORDER BY sort_order ASC");
    $list = [];
    while ($m = $medals->fetch_assoc()) {
        $list[] = ['id' => (int) $m['id'], 'name' => $m['name'], 'description' => $m['description'],
                   'icon' => $m['icon'], 'color' => $m['color'], 'type' => $m['type']];
    }
    jsonResponse(['medals' => $list]);
}

// POST: Grant medal to user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $targetId = (int) ($input['user_id'] ?? 0);
    $medalId  = (int) ($input['medal_id'] ?? 0);
    $reason   = trim($input['reason'] ?? '');

    if (!$targetId || !$medalId) jsonResponse(['error' => 'User ID and Medal ID required'], 400);

    $stmt = $db->prepare("INSERT INTO user_medals (user_id, medal_id, granted_by, reason) VALUES (?,?,?,?)");
    $stmt->bind_param('iiis', $targetId, $medalId, $user['id'], $reason);
    $stmt->execute();
    $stmt->close();

    logActivity($user['id'], 'grant_medal', "Granted medal ID: $medalId to user ID: $targetId - Reason: $reason");

    jsonResponse(['message' => 'Medal granted successfully']);
}

// DELETE: Remove medal
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $grantId = (int) ($_GET['id'] ?? 0);
    if (!$grantId) jsonResponse(['error' => 'Grant ID required'], 400);
    $db->query("DELETE FROM user_medals WHERE id = $grantId");
    jsonResponse(['message' => 'Medal removed']);
}
