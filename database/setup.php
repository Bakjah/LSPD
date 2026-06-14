<?php
/**
 * Database Setup Script
 * Run via browser: http://localhost/!Project/LSPD/database/setup.php
 */

$messages = [];

echo "<!DOCTYPE html>
<html><head>
<title>LSPD Portal - Database Setup</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #0F172A; color: #F8FAFC; padding: 40px; }
    .container { max-width: 900px; margin: 0 auto; }
    h1 { color: #9925EB; margin-bottom: 30px; }
    .card { background: #1E293B; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 12px; }
    .success { border-left: 4px solid #22C55E; }
    .error { border-left: 4px solid #EF4444; }
    .info { border-left: 4px solid #3B82F6; }
    .warning { border-left: 4px solid #F59E0B; }
    pre { background: #0F172A; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 12px; max-height: 300px; }
    code { color: #F59E0B; }
    .btn { display: inline-block; background: #9925EB; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin: 5px; }
    .btn:hover { background: #7C3AED; }
    .btn-green { background: #22C55E; }
    .btn-green:hover { background: #16A34A; }
</style>
</head><body>
<div class='container'>
    <h1>🏛️ LSPD Portal - Database Setup</h1>";

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=localhost", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $messages[] = ['success', '✅ Connected to MySQL server'];

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS lspd_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE lspd_portal");
    $messages[] = ['success', '✅ Database <code>lspd_portal</code> ready'];

    // Check existing tables
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[$row[0]] = true;
    }
    $stmt->closeCursor();

    // ============================================================
    // Create Main Schema Tables
    // ============================================================
    $mainTables = ['users', 'departments', 'roles', 'permissions', 'role_permissions',
                   'user_roles', 'user_departments', 'user_medals', 'refresh_tokens',
                   'settings', 'activity_logs', 'login_logs'];

    if (empty($existingTables['users'])) {
        $messages[] = ['info', '📦 Creating main schema tables...'];

        // Run setup.sql
        $sql = file_get_contents(__DIR__ . '/schema/setup.sql');
        $pdo->exec($sql);

        $messages[] = ['success', '✅ Main schema tables created'];
    } else {
        $messages[] = ['warning', '⏭️ Main schema tables already exist'];
    }

    // Refresh existing tables
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[$row[0]] = true;
    }
    $stmt->closeCursor();

    // ============================================================
    // Create Forum Tables
    // ============================================================
    $forumTables = ['forums', 'categories', 'topics', 'posts', 'topic_tags', 'topic_tag_map',
                    'polls', 'poll_options', 'poll_votes', 'topic_reactions', 'post_reactions',
                    'topic_watch', 'topic_bookmarks'];

    $missingForums = [];
    foreach ($forumTables as $table) {
        if (!isset($existingTables[$table])) {
            $missingForums[] = $table;
        }
    }

    if (!empty($missingForums)) {
        $messages[] = ['info', '📦 Creating forum tables (' . count($missingForums) . ' missing)...'];

        // Read forum SQL
        $sql = file_get_contents(__DIR__ . '/schema/forum.sql');

        // Split by semicolon and execute each statement
        $statements = [];
        $buffer = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && ($i === 0 || $sql[$i-1] !== '\\')) {
                $inString = false;
            } elseif (!$inString && $char === ';') {
                $stmt = trim($buffer);
                if (!empty($stmt) && stripos($stmt, 'DROP TABLE') === false) {
                    $statements[] = $stmt;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        // Execute statements
        foreach ($statements as $stmt) {
            // Skip comments and empty statements
            if (trim($stmt) === '' || strpos(trim($stmt), '--') === 0 || strpos(trim($stmt), '/*') === 0) {
                continue;
            }

            // Skip if table already exists
            if (stripos($stmt, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*?`(\w+)`/i', $stmt, $match);
                if ($match && isset($existingTables[$match[1]])) {
                    continue;
                }
            }

            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    // Log but continue
                }
            }
        }

        $messages[] = ['success', '✅ Forum tables created'];
    } else {
        $messages[] = ['warning', '⏭️ Forum tables already exist'];
    }

    // ============================================================
    // Create Admin User
    // ============================================================
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt->closeCursor();

        $uuid = bin2hex(random_bytes(16));
        $passwordHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);

        $pdo->exec("INSERT INTO users (uuid, username, email, password, is_verified, is_active, created_at, last_seen)
                    VALUES ('$uuid', 'admin', 'admin@lspd-portal.local', '$passwordHash', 1, 1, NOW(), NOW())");

        // Assign admin role
        $stmt = $pdo->query("SELECT id FROM roles WHERE slug = 'administrator'");
        $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($adminRole) {
            $stmt = $pdo->query("SELECT id FROM users WHERE username = 'admin'");
            $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($adminUser) {
                $pdo->exec("INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
                            VALUES ({$adminUser['id']}, {$adminRole['id']}, NULL, NOW())");
            }
        }

        $messages[] = ['success', '✅ Admin user created (<code>admin</code> / <code>admin123</code>)'];
    } else {
        $stmt->closeCursor();
        $messages[] = ['warning', '⏭️ Admin user already exists'];
    }

    // ============================================================
    // Create Test Users
    // ============================================================
    $testUsers = [['john_doe', 'john.doe@example.com'], ['jane_smith', 'jane.smith@example.com'], ['bob_wilson', 'bob.wilson@example.com']];
    $created = 0;

    foreach ($testUsers as $user) {
        $username = $user[0];
        $email = $user[1];

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            $stmt->closeCursor();

            $uuid = bin2hex(random_bytes(16));
            $passwordHash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);

            $pdo->exec("INSERT INTO users (uuid, username, email, password, is_verified, is_active, created_at, last_seen)
                        VALUES ('$uuid', '$username', '$email', '$passwordHash', 1, 1, NOW(), NOW())");

            $stmt = $pdo->query("SELECT id FROM roles WHERE slug = 'community-member'");
            $memberRole = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($memberRole) {
                $stmt = $pdo->query("SELECT id FROM users WHERE username = '$username'");
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                if ($u) {
                    $pdo->exec("INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
                                VALUES ({$u['id']}, {$memberRole['id']}, NULL, NOW())");
                }
            }

            $created++;
        } else {
            $stmt->closeCursor();
        }
    }

    if ($created > 0) {
        $messages[] = ['success', "✅ Created {$created} test users"];
    } else {
        $messages[] = ['warning', '⏭️ Test users already exist'];
    }

    // ============================================================
    // Verify Tables
    // ============================================================
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $allTables[] = $row[0];
    }
    $stmt->closeCursor();

    $messages[] = ['success', '🎉 SETUP COMPLETE! <code>' . count($allTables) . '</code> tables ready'];

} catch (Exception $e) {
    $messages[] = ['error', '❌ Error: ' . htmlspecialchars($e->getMessage())];
}

// Display messages
foreach ($messages as $msg) {
    [$type, $text] = $msg;
    echo "<div class='card $type'><p>$text</p></div>";
}

// API Test Links
echo "<div class='card info'>
    <h3>🔗 Test APIs</h3>
    <pre>
GET /api/portal.php   - Portal Homepage
GET /api/forums.php    - Forum List
GET /api/topics.php    - Topics List</pre>
    <a href='../api/portal.php' class='btn' target='_blank'>Portal API</a>
    <a href='../api/forums.php' class='btn btn-green' target='_blank'>Forums API</a>
</div>";

echo "</div></body></html>";
