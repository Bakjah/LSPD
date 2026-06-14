<?php
/**
 * Phase 4 Setup - Admin Tables & Initial Data
 * Run: http://localhost/!Project/LSPD/database/phase4-setup.php
 */

echo '<!DOCTYPE html>
<html><head>
<title>Phase 4 Setup</title>
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
<h1>⚙️ Phase 4 Setup - Admin Panel, Logs, Settings</h1>';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=lspd_portal", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $messages = [];

    // ============================================================
    // Check/Add Tables
    // ============================================================
    $tables = ['activity_logs', 'login_logs', 'notifications', 'private_messages', 'medals', 'user_medals'];

    $stmt = $pdo->query("SHOW TABLES");
    $existing = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existing[$row[0]] = true;
    }
    $stmt->closeCursor();

    $missing = [];
    foreach ($tables as $t) {
        if (!isset($existing[$t])) {
            $missing[] = $t;
        }
    }

    if (!empty($missing)) {
        $messages[] = ['warning', '⏭️ Missing tables: ' . implode(', ', $missing)];
        $messages[] = ['info', '📝 Run phase3-setup.php first to create these tables'];
    } else {
        $messages[] = ['success', '✅ All Phase 3 tables exist'];
    }

    // ============================================================
    // Ensure settings exist
    // ============================================================
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $stmt->closeCursor();

        $settings = [
            ['site_name', 'Los Santos Roleplay Community', 'string', 'general', 'Website name'],
            ['site_tagline', 'To Protect and Serve', 'string', 'general', 'Tagline'],
            ['site_url', 'http://localhost', 'string', 'general', 'Site URL'],
            ['allow_registration', '1', 'bool', 'auth', 'Allow new registrations'],
            ['jwt_secret', bin2hex(random_bytes(32)), 'string', 'auth', 'JWT Secret'],
            ['topics_per_page', '20', 'int', 'forum', 'Topics per page'],
            ['posts_per_page', '15', 'int', 'forum', 'Posts per page'],
            ['maintenance_mode', '0', 'bool', 'maintenance', 'Maintenance mode'],
        ];

        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`, `type`, `group`, `autoload`, `description`) VALUES (?, ?, ?, ?, 1, ?)");
        foreach ($settings as $s) {
            $stmt->execute($s);
        }
        $stmt->closeCursor();

        $messages[] = ['success', '✅ Created default settings'];
    } else {
        $stmt->closeCursor();
        $messages[] = ['warning', '⏭️ Settings already exist'];
    }

    // ============================================================
    // Ensure activity_logs table exists
    // ============================================================
    $stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
    if ($stmt->rowCount() == 0) {
        $stmt->closeCursor();
        $pdo->exec("CREATE TABLE `activity_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `action` VARCHAR(100) NOT NULL,
            `entity_type` VARCHAR(50) DEFAULT NULL,
            `entity_id` INT UNSIGNED DEFAULT NULL,
            `details` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `action` (`action`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $messages[] = ['success', '✅ Created activity_logs table'];
    } else {
        $stmt->closeCursor();
    }

    // ============================================================
    // Ensure login_logs table exists
    // ============================================================
    $stmt = $pdo->query("SHOW TABLES LIKE 'login_logs'");
    if ($stmt->rowCount() == 0) {
        $stmt->closeCursor();
        $pdo->exec("CREATE TABLE `login_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `username` VARCHAR(50) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('success','failed','locked','banned') NOT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `reason` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `username` (`username`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $messages[] = ['success', '✅ Created login_logs table'];
    } else {
        $stmt->closeCursor();
    }

    // ============================================================
    // Create sample activity
    // ============================================================
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
    if ($stmt->fetchColumn() == 0) {
        $stmt->closeCursor();

        $stmt = $pdo->query("SELECT id FROM users LIMIT 3");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $actions = ['user_register', 'user_login', 'profile_update'];
        foreach ($users as $i => $u) {
            foreach (array_slice($actions, 0, rand(1, 3)) as $action) {
                $pdo->exec("INSERT INTO activity_logs (user_id, action, details, created_at)
                            VALUES ({$u['id']}, '$action', NULL, DATE_SUB(NOW(), INTERVAL " . rand(0, 30) . " DAY))");
            }
        }
        $messages[] = ['success', '✅ Created sample activity logs'];
    } else {
        $stmt->closeCursor();
        $messages[] = ['warning', '⏭️ Activity logs already exist'];
    }

    // ============================================================
    // Verify admin user has admin role
    // ============================================================
    $stmt = $pdo->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($admin) {
        $stmt = $pdo->query("SELECT id FROM roles WHERE slug = 'administrator' LIMIT 1");
        $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($adminRole) {
            $stmt = $pdo->prepare("SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?");
            $stmt->execute([$admin['id'], $adminRole['id']]);
            if (!$stmt->fetch()) {
                $stmt->closeCursor();
                $pdo->exec("INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
                            VALUES ({$admin['id']}, {$adminRole['id']}, NULL, NOW())");
                $messages[] = ['success', '✅ Admin user assigned administrator role'];
            } else {
                $stmt->closeCursor();
                $messages[] = ['warning', '⏭️ Admin already has administrator role'];
            }
        }
    }

    // ============================================================
    // Summary
    // ============================================================
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    $stmt->closeCursor();

    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
    $logCount = $stmt->fetchColumn();
    $stmt->closeCursor();

    $messages[] = ['success', "🎉 PHASE 4 SETUP COMPLETE! ($userCount users, $logCount logs)"];

} catch (Exception $e) {
    $messages[] = ['error', '❌ Error: ' . $e->getMessage()];
}

foreach ($messages as $msg) {
    [$type, $text] = $msg;
    echo "<div class='card $type'><p>$text</p></div>";
}

echo '<div class="card" style="border-left:4px solid #3B82F6">
    <h3>🔗 Test Phase 4 APIs</h3>
    <pre>
GET /api/admin.php?action=dashboard  - Admin Dashboard (admin)
GET /api/admin.php?action=users       - User Management (admin)
GET /api/admin.php?action=settings   - Settings (admin)
GET /api/logs.php?type=activity      - Activity Logs (staff)
GET /api/logs.php?type=login         - Login Logs (staff)</pre>
    <a href="../api/admin.php?action=dashboard" class="btn" target="_blank">Admin API</a>
    <a href="../api/logs.php" class="btn" target="_blank">Logs API</a>
</div>';

echo "</div></body></html>";
