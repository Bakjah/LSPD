<?php
/**
 * Database Seed Script
 * Run after setup.sql to create initial data
 */

require_once __DIR__ . '/../api/helpers/database.php';
require_once __DIR__ . '/../api/helpers/jwt.php';

echo "==============================================\n";
echo "Los Santos Roleplay Community Portal\n";
echo "Database Seed Script\n";
echo "==============================================\n\n";

try {
    $db = getDB();

    // ============================================================
    // Create Administrator Account
    // ============================================================
    echo "Creating administrator account...\n";

    $adminUsername = 'admin';
    $adminEmail = 'admin@lspd-portal.local';
    $adminPassword = 'admin123'; // CHANGE THIS IN PRODUCTION!

    // Check if admin exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$adminUsername]);
    if ($stmt->fetch()) {
        echo "  Administrator account already exists.\n";
        $stmt->closeCursor();
    } else {
        $stmt->closeCursor();

        $uuid = generateUUID();
        $passwordHash = hashPassword($adminPassword);

        $stmt = $db->prepare("
            INSERT INTO users (uuid, username, email, password, is_verified, is_active, created_at, last_seen)
            VALUES (?, ?, ?, ?, 1, 1, NOW(), NOW())
        ");
        $stmt->execute([$uuid, $adminUsername, $adminEmail, $passwordHash]);
        $adminId = $db->lastInsertId();
        $stmt->closeCursor();

        // Assign Administrator role
        $stmt = $db->prepare("SELECT id FROM roles WHERE slug = 'administrator'");
        $stmt->execute();
        $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($adminRole) {
            $stmt = $db->prepare("
                INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
                VALUES (?, ?, NULL, NOW())
            ");
            $stmt->execute([$adminId, $adminRole['id']]);
            $stmt->closeCursor();
        }

        echo "  Administrator account created:\n";
        echo "    Username: {$adminUsername}\n";
        echo "    Email: {$adminEmail}\n";
        echo "    Password: {$adminPassword}\n";
        echo "  ⚠️  CHANGE THIS PASSWORD IN PRODUCTION!\n";
    }

    // ============================================================
    // Create Test Users
    // ============================================================
    echo "\nCreating test users...\n";

    $testUsers = [
        ['john_doe', 'john.doe@example.com', 'Test User'],
        ['jane_smith', 'jane.smith@example.com', 'Jane Smith'],
        ['bob_wilson', 'bob.wilson@example.com', 'Bob Wilson'],
    ];

    foreach ($testUsers as $index => $user) {
        [$username, $email, $displayName] = $user;

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo "  User '{$username}' already exists.\n";
            $stmt->closeCursor();
            continue;
        }
        $stmt->closeCursor();

        $uuid = generateUUID();
        $passwordHash = hashPassword('password123');

        $stmt = $db->prepare("
            INSERT INTO users (uuid, username, email, password, is_verified, is_active, created_at, last_seen)
            VALUES (?, ?, ?, ?, 1, 1, NOW(), NOW())
        ");
        $stmt->execute([$uuid, $username, $email, $passwordHash]);
        $userId = $db->lastInsertId();
        $stmt->closeCursor();

        // Assign Community Member role
        $stmt = $db->prepare("SELECT id FROM roles WHERE slug = 'community-member'");
        $stmt->execute();
        $memberRole = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($memberRole) {
            $stmt = $db->prepare("
                INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at)
                VALUES (?, ?, NULL, NOW())
            ");
            $stmt->execute([$userId, $memberRole['id']]);
            $stmt->closeCursor();
        }

        echo "  Created: {$username}\n";
    }

    // ============================================================
    // Create Department Medals
    // ============================================================
    echo "\nCreating department medals...\n";

    // LSPD Medals
    $lspdMedals = [
        ['Medal of Valor', 'medal_valor', 'Awarded for acts of extraordinary courage in the line of duty.', '#FFD700'],
        ['Purple Heart', 'medal_purple_heart', 'Awarded to officers wounded or killed in the line of duty.', '#8B5CF6'],
        ['Distinguished Service Medal', 'medal_distinguished', 'Awarded for exceptional service to the department.', '#3B82F6'],
        ['Meritorious Service Medal', 'medal_meritorious', 'Awarded for meritorious service above and beyond.', '#22C55E'],
    ];

    // LSSD Medals
    $lssdMedals = [
        ['Sheriff\'s Medal', 'sheriff_medal', 'The highest honor in the Sheriff\'s Department.', '#92400E'],
        ['Outstanding Deputy Medal', 'outstanding_deputy', 'Awarded for outstanding performance.', '#D97706'],
    ];

    // LSFD Medals
    $lsfdMedals = [
        ['Fire Service Medal', 'fire_service_medal', 'Awarded for bravery in firefighting operations.', '#DC2626'],
        ['Rescue Medal', 'rescue_medal', 'Awarded for life-saving rescue operations.', '#EF4444'],
        ['EMS Excellence Medal', 'ems_excellence', 'Awarded for excellence in emergency medical services.', '#F87171'],
    ];

    // LSN Medals
    $lsnMedals = [
        ['Journalist Excellence Award', 'journalist_excellence', 'Awarded for outstanding journalism.', '#EA580C'],
        ['Community Media Award', 'community_media', 'Awarded for community-focused reporting.', '#FB923C'],
    ];

    $allMedals = array_merge($lspdMedals, $lssdMedals, $lsfdMedals, $lsnMedals);

    $stmt = $db->prepare("SELECT id FROM permissions WHERE `group` = 'medal' LIMIT 1");
    $stmt->execute();
    $existingMedal = $stmt->fetch();
    $stmt->closeCursor();

    if ($existingMedal) {
        echo "  Medals already exist.\n";
    } else {
        foreach ($allMedals as $medal) {
            [$name, $key, $description, $color] = $medal;
            $uuid = generateUUID();

            $stmt = $db->prepare("
                INSERT INTO permissions (uuid, name, `key`, description, `group`, sort_order)
                VALUES (?, ?, ?, ?, 'medal', 0)
            ");
            $stmt->execute([$uuid, $name, $key, $description]);
            $stmt->closeCursor();
        }

        echo "  Created " . count($allMedals) . " department medals.\n";
    }

    // ============================================================
    // Create Sample Activity Logs
    // ============================================================
    echo "\nCreating sample activity logs...\n";

    $stmt = $db->prepare("SELECT id FROM activity_logs LIMIT 1");
    $stmt->execute();
    $existingLog = $stmt->fetch();
    $stmt->closeCursor();

    if (!$existingLog) {
        $stmt = $db->prepare("SELECT id FROM users LIMIT 5");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $actions = ['user_register', 'user_login', 'profile_update', 'avatar_update'];

        foreach ($users as $user) {
            foreach (array_slice($actions, 0, rand(1, 3)) as $action) {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, details, created_at)
                    VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))
                ");
                $stmt->execute([$user['id'], $action, null, rand(0, 30)]);
                $stmt->closeCursor();
            }
        }

        echo "  Created sample activity logs.\n";
    } else {
        echo "  Activity logs already exist.\n";
    }

    // ============================================================
    // Clean expired tokens
    // ============================================================
    echo "\nCleaning expired tokens...\n";
    cleanExpiredTokens();
    echo "  Done.\n";

    echo "\n==============================================\n";
    echo "Seed completed successfully!\n";
    echo "==============================================\n";
    echo "\nLogin credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
    echo "\n⚠️  CHANGE THESE CREDENTIALS IN PRODUCTION!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
