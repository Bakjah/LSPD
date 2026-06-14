<?php
/**
 * Reaction API — Toggle reactions on posts/threads
 */
require_once __DIR__ . '/config.php';

$db = getDB();
$user = requireAuth();

// POST: Toggle reaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type       = $input['type'] ?? ''; // 'post' or 'thread'
    $targetId   = (int) ($input['target_id'] ?? 0);
    $reactionId = (int) ($input['reaction_id'] ?? 0);

    if (!$type || !$targetId || !$reactionId) {
        jsonResponse(['error' => 'Type, target_id, and reaction_id are required'], 400);
    }

    if (!in_array($type, ['post', 'thread'], true)) {
        jsonResponse(['error' => 'Invalid type'], 400);
    }

    $userId = $user['id'];
    $table = $type === 'post' ? 'post_reactions' : 'thread_reactions';
    $idCol = $type === 'post' ? 'post_id' : 'thread_id';

    // Check if reaction exists
    $reactStmt = $db->prepare("SELECT id FROM reactions WHERE id = ? AND type = ?");
    $reactStmt->bind_param('is', $reactionId, $type);
    $reactStmt->execute();
    if ($reactStmt->get_result()->num_rows === 0) jsonResponse(['error' => 'Invalid reaction'], 404);
    $reactStmt->close();

    // Check if already reacted
    $checkStmt = $db->prepare("SELECT 1 FROM $table WHERE $idCol = ? AND user_id = ?");
    $checkStmt->bind_param('ii', $targetId, $userId);
    $checkStmt->execute();
    $alreadyReacted = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();

    if ($alreadyReacted) {
        // Remove reaction
        $delStmt = $db->prepare("DELETE FROM $table WHERE $idCol = ? AND user_id = ?");
        $delStmt->bind_param('ii', $targetId, $userId);
        $delStmt->execute();
        $delStmt->close();

        // Decrement likes
        $db->query("UPDATE " . ($type === 'post' ? 'posts' : 'threads') . " SET likes = GREATEST(0, likes - 1) WHERE id = $targetId");

        jsonResponse(['message' => 'Reaction removed', 'action' => 'removed']);
    } else {
        // Add reaction
        $addStmt = $db->prepare("INSERT INTO $table ($idCol, user_id, reaction_id) VALUES (?,?,?)");
        $addStmt->bind_param('iii', $targetId, $userId, $reactionId);
        $addStmt->execute();
        $addStmt->close();

        // Increment likes
        $db->query("UPDATE " . ($type === 'post' ? 'posts' : 'threads') . " SET likes = likes + 1 WHERE id = $targetId");

        jsonResponse(['message' => 'Reaction added', 'action' => 'added']);
    }
}

// GET: Get available reactions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? 'post';
    $result = $db->query("SELECT id, name, icon, color FROM reactions WHERE type = '$type' OR type = 'post'");
    $reactions = [];
    while ($r = $result->fetch_assoc()) {
        $reactions[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'icon' => $r['icon'], 'color' => $r['color']];
    }
    jsonResponse(['reactions' => $reactions]);
}
