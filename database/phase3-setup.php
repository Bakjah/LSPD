<?php
/**
 * Phase 3 Setup - Notifications, PM, Medals Tables
 * Run: http://localhost/!Project/LSPD/database/phase3-setup.php
 */

echo '<!DOCTYPE html>
<html><head>
<title>Phase 3 Setup</title>
<style>
    body { font-family: "Segoe UI", sans-serif; background: #0F172A; color: #F8FAFC; padding: 40px; }
    .container { max-width: 900px; margin: 0 auto; }
    h1 { color: #9925EB; }
    .card { background: #1E293B; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 12px; }
    .success { border-left: 4px solid #22C55E; }
    .error { border-left: 4px solid #EF4444; }
    .warning { border-left: 4px solid #F59E0B; }
    pre { background: #0F172A; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
    .btn { display: inline-block; background: #9925EB; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin: 5px; }
</style>
</head><body>
<div class="container">
<h1>🎖️ Phase 3 Setup - Notifications, PM, Medals</h1>';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=lspd_portal", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $messages = [];

    // Create tables
    $tables = [
        'notifications' => "CREATE TABLE `notifications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `from_user_id` INT UNSIGNED DEFAULT NULL,
            `topic_id` INT UNSIGNED DEFAULT NULL,
            `post_id` INT UNSIGNED DEFAULT NULL,
            `message` VARCHAR(500) DEFAULT NULL,
            `data` TEXT DEFAULT NULL,
            `link` VARCHAR(255) DEFAULT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `read_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'private_messages' => "CREATE TABLE `private_messages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` CHAR(36) NOT NULL,
            `sender_id` INT UNSIGNED NOT NULL,
            `recipient_id` INT UNSIGNED NOT NULL,
            `subject` VARCHAR(300) NOT NULL,
            `content` LONGTEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `is_deleted_by_sender` TINYINT(1) DEFAULT 0,
            `is_deleted_by_recipient` TINYINT(1) DEFAULT 0,
            `is_starred` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uuid` (`uuid`),
            KEY `sender_id` (`sender_id`),
            KEY `recipient_id` (`recipient_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'medals' => "CREATE TABLE `medals` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` CHAR(36) NOT NULL,
            `department_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `icon` VARCHAR(100) DEFAULT NULL,
            `color` VARCHAR(7) DEFAULT '#F59E0B',
            `type` ENUM('valor','service','injury','community','training','other') DEFAULT 'other',
            `sort_order` INT UNSIGNED DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `department_id` (`department_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'user_medals' => "CREATE TABLE `user_medals` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `medal_id` INT UNSIGNED NOT NULL,
            `granted_by` INT UNSIGNED DEFAULT NULL,
            `reason` TEXT DEFAULT NULL,
            `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_medal` (`user_id`, `medal_id`),
            KEY `user_id` (`user_id`),
            KEY `medal_id` (`medal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    // Check existing tables
    $stmt = $pdo->query("SHOW TABLES");
    $existing = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existing[$row[0]] = true;
    }
    $stmt->closeCursor();

    foreach ($tables as $name => $sql) {
        if (isset($existing[$name])) {
            $messages[] = ['warning', "⏭️ Table <code>$name</code> exists"];
        } else {
            try {
                $pdo->exec($sql);
                $messages[] = ['success', "✅ Table <code>$name</code> created"];
            } catch (PDOException $e) {
                $messages[] = ['error', "❌ $name: " . $e->getMessage()];
            }
        }
    }

    // Insert Default Medals
    $stmt = $pdo->query("SELECT COUNT(*) FROM medals");
    if ($stmt->fetchColumn() == 0) {
        $stmt->closeCursor();

        $medals = [
            [2, 'Medal of Valor', 'Awarded for acts of extraordinary courage', '🏅', '#FFD700', 'valor'],
            [2, 'Purple Heart', 'Awarded for wounds sustained in the line of duty', '💜', '#8B5CF6', 'injury'],
            [2, 'Distinguished Service Medal', 'Awarded for exceptional service', '🎖️', '#2563EB', 'service'],
            [2, 'Meritorious Service Medal', 'Awarded for meritorious service above and beyond', '⭐', '#22C55E', 'service'],
            [3, "Sheriff's Medal", "The department's highest honor", '🎖️', '#92400E', 'valor'],
            [3, 'Outstanding Deputy Medal', 'Awarded for outstanding performance', '⭐', '#D97706', 'service'],
            [4, 'Fire Service Medal', 'Awarded for bravery in firefighting operations', '🚒', '#DC2626', 'valor'],
            [4, 'Rescue Medal', 'Awarded for life-saving rescue operations', '🏥', '#EF4444', 'valor'],
            [4, 'EMS Excellence Medal', 'Awarded for excellence in emergency medical services', '🚑', '#F87171', 'service'],
            [5, 'Journalist Excellence Award', 'Awarded for outstanding journalism', '📰', '#EA580C', 'community'],
            [5, 'Community Media Award', 'Awarded for community-focused reporting', '🎥', '#FB923C', 'community'],
            [NULL, 'Community Service Award', 'Awarded for significant community contributions', '🌟', '#F59E0B', 'community'],
            [NULL, 'Training Excellence', 'Awarded for outstanding training performance', '📚', '#3B82F6', 'training'],
        ];

        $stmt = $pdo->prepare("INSERT INTO medals (uuid, department_id, name, description, icon, color, type) VALUES (UUID(), ?, ?, ?, ?, ?, ?)");
        foreach ($medals as $m) {
            $stmt->execute($m);
        }
        $stmt->closeCursor();

        $messages[] = ['success', '✅ Inserted ' . count($medals) . ' default medals'];
    } else {
        $stmt->closeCursor();
        $messages[] = ['warning', '⏭️ Medals already exist'];
    }

    // Insert Sample Notification
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications");
    if ($stmt->fetchColumn() == 0) {
        $stmt->closeCursor();

        $stmt = $pdo->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($admin) {
            $pdo->exec("INSERT INTO notifications (user_id, type, message, created_at) VALUES ({$admin['id']}, 'system', 'Welcome to LSPD Portal! Start by introducing yourself in the community forum.', NOW())");
            $messages[] = ['success', '✅ Inserted sample notification'];
        }
    } else {
        $stmt->closeCursor();
        $messages[] = ['warning', '⏭️ Notifications already exist'];
    }

    $messages[] = ['success', '🎉 PHASE 3 SETUP COMPLETE!'];

} catch (Exception $e) {
    $messages[] = ['error', '❌ Error: ' . $e->getMessage()];
}

foreach ($messages as $msg) {
    [$type, $text] = $msg;
    echo "<div class='card $type'><p>$text</p></div>";
}

echo '<div class="card" style="border-left:4px solid #3B82F6">
    <h3>🔗 Test Phase 3 APIs</h3>
    <pre>
GET  /api/notifications.php  - Notifications (auth required)
GET  /api/messages.php        - Private Messages (auth required)
GET  /api/staff.php           - Staff Directory
GET  /api/medals.php          - Medals List</pre>
    <a href="../api/staff.php" class="btn" target="_blank">Staff API</a>
    <a href="../api/medals.php" class="btn" target="_blank">Medals API</a>
</div>';

echo "</div></body></html>";
