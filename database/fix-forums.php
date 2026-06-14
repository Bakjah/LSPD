<?php
/**
 * Fix Forum Tables
 * Run via browser: http://localhost/!Project/LSPD/database/fix-forums.php
 */

echo "<!DOCTYPE html>
<html><head>
<title>Fix Forum Tables</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #0F172A; color: #F8FAFC; padding: 40px; }
    .container { max-width: 900px; margin: 0 auto; }
    .card { background: #1E293B; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 12px; }
    .success { border-left: 4px solid #22C55E; }
    .error { border-left: 4px solid #EF4444; }
    pre { background: #0F172A; padding: 16px; border-radius: 8px; overflow-x: auto; }
</style>
</head><body>
<div class='container'>
<h1>🔧 Fix Forum Tables</h1>";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=lspd_portal", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Check current tables
    echo "<div class='card'><h3>Current Tables:</h3><pre>";
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo $row[0] . "\n";
    }
    echo "</pre></div>";

    // Create missing tables
    $missingTables = ['forums', 'categories', 'topics', 'posts', 'topic_tags', 'topic_tag_map',
                      'polls', 'poll_options', 'poll_votes', 'topic_reactions', 'post_reactions',
                      'topic_watch', 'topic_bookmarks'];

    foreach ($missingTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            echo "<div class='card'><p>Creating <strong>$table</strong>...</p>";

            // Create table based on type
            switch($table) {
                case 'forums':
                    $pdo->exec("CREATE TABLE `forums` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `uuid` CHAR(36) NOT NULL,
                        `department_id` INT UNSIGNED DEFAULT NULL,
                        `name` VARCHAR(200) NOT NULL,
                        `slug` VARCHAR(200) NOT NULL,
                        `description` TEXT,
                        `icon` VARCHAR(100) DEFAULT NULL,
                        `color` VARCHAR(7) DEFAULT '#3B82F6',
                        `sort_order` INT UNSIGNED DEFAULT 0,
                        `is_active` TINYINT(1) DEFAULT 1,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `uuid` (`uuid`),
                        UNIQUE KEY `slug` (`slug`),
                        KEY `department_id` (`department_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'categories':
                    $pdo->exec("CREATE TABLE `categories` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `uuid` CHAR(36) NOT NULL,
                        `forum_id` INT UNSIGNED NOT NULL,
                        `name` VARCHAR(200) NOT NULL,
                        `slug` VARCHAR(200) NOT NULL,
                        `description` TEXT,
                        `icon` VARCHAR(100) DEFAULT NULL,
                        `color` VARCHAR(7) DEFAULT '#374151',
                        `sort_order` INT UNSIGNED DEFAULT 0,
                        `is_active` TINYINT(1) DEFAULT 1,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `uuid` (`uuid`),
                        KEY `forum_id` (`forum_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'topics':
                    $pdo->exec("CREATE TABLE `topics` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `uuid` CHAR(36) NOT NULL,
                        `category_id` INT UNSIGNED NOT NULL,
                        `user_id` INT UNSIGNED NOT NULL,
                        `title` VARCHAR(300) NOT NULL,
                        `slug` VARCHAR(350) NOT NULL,
                        `content` LONGTEXT NOT NULL,
                        `views` INT UNSIGNED DEFAULT 0,
                        `replies` INT UNSIGNED DEFAULT 0,
                        `likes` INT UNSIGNED DEFAULT 0,
                        `is_pinned` TINYINT(1) DEFAULT 0,
                        `is_locked` TINYINT(1) DEFAULT 0,
                        `is_sticky` TINYINT(1) DEFAULT 0,
                        `is_archived` TINYINT(1) DEFAULT 0,
                        `is_deleted` TINYINT(1) DEFAULT 0,
                        `deleted_by` INT UNSIGNED DEFAULT NULL,
                        `deleted_at` DATETIME DEFAULT NULL,
                        `prefix` VARCHAR(50) DEFAULT NULL,
                        `last_reply_at` DATETIME DEFAULT NULL,
                        `last_reply_by` INT UNSIGNED DEFAULT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `uuid` (`uuid`),
                        KEY `category_id` (`category_id`),
                        KEY `user_id` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'posts':
                    $pdo->exec("CREATE TABLE `posts` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `uuid` CHAR(36) NOT NULL,
                        `topic_id` INT UNSIGNED NOT NULL,
                        `user_id` INT UNSIGNED NOT NULL,
                        `content` LONGTEXT NOT NULL,
                        `likes` INT UNSIGNED DEFAULT 0,
                        `is_first_post` TINYINT(1) DEFAULT 0,
                        `is_deleted` TINYINT(1) DEFAULT 0,
                        `deleted_by` INT UNSIGNED DEFAULT NULL,
                        `deleted_at` DATETIME DEFAULT NULL,
                        `edit_count` INT UNSIGNED DEFAULT 0,
                        `last_edited_at` DATETIME DEFAULT NULL,
                        `last_edited_by` INT UNSIGNED DEFAULT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `uuid` (`uuid`),
                        KEY `topic_id` (`topic_id`),
                        KEY `user_id` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'topic_tags':
                    $pdo->exec("CREATE TABLE `topic_tags` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `uuid` CHAR(36) NOT NULL,
                        `name` VARCHAR(50) NOT NULL,
                        `slug` VARCHAR(50) NOT NULL,
                        `color` VARCHAR(7) DEFAULT '#6B7280',
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `name` (`name`),
                        UNIQUE KEY `slug` (`slug`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'topic_tag_map':
                    $pdo->exec("CREATE TABLE `topic_tag_map` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `topic_id` INT UNSIGNED NOT NULL,
                        `tag_id` INT UNSIGNED NOT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `topic_tag` (`topic_id`, `tag_id`),
                        KEY `topic_id` (`topic_id`),
                        KEY `tag_id` (`tag_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'polls':
                    $pdo->exec("CREATE TABLE `polls` (
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
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'poll_options':
                    $pdo->exec("CREATE TABLE `poll_options` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `uuid` CHAR(36) NOT NULL,
                        `poll_id` INT UNSIGNED NOT NULL,
                        `option_text` VARCHAR(300) NOT NULL,
                        `sort_order` INT UNSIGNED DEFAULT 0,
                        PRIMARY KEY (`id`),
                        KEY `poll_id` (`poll_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'poll_votes':
                    $pdo->exec("CREATE TABLE `poll_votes` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `poll_id` INT UNSIGNED NOT NULL,
                        `option_id` INT UNSIGNED NOT NULL,
                        `user_id` INT UNSIGNED NOT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `poll_user` (`poll_id`, `user_id`),
                        KEY `poll_id` (`poll_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'topic_reactions':
                    $pdo->exec("CREATE TABLE `topic_reactions` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `topic_id` INT UNSIGNED NOT NULL,
                        `user_id` INT UNSIGNED NOT NULL,
                        `reaction` VARCHAR(50) NOT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
                        KEY `topic_id` (`topic_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'post_reactions':
                    $pdo->exec("CREATE TABLE `post_reactions` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `post_id` INT UNSIGNED NOT NULL,
                        `user_id` INT UNSIGNED NOT NULL,
                        `reaction` VARCHAR(50) NOT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `post_user` (`post_id`, `user_id`),
                        KEY `post_id` (`post_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'topic_watch':
                    $pdo->exec("CREATE TABLE `topic_watch` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `topic_id` INT UNSIGNED NOT NULL,
                        `user_id` INT UNSIGNED NOT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
                        KEY `topic_id` (`topic_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;

                case 'topic_bookmarks':
                    $pdo->exec("CREATE TABLE `topic_bookmarks` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `topic_id` INT UNSIGNED NOT NULL,
                        `user_id` INT UNSIGNED NOT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
                        KEY `topic_id` (`topic_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    break;
            }

            echo "<span style='color:#22C55E'>✅ Created</span></div>";
        } else {
            echo "<div class='card'><p><strong>$table</strong> - Already exists</p></div>";
        }
        $stmt->closeCursor();
    }

    // Insert sample data
    echo "<div class='card'><h3>Inserting Sample Data...</h3>";

    // Check if forums exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM forums");
    if ($stmt->fetchColumn() == 0) {
        // Insert forums
        $pdo->exec("INSERT INTO forums (uuid, department_id, name, slug, description, icon, color, sort_order) VALUES
            (UUID(), 2, 'LSPD General', 'lspd-general', 'General discussions for LSPD', '💬', '#2563EB', 1),
            (UUID(), 2, 'LSPD Announcements', 'lspd-announcements', 'Official announcements', '📢', '#2563EB', 2),
            (UUID(), 3, 'LSSD General', 'lssd-general', 'General discussions for LSSD', '💬', '#92400E', 1),
            (UUID(), 4, 'LSFD General', 'lsfd-general', 'General discussions for LSFD', '💬', '#DC2626', 1),
            (UUID(), 5, 'LSN General', 'lsn-general', 'General discussions for LSN', '💬', '#EA580C', 1),
            (UUID(), NULL, 'Community Hub', 'community-hub', 'Community discussions', '🌐', '#9925EB', 1)");

        echo "<span style='color:#22C55E'>✅ Forums inserted</span><br>";
    }

    // Check if categories exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO categories (uuid, forum_id, name, slug, description, icon, color, sort_order) VALUES
            (UUID(), 1, 'General Discussion', 'general-discussion', 'General chat', '💬', '#2563EB', 1),
            (UUID(), 1, 'Patrol Operations', 'patrol-operations', 'Patrol topics', '🚔', '#2563EB', 2),
            (UUID(), 2, 'Official Orders', 'official-orders', 'Official orders', '📜', '#2563EB', 1),
            (UUID(), 3, 'General Discussion', 'lssd-general-discussion', 'General chat', '💬', '#92400E', 1),
            (UUID(), 4, 'General Discussion', 'lsfd-general-discussion', 'General chat', '💬', '#DC2626', 1),
            (UUID(), 5, 'General Discussion', 'lsn-general-discussion', 'General chat', '💬', '#EA580C', 1),
            (UUID(), 6, 'General Chat', 'general-chat', 'Community chat', '💬', '#9925EB', 1)");

        echo "<span style='color:#22C55E'>✅ Categories inserted</span><br>";
    }

    // Check if tags exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM topic_tags");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO topic_tags (uuid, name, slug, color) VALUES
            (UUID(), 'Announcement', 'announcement', '#F59E0B'),
            (UUID(), 'Important', 'important', '#EF4444'),
            (UUID(), 'Discussion', 'discussion', '#3B82F6'),
            (UUID(), 'Question', 'question', '#8B5CF6')");

        echo "<span style='color:#22C55E'>✅ Tags inserted</span><br>";
    }

    echo "</div>";

    echo "<div class='card success'><h3>✅ All Forum Tables Ready!</h3>
        <a href='../api/forums.php' target='_blank' style='color:#22C55E'>Test Forums API →</a>
    </div>";

} catch (Exception $e) {
    echo "<div class='card error'><h3>❌ Error</h3><pre>" . $e->getMessage() . "</pre></div>";
}

echo "</div></body></html>";
