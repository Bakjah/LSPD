<?php
/**
 * Complete Forum Setup
 * Run: http://localhost/!Project/LSPD/database/complete-setup.php
 */

echo "<!DOCTYPE html>
<html><head>
<title>LSPD Portal - Complete Setup</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #0F172A; color: #F8FAFC; padding: 40px; }
    .container { max-width: 900px; margin: 0 auto; }
    h1 { color: #9925EB; }
    .card { background: #1E293B; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 12px; }
    .success { border-left: 4px solid #22C55E; }
    .error { border-left: 4px solid #EF4444; }
    .info { border-left: 4px solid #3B82F6; }
    .warning { border-left: 4px solid #F59E0B; }
    pre { background: #0F172A; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
    .btn { display: inline-block; background: #9925EB; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin: 5px; }
    .btn:hover { background: #7C3AED; }
</style>
</head><body>
<div class='container'>
<h1>🏛️ Complete Forum Setup</h1>";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=lspd_portal", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $messages = [];

    // ============================================================
    // Create missing tables
    // ============================================================
    $tables = [
        'polls' => "CREATE TABLE `polls` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` CHAR(36) NOT NULL,
            `topic_id` INT UNSIGNED NOT NULL UNIQUE,
            `question` VARCHAR(500) NOT NULL,
            `max_options` INT UNSIGNED DEFAULT 1,
            `expires_at` DATETIME DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `topic_id` (`topic_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'poll_options' => "CREATE TABLE `poll_options` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` CHAR(36) NOT NULL,
            `poll_id` INT UNSIGNED NOT NULL,
            `option_text` VARCHAR(300) NOT NULL,
            `sort_order` INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `poll_id` (`poll_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'poll_votes' => "CREATE TABLE `poll_votes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `poll_id` INT UNSIGNED NOT NULL,
            `option_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `poll_user` (`poll_id`, `user_id`),
            KEY `poll_id` (`poll_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'topic_reactions' => "CREATE TABLE `topic_reactions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `topic_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `reaction` VARCHAR(50) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
            KEY `topic_id` (`topic_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'post_reactions' => "CREATE TABLE `post_reactions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `reaction` VARCHAR(50) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `post_user` (`post_id`, `user_id`),
            KEY `post_id` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'topic_watch' => "CREATE TABLE `topic_watch` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `topic_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
            KEY `topic_id` (`topic_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'topic_bookmarks' => "CREATE TABLE `topic_bookmarks` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `topic_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
            KEY `topic_id` (`topic_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tables as $name => $sql) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$name'");
        if ($stmt->rowCount() === 0) {
            try {
                $pdo->exec($sql);
                $messages[] = ['success', "✅ Table <code>$name</code> created"];
            } catch (PDOException $e) {
                $messages[] = ['error', "❌ $name: " . $e->getMessage()];
            }
        } else {
            $messages[] = ['warning', "⏭️ Table <code>$name</code> exists"];
        }
        $stmt->closeCursor();
    }

    // ============================================================
    // Insert Sample Data
    // ============================================================

    // Forums
    $stmt = $pdo->query("SELECT COUNT(*) FROM forums");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO forums (uuid, department_id, name, slug, description, icon, color, sort_order) VALUES
            (UUID(), 2, 'LSPD General', 'lspd-general', 'General discussions for LSPD personnel', '💬', '#2563EB', 1),
            (UUID(), 2, 'LSPD Announcements', 'lspd-announcements', 'Official announcements from command', '📢', '#2563EB', 2),
            (UUID(), 2, 'LSPD Recruitment', 'lspd-recruitment', 'Recruitment applications', '📋', '#2563EB', 3),
            (UUID(), 3, 'LSSD General', 'lssd-general', 'General discussions for LSSD', '💬', '#92400E', 1),
            (UUID(), 3, 'LSSD Announcements', 'lssd-announcements', 'Official announcements', '📢', '#92400E', 2),
            (UUID(), 4, 'LSFD General', 'lsfd-general', 'General discussions for LSFD', '💬', '#DC2626', 1),
            (UUID(), 4, 'LSFD Announcements', 'lsfd-announcements', 'Official announcements', '📢', '#DC2626', 2),
            (UUID(), 5, 'LSN General', 'lsn-general', 'General discussions for LSN', '💬', '#EA580C', 1),
            (UUID(), NULL, 'Community Hub', 'community-hub', 'General community discussions', '🌐', '#9925EB', 1),
            (UUID(), NULL, 'Introductions', 'introductions', 'Introduce yourself', '👋', '#9925EB', 2)");
        $messages[] = ['success', '✅ Forums inserted'];
    } else {
        $messages[] = ['warning', '⏭️ Forums already exist'];
    }

    // Categories
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO categories (uuid, forum_id, name, slug, description, icon, color, sort_order) VALUES
            (UUID(), 1, 'General Discussion', 'lspd-general-chat', 'Chat about anything LSPD', '💬', '#2563EB', 1),
            (UUID(), 1, 'Patrol Operations', 'lspd-patrol', 'Patrol-related discussions', '🚔', '#2563EB', 2),
            (UUID(), 2, 'Official Orders', 'official-orders', 'Orders from command', '📜', '#2563EB', 1),
            (UUID(), 3, 'General Discussion', 'lssd-general-chat', 'Chat about anything LSSD', '💬', '#92400E', 1),
            (UUID(), 4, 'General Discussion', 'lsfd-general-chat', 'Chat about anything LSFD', '💬', '#DC2626', 1),
            (UUID(), 5, 'General Discussion', 'lsn-general-chat', 'Chat about anything LSN', '💬', '#EA580C', 1),
            (UUID(), 6, 'General Chat', 'community-chat', 'Community general chat', '💬', '#9925EB', 1),
            (UUID(), 7, 'Introductions', 'new-introductions', 'Introduce yourself', '👋', '#9925EB', 1)");
        $messages[] = ['success', '✅ Categories inserted'];
    } else {
        $messages[] = ['warning', '⏭️ Categories already exist'];
    }

    // Tags
    $stmt = $pdo->query("SELECT COUNT(*) FROM topic_tags");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO topic_tags (uuid, name, slug, color) VALUES
            (UUID(), 'Announcement', 'announcement', '#F59E0B'),
            (UUID(), 'Important', 'important', '#EF4444'),
            (UUID(), 'Discussion', 'discussion', '#3B82F6'),
            (UUID(), 'Question', 'question', '#8B5CF6'),
            (UUID(), 'Guide', 'guide', '#22C55E'),
            (UUID(), 'Resolved', 'resolved', '#6B7280')");
        $messages[] = ['success', '✅ Tags inserted'];
    } else {
        $messages[] = ['warning', '⏭️ Tags already exist'];
    }

    // Sample Topic
    $stmt = $pdo->query("SELECT COUNT(*) FROM topics");
    if ($stmt->fetchColumn() == 0) {
        // Get admin user
        $stmt = $pdo->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            $topicUuid = bin2hex(random_bytes(16));
            $postUuid = bin2hex(random_bytes(16));
            $topicSlug = 'welcome-to-lspd-portal-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $content = "[b]Welcome to Los Santos Roleplay Community Portal![/b]\n\nThis is the official forum for all LSPD, LSSD, LSFD, and LSN personnel.\n\n[b]Forum Rules:[/b]\n[list]\n[*]Maintain professional conduct\n[*]Respect all members\n[*]Follow community guidelines\n[*]Report issues to staff\n[/list]\n\nWelcome aboard!";

            $pdo->exec("INSERT INTO topics (uuid, category_id, user_id, title, slug, content, views, replies, is_pinned, created_at)
                        VALUES ('$topicUuid', 1, {$admin['id']}, 'Welcome to LSPD Portal Forum', '$topicSlug', '$content', 1, 0, 1, NOW())");

            $topicId = $pdo->lastInsertId();

            $pdo->exec("INSERT INTO posts (uuid, topic_id, user_id, content, is_first_post, created_at)
                        VALUES ('$postUuid', $topicId, {$admin['id']}, '$content', 1, NOW())");

            $messages[] = ['success', '✅ Sample topic created'];
        }
    } else {
        $messages[] = ['warning', '⏭️ Topics already exist'];
    }

    $messages[] = ['success', '🎉 SETUP COMPLETE!'];

} catch (Exception $e) {
    $messages[] = ['error', '❌ Error: ' . $e->getMessage()];
}

// Show messages
foreach ($messages as $msg) {
    [$type, $text] = $msg;
    echo "<div class='card $type'><p>$text</p></div>";
}

// API Test Links
echo "<div class='card info'>
    <h3>🔗 Test APIs</h3>
    <pre>
GET /api/portal.php    - Portal Homepage
GET /api/forums.php     - Forum List
GET /api/topics.php     - Topics List
GET /api/topics.php?id=X - Single Topic</pre>
    <a href='../api/portal.php' class='btn' target='_blank'>Portal API</a>
    <a href='../api/forums.php' class='btn' target='_blank'>Forums API</a>
    <a href='../api/topics.php' class='btn' target='_blank'>Topics API</a>
</div>";

echo "</div></body></html>";
