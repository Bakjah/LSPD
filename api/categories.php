<?php
/**
 * Categories List API (public — all categories with forum info)
 */
require_once __DIR__ . '/config.php';
startSession();

header('Content-Type: application/json');

$db = getDB();
$result = $db->query("
  SELECT c.id, c.name, c.description, c.forum_id, c.slug,
    c.thread_count, c.post_count, c.last_thread_id,
    f.name as forum_name, f.slug as forum_slug,
    lt.title as last_thread_title, lt.slug as last_thread_slug,
    u.username as last_poster
  FROM categories c
  LEFT JOIN forums f ON c.forum_id = f.id
  LEFT JOIN threads lt ON c.last_thread_id = lt.id
  LEFT JOIN users u ON lt.last_poster_id = u.id
  WHERE c.is_active = 1
  ORDER BY f.display_order, c.display_order
");

$categories = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

jsonResponse(['categories' => $categories]);
