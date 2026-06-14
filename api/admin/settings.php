<?php
/**
 * Settings Save API
 */
require_once __DIR__ . '/config.php';
startSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !is_array($data)) {
    jsonResponse(['error' => 'Invalid JSON data.'], 400);
}

$db = getDB();
$stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())
  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");

$updated = 0;
foreach ($data as $key => $value) {
    $key = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($key));
    if (empty($key)) continue;
    $value = is_scalar($value) ? (string) $value : json_encode($value);
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $updated++;
}
$stmt->close();

logActivity($_SESSION['user_id'], 'settings_update', "Updated {$updated} setting(s)");

jsonResponse(['message' => "{$updated} setting(s) updated successfully.", 'updated' => $updated]);
