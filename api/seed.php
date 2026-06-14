<?php
/**
 * Seed Script — Run ONCE in browser:
 * http://localhost/LSPD/api/seed.php
 *
 * Creates all initial data: departments, roles, permissions, forums, categories, medals, reactions, settings, and default users.
 */
require_once __DIR__ . '/config.php';

$db = getDB();

// Check if already seeded (departments exist)
$check = $db->query("SELECT COUNT(*) as cnt FROM departments")->fetch_assoc();
if ($check['cnt'] > 0) {
    $clean = isset($_GET['clean']);
    if (!$clean) {
        die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Already Seeded</title></head><body style="background:#0a0f1a;color:#d6e4f0;font-family:Segoe UI,sans-serif;padding:40px;text-align:center;"><div style="background:#1e2d45;border:1px solid #2a3a56;border-radius:8px;padding:24px;max-width:500px;margin:0 auto;"><h1 style="color:#f59e0b;">Already Seeded</h1><p>Database already contains data. Add <code>?clean=1</code> to URL to reset and reseed.</p><a href="../index.php" style="color:#8ab4f8;">Go to Forum →</a></div></body></html>');
    }
    // Clean existing data
    $db->query("SET FOREIGN_KEY_CHECKS = 0");
    foreach (['user_medals','thread_tag_map','thread_tags','post_reactions','thread_reactions','bookmarks','thread_watch','warnings','moderator_notes','activity_logs','login_logs','notifications','private_messages','posts','threads','user_roles','users','settings','reactions','medals','categories','forums','permissions','role_permissions','roles','departments'] as $table) {
        $db->query("TRUNCATE TABLE `$table`");
    }
    $db->query("SET FOREIGN_KEY_CHECKS = 1");
}

$messages = [];

// ============================================================
// 1. Departments
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `departments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(10) NOT NULL, `name` VARCHAR(100) NOT NULL,
        `description` TEXT DEFAULT NULL, `color` VARCHAR(7) DEFAULT '#1E40AF',
        `icon` VARCHAR(50) DEFAULT NULL, `sort_order` INT UNSIGNED DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`),
        KEY `is_active` (`is_active`), KEY `sort_order` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$departments = [
    ['LSPD', 'Los Santos Police Department', '#1E40AF', '🛡️', 1],
    ['LSSD', 'Los Santos Sheriff Department', '#065F46', '⭐', 2],
    ['LSFD', 'Los Santos Fire Department', '#991B1B', '🚒', 3],
    ['LSN',  'Los Santos News Network',      '#7C3AED', '📰', 4],
    ['COMM', 'Community',                     '#374151', '🌐', 5],
];

$deptMap = [];
foreach ($departments as $d) {
    [$code, $name, $color, $icon, $order] = $d;
    $stmt = $db->prepare("INSERT IGNORE INTO departments (code, name, color, icon, sort_order) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssssi', $code, $name, $color, $icon, $order);
    $stmt->execute();
    $stmt->close();
    $r = $db->query("SELECT id FROM departments WHERE code='$code'")->fetch_assoc();
    $deptMap[$code] = $r['id'];
}

// ============================================================
// 2. Roles
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `roles` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL, `department_id` INT UNSIGNED DEFAULT NULL,
        `type` ENUM('global','department') DEFAULT 'global',
        `is_staff` TINYINT(1) DEFAULT 0, `color` VARCHAR(7) DEFAULT '#6B7280',
        `badge` VARCHAR(50) DEFAULT NULL, `sort_order` INT UNSIGNED DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `department_id` (`department_id`),
        KEY `is_staff` (`is_staff`), KEY `sort_order` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$roles = [
    // Global
    ['Community Manager', null, 'global', 1, '#F59E0B', 'CM', 1],
    ['Administrator',     null, 'global', 1, '#EF4444', 'ADMIN', 2],
    ['Moderator',         null, 'global', 1, '#3B82F6', 'MOD', 3],
    ['Community Member',  null, 'global', 0, '#6B7280', 'MEMBER', 10],
    // LSPD
    ['LSPD Chief',        $deptMap['LSPD'], 'department', 1, '#1E40AF', 'CHIEF', 10],
    ['LSPD Assistant Chief', $deptMap['LSPD'], 'department', 1, '#1D4ED8', 'AC', 20],
    ['LSPD Commander',    $deptMap['LSPD'], 'department', 1, '#2563EB', 'CMDR', 30],
    ['LSPD Captain',      $deptMap['LSPD'], 'department', 1, '#3B82F6', 'CPT', 40],
    ['LSPD Lieutenant',   $deptMap['LSPD'], 'department', 1, '#60A5FA', 'LT', 50],
    ['LSPD Sergeant',     $deptMap['LSPD'], 'department', 1, '#93C5FD', 'SGT', 60],
    ['LSPD Officer',      $deptMap['LSPD'], 'department', 0, '#BFDBFE', 'PO', 70],
    ['LSPD Cadet',       $deptMap['LSPD'], 'department', 0, '#DBEAFE', 'CDT', 80],
    // LSSD
    ['LSSD Sheriff',      $deptMap['LSSD'], 'department', 1, '#065F46', 'SHERIFF', 10],
    ['LSSD Undersheriff', $deptMap['LSSD'], 'department', 1, '#047857', 'US', 20],
    ['LSSD Captain',     $deptMap['LSSD'], 'department', 1, '#059669', 'CPT', 30],
    ['LSSD Deputy',      $deptMap['LSSD'], 'department', 0, '#10B981', 'DEP', 40],
    // LSFD
    ['LSFD Chief',        $deptMap['LSFD'], 'department', 1, '#991B1B', 'CHIEF', 10],
    ['LSFD Captain',      $deptMap['LSFD'], 'department', 1, '#B91C1C', 'CPT', 20],
    ['LSFD Firefighter',  $deptMap['LSFD'], 'department', 0, '#DC2626', 'FF', 30],
    // LSN
    ['LSN Director',     $deptMap['LSN'], 'department', 1, '#7C3AED', 'DIR', 10],
    ['LSN Reporter',     $deptMap['LSN'], 'department', 0, '#8B5CF6', 'REP', 20],
];

$roleMap = [];
foreach ($roles as $r) {
    [$name, $deptId, $type, $isStaff, $color, $badge, $order] = $r;
    $stmt = $db->prepare("INSERT IGNORE INTO roles (name, department_id, type, is_staff, color, badge, sort_order) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('sisissi', $name, $deptId, $type, $isStaff, $color, $badge, $order);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $roleMap[$name] = $row['id'] ?? null;
}

// ============================================================
// 3. Permissions
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `permissions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL, `key` VARCHAR(100) NOT NULL,
        `group` VARCHAR(50) DEFAULT 'general',
        PRIMARY KEY (`id`), UNIQUE KEY `key` (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$permissions = [
    ['Access Admin Panel', 'access_admin', 'admin'],
    ['Manage Users', 'manage_users', 'admin'],
    ['Manage Forums', 'manage_forums', 'admin'],
    ['Manage Settings', 'manage_settings', 'admin'],
    ['View Logs', 'view_logs', 'admin'],
    ['Grant Medals', 'grant_medals', 'admin'],
    ['Moderate Posts', 'moderate_posts', 'moderation'],
    ['Moderate Threads', 'moderate_threads', 'moderation'],
    ['Warn Users', 'warn_users', 'moderation'],
    ['Suspend Users', 'suspend_users', 'moderation'],
    ['Ban Users', 'ban_users', 'moderation'],
    ['Move Threads', 'move_threads', 'moderation'],
    ['Lock Threads', 'lock_threads', 'moderation'],
    ['Delete Posts', 'delete_posts', 'moderation'],
    ['Delete Threads', 'delete_threads', 'moderation'],
    ['Pin Threads', 'pin_threads', 'moderation'],
    ['Create Threads', 'create_threads', 'forum'],
    ['Reply Threads', 'reply_threads', 'forum'],
    ['Edit Own Post', 'edit_own_post', 'forum'],
    ['Delete Own Post', 'delete_own_post', 'forum'],
    ['Upload Attachments', 'upload_attachments', 'forum'],
    ['Use BBCode', 'use_bbcode', 'forum'],
    ['Send PM', 'send_pm', 'pm'],
    ['View Profile', 'view_profile', 'profile'],
    ['Edit Profile', 'edit_profile', 'profile'],
];

$permMap = [];
foreach ($permissions as $p) {
    [$name, $key, $group] = $p;
    $stmt = $db->prepare("INSERT IGNORE INTO permissions (name, `key`, `group`) VALUES (?,?,?)");
    $stmt->bind_param('sss', $name, $key, $group);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare("SELECT id FROM permissions WHERE `key` = ? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $permMap[$key] = $row['id'] ?? null;
}

// ============================================================
// 4. Role Permissions
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `role_permissions` (
        `role_id` INT UNSIGNED NOT NULL, `permission_id` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`role_id`, `permission_id`),
        KEY `permission_id` (`permission_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$rolePerms = [
    'Community Manager' => ['access_admin','manage_users','manage_forums','manage_settings','view_logs','grant_medals','moderate_posts','moderate_threads','warn_users','suspend_users','ban_users','move_threads','lock_threads','delete_posts','delete_threads','pin_threads','create_threads','reply_threads','edit_own_post','delete_own_post','upload_attachments','use_bbcode','send_pm','view_profile','edit_profile'],
    'Administrator'     => ['access_admin','manage_users','manage_forums','manage_settings','view_logs','grant_medals','moderate_posts','moderate_threads','warn_users','suspend_users','ban_users','move_threads','lock_threads','delete_posts','delete_threads','pin_threads','create_threads','reply_threads','edit_own_post','delete_own_post','upload_attachments','use_bbcode','send_pm','view_profile','edit_profile'],
    'Moderator'         => ['moderate_posts','moderate_threads','warn_users','suspend_users','move_threads','lock_threads','delete_posts','delete_threads','pin_threads','create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile'],
    'Community Member' => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSPD Chief'        => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSPD Assistant Chief' => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSPD Commander'    => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSPD Captain'      => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSPD Lieutenant'   => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSPD Sergeant'     => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSPD Officer'      => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSPD Cadet'        => ['create_threads','reply_threads','edit_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSSD Sheriff'      => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSSD Undersheriff' => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSSD Captain'      => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSSD Deputy'       => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSFD Chief'        => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSFD Captain'      => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSFD Firefighter'  => ['create_threads','reply_threads','edit_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSN Director'      => ['create_threads','reply_threads','edit_own_post','delete_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
    'LSN Reporter'      => ['create_threads','reply_threads','edit_own_post','use_bbcode','send_pm','view_profile','edit_profile'],
];

foreach ($rolePerms as $roleName => $perms) {
    if (!isset($roleMap[$roleName])) continue;
    $roleId = $roleMap[$roleName];
    foreach ($perms as $permKey) {
        if (!isset($permMap[$permKey])) continue;
        $permId = $permMap[$permKey];
        $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)");
        $stmt->bind_param('ii', $roleId, $permId);
        $stmt->execute();
        $stmt->close();
    }
}

// ============================================================
// 5. Forums
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `forums` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `department_id` INT UNSIGNED DEFAULT NULL,
        `name` VARCHAR(200) NOT NULL, `description` TEXT DEFAULT NULL,
        `icon` VARCHAR(50) DEFAULT NULL, `color` VARCHAR(7) DEFAULT '#1E40AF',
        `sort_order` INT UNSIGNED DEFAULT 0, `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `department_id` (`department_id`),
        KEY `is_active` (`is_active`), KEY `sort_order` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$forums = [
    [$deptMap['LSPD'], 'Los Santos Police Department', 'Official department forum for LSPD personnel', '🛡️', '#1E40AF', 1],
    [$deptMap['LSSD'], 'Los Santos Sheriff Department', 'Official department forum for LSSD personnel', '⭐', '#065F46', 2],
    [$deptMap['LSFD'], 'Los Santos Fire Department', 'Official department forum for LSFD personnel', '🚒', '#991B1B', 3],
    [$deptMap['LSN'],  'Los Santos News Network', 'Press and media relations', '📰', '#7C3AED', 4],
    [$deptMap['COMM'], 'Community', 'General community discussions', '🌐', '#374151', 5],
    [null, 'Archive', 'Archived topics and old discussions', '📁', '#6B7280', 99],
];

$forumMap = [];
foreach ($forums as $f) {
    [$deptId, $name, $desc, $icon, $color, $order] = $f;
    $stmt = $db->prepare("INSERT IGNORE INTO forums (department_id, name, description, icon, color, sort_order) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('issssi', $deptId, $name, $desc, $icon, $color, $order);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare("SELECT id FROM forums WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $forumMap[$name] = $r['id'] ?? null;
}

// ============================================================
// 6. Categories
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `forum_id` INT UNSIGNED NOT NULL, `name` VARCHAR(200) NOT NULL,
        `description` TEXT DEFAULT NULL, `icon` VARCHAR(50) DEFAULT NULL,
        `color` VARCHAR(7) DEFAULT '#374151', `sort_order` INT UNSIGNED DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `forum_id` (`forum_id`),
        KEY `is_active` (`is_active`), KEY `sort_order` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$categories = [
    // LSPD
    ['Information Desk', 'General information and announcements', '📌', '#F59E0B', 1, $forumMap['Los Santos Police Department']],
    ['Department Announcements', 'Official LSPD announcements', '📢', '#EF4444', 2, $forumMap['Los Santos Police Department']],
    ['Standard Operating Procedures', 'SOPs and regulations', '📖', '#3B82F6', 3, $forumMap['Los Santos Police Department']],
    ['Penal Code', 'San Andreas Penal Code discussions', '⚖️', '#1E40AF', 4, $forumMap['Los Santos Police Department']],
    ['Recruitment Division', 'Recruitment information and requirements', '📋', '#10B981', 5, $forumMap['Los Santos Police Department']],
    ['Cadet Applications', 'Cadet application submissions', '🎓', '#06B6D4', 6, $forumMap['Los Santos Police Department']],
    ['Internal Affairs', 'Internal affairs and disciplinary matters', '🔍', '#7C3AED', 7, $forumMap['Los Santos Police Department']],
    ['Detective Bureau', 'Detective unit discussions', '🔎', '#4F46E5', 8, $forumMap['Los Santos Police Department']],
    ['Traffic Bureau', 'Traffic regulations and operations', '🚦', '#F97316', 9, $forumMap['Los Santos Police Department']],
    ['SWAT Division', 'SWAT unit discussions', '🛡️', '#DC2626', 10, $forumMap['Los Santos Police Department']],
    ['Community Relations', 'Community outreach and relations', '🤝', '#14B8A6', 11, $forumMap['Los Santos Police Department']],
    // LSSD
    ["Sheriff's Announcements", "Official LSSD announcements", '📢', '#10B981', 1, $forumMap['Los Santos Sheriff Department']],
    ['Patrol Operations', 'Patrol and field operations', '🚓', '#059669', 2, $forumMap['Los Santos Sheriff Department']],
    ['Recruitment', 'LSSD recruitment information', '📋', '#047857', 3, $forumMap['Los Santos Sheriff Department']],
    // LSFD
    ['Fire Department News', 'LSFD news and updates', '📢', '#EF4444', 1, $forumMap['Los Santos Fire Department']],
    ['Recruitment', 'LSFD recruitment information', '📋', '#DC2626', 2, $forumMap['Los Santos Fire Department']],
    ['Training', 'Training programs and exercises', '🎓', '#B91C1C', 3, $forumMap['Los Santos Fire Department']],
    // LSN
    ['Press Releases', 'Official press releases', '📄', '#8B5CF6', 1, $forumMap['Los Santos News Network']],
    ['News Reports', 'News reports and articles', '📰', '#7C3AED', 2, $forumMap['Los Santos News Network']],
    // Community
    ['General Discussion', 'General topics and discussions', '💬', '#6B7280', 1, $forumMap['Community']],
    ['Suggestions', 'Suggestions for the community', '💡', '#F59E0B', 2, $forumMap['Community']],
    ['Questions', 'Ask the community', '❓', '#3B82F6', 3, $forumMap['Community']],
    ['Off Topic', 'Off topic discussions', '🎮', '#8B5CF6', 4, $forumMap['Community']],
    // Archive
    ['Archived Topics', 'Old and archived discussions', '📁', '#4B5563', 1, $forumMap['Archive']],
];

$catMap = [];
foreach ($categories as $c) {
    [$name, $desc, $icon, $color, $order, $forumId] = $c;
    $stmt = $db->prepare("INSERT IGNORE INTO categories (forum_id, name, description, icon, color, sort_order) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('issssi', $forumId, $name, $desc, $icon, $color, $order);
    $stmt->execute();
    $stmt->close();
    $stmt2 = $db->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
    $stmt2->bind_param('s', $name);
    $stmt2->execute();
    $r = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    $catMap[$name] = $r['id'] ?? null;
}

// ============================================================
// 7. Medals
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `medals` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL, `description` TEXT DEFAULT NULL,
        `icon` VARCHAR(100) DEFAULT NULL, `color` VARCHAR(7) DEFAULT '#F59E0B',
        `type` ENUM('valor','service','injury','community','training','other') DEFAULT 'other',
        `sort_order` INT UNSIGNED DEFAULT 0, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$medals = [
    ['Medal of Valor', 'Awarded for acts of exceptional bravery', '🏅', '#F59E0B', 'valor', 1],
    ['Distinguished Service Medal', 'Awarded for outstanding service', '🎖️', '#EF4444', 'service', 2],
    ['Purple Heart', 'Awarded for injuries sustained in the line of duty', '💜', '#7C3AED', 'injury', 3],
    ['Community Service Medal', 'Awarded for exceptional community service', '🌟', '#10B981', 'community', 4],
    ['Training Excellence Medal', 'Awarded for excellence in training', '🎓', '#3B82F6', 'training', 5],
    ['Life Saving Award', 'Awarded for saving lives', '❤️', '#EF4444', 'valor', 6],
    ['Unit Citation', 'Awarded to entire units for collective excellence', '🏆', '#F59E0B', 'service', 7],
    ['Meritorious Service Medal', 'Awarded for meritorious service above and beyond', '⭐', '#06B6D4', 'service', 8],
];

foreach ($medals as $m) {
    [$name, $desc, $icon, $color, $type, $order] = $m;
    $stmt = $db->prepare("INSERT IGNORE INTO medals (name, description, icon, color, type, sort_order) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('sssssi', $name, $desc, $icon, $color, $type, $order);
    $stmt->execute();
    $stmt->close();
}

// ============================================================
// 8. Reactions
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `reactions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(50) NOT NULL, `icon` VARCHAR(100) NOT NULL,
        `color` VARCHAR(7) DEFAULT '#6B7280',
        `type` ENUM('post','thread') DEFAULT 'post',
        PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$reactions = [
    ['Like', '👍', '#22C55E', 'post'],
    ['Love', '❤️', '#EF4444', 'post'],
    ['Haha', '😂', '#F59E0B', 'post'],
    ['Wow', '😮', '#3B82F6', 'post'],
    ['Sad', '😢', '#6366F1', 'post'],
    ['Angry', '😡', '#DC2626', 'post'],
    ['Insightful', '💡', '#F59E0B', 'post'],
    ['Agree', '✅', '#10B981', 'post'],
    ['Disagree', '❌', '#EF4444', 'post'],
    ['Bookmark', '🔖', '#8B5CF6', 'thread'],
];

foreach ($reactions as $r) {
    [$name, $icon, $color, $type] = $r;
    $stmt = $db->prepare("INSERT IGNORE INTO reactions (name, icon, color, type) VALUES (?,?,?,?)");
    $stmt->bind_param('ssss', $name, $icon, $color, $type);
    $stmt->execute();
    $stmt->close();
}

// ============================================================
// 9. Settings
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `key` VARCHAR(100) NOT NULL, `value` TEXT DEFAULT NULL,
        `type` VARCHAR(20) DEFAULT 'string', `autoload` TINYINT(1) DEFAULT 0,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `key` (`key`), KEY `autoload` (`autoload`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$settings = [
    ['site_name', 'Los Santos Police Department', 'string', 1],
    ['site_tagline', 'To Protect and Serve', 'string', 1],
    ['site_logo', '', 'string', 1],
    ['site_favicon', '', 'string', 1],
    ['maintenance_mode', '0', 'bool', 1],
    ['registration_enabled', '1', 'bool', 1],
    ['email_verification_required', '1', 'bool', 1],
    ['posts_per_page', '15', 'int', 1],
    ['threads_per_page', '20', 'int', 1],
    ['hot_thread_threshold', '5', 'int', 1],
    ['max_avatar_size', '2097152', 'int', 1],
    ['max_upload_size', '10485760', 'int', 1],
    ['allowed_avatar_types', 'image/jpeg,image/png,image/gif,image/webp', 'string', 1],
    ['cookie_domain', '', 'string', 1],
    ['cookie_secure', '0', 'bool', 1],
    ['cookie_httponly', '1', 'bool', 1],
    ['session_lifetime', '7200', 'int', 1],
    ['remember_lifetime', '604800', 'int', 1],
    ['rate_limit_login', '5', 'int', 1],
    ['rate_limit_window', '300', 'int', 1],
    ['default_role', 'Community Member', 'string', 1],
    ['default_department', 'COMM', 'string', 1],
];

foreach ($settings as $s) {
    [$key, $val, $type, $autoload] = $s;
    $stmt = $db->prepare("INSERT IGNORE INTO settings (`key`, `value`, `type`, `autoload`) VALUES (?,?,?,?)");
    $stmt->bind_param('sssi', $key, $val, $type, $autoload);
    $stmt->execute();
    $stmt->close();
}

// ============================================================
// 10. Create Required Tables (Users, Threads, Posts, etc.)
// ============================================================

// Users
$db->query("
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `username` VARCHAR(50) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `avatar` VARCHAR(500) DEFAULT NULL,
        `cover_photo` VARCHAR(500) DEFAULT NULL,
        `biography` TEXT DEFAULT NULL,
        `signature` TEXT DEFAULT NULL,
        `department_id` INT UNSIGNED DEFAULT NULL,
        `rank_id` INT UNSIGNED DEFAULT NULL,
        `join_date` DATETIME DEFAULT NULL,
        `last_seen` DATETIME DEFAULT NULL,
        `total_threads` INT UNSIGNED DEFAULT 0,
        `total_posts` INT UNSIGNED DEFAULT 0,
        `is_verified` TINYINT(1) DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `is_suspended` TINYINT(1) DEFAULT 0,
        `suspended_until` DATETIME DEFAULT NULL,
        `suspended_reason` TEXT DEFAULT NULL,
        `is_banned` TINYINT(1) DEFAULT 0,
        `banned_reason` TEXT DEFAULT NULL,
        `banned_at` DATETIME DEFAULT NULL,
        `verify_token` CHAR(64) DEFAULT NULL,
        `reset_token` CHAR(64) DEFAULT NULL,
        `reset_expires` DATETIME DEFAULT NULL,
        `remember_token` CHAR(64) DEFAULT NULL,
        `login_count` INT UNSIGNED DEFAULT 0,
        `failed_logins` INT UNSIGNED DEFAULT 0,
        `locked_until` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `uuid` (`uuid`),
        UNIQUE KEY `username` (`username`), UNIQUE KEY `email` (`email`),
        KEY `department_id` (`department_id`), KEY `rank_id` (`rank_id`),
        KEY `is_active` (`is_active`), KEY `is_banned` (`is_banned`),
        KEY `last_seen` (`last_seen`), KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Threads
$db->query("
    CREATE TABLE IF NOT EXISTS `threads` (
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
        `tags` VARCHAR(500) DEFAULT NULL,
        `last_reply_at` DATETIME DEFAULT NULL,
        `last_reply_by` INT UNSIGNED DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `uuid` (`uuid`),
        KEY `category_id` (`category_id`), KEY `user_id` (`user_id`),
        KEY `is_pinned` (`is_pinned`), KEY `is_sticky` (`is_sticky`),
        KEY `is_archived` (`is_archived`), KEY `is_deleted` (`is_deleted`),
        KEY `last_reply_at` (`last_reply_at`), KEY `created_at` (`created_at`),
        KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Posts
$db->query("
    CREATE TABLE IF NOT EXISTS `posts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `thread_id` INT UNSIGNED NOT NULL,
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
        PRIMARY KEY (`id`), UNIQUE KEY `uuid` (`uuid`),
        KEY `thread_id` (`thread_id`), KEY `user_id` (`user_id`),
        KEY `is_deleted` (`is_deleted`), KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Notifications
$db->query("
    CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `type` ENUM('mention','quote','reply','pm','staff','reaction','system') NOT NULL,
        `from_user_id` INT UNSIGNED DEFAULT NULL,
        `reference_type` ENUM('thread','post','pm','user') DEFAULT NULL,
        `reference_id` INT UNSIGNED DEFAULT NULL,
        `thread_id` INT UNSIGNED DEFAULT NULL,
        `post_id` INT UNSIGNED DEFAULT NULL,
        `message` VARCHAR(500) DEFAULT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `read_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `user_id` (`user_id`),
        KEY `is_read` (`is_read`), KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Private Messages
$db->query("
    CREATE TABLE IF NOT EXISTS `private_messages` (
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
        PRIMARY KEY (`id`), UNIQUE KEY `uuid` (`uuid`),
        KEY `sender_id` (`sender_id`), KEY `recipient_id` (`recipient_id`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Reactions
$db->query("
    CREATE TABLE IF NOT EXISTS `post_reactions` (
        `post_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `reaction_id` INT UNSIGNED NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`post_id`, `user_id`),
        KEY `user_id` (`user_id`), KEY `reaction_id` (`reaction_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->query("
    CREATE TABLE IF NOT EXISTS `thread_reactions` (
        `thread_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `reaction_id` INT UNSIGNED NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`thread_id`, `user_id`),
        KEY `user_id` (`user_id`), KEY `reaction_id` (`reaction_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Bookmarks
$db->query("
    CREATE TABLE IF NOT EXISTS `bookmarks` (
        `user_id` INT UNSIGNED NOT NULL,
        `thread_id` INT UNSIGNED NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`, `thread_id`),
        KEY `thread_id` (`thread_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Thread Watch
$db->query("
    CREATE TABLE IF NOT EXISTS `thread_watch` (
        `user_id` INT UNSIGNED NOT NULL,
        `thread_id` INT UNSIGNED NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`, `thread_id`),
        KEY `thread_id` (`thread_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// User Medals
$db->query("
    CREATE TABLE IF NOT EXISTS `user_medals` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `medal_id` INT UNSIGNED NOT NULL,
        `granted_by` INT UNSIGNED DEFAULT NULL,
        `reason` TEXT DEFAULT NULL,
        `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `user_id` (`user_id`),
        KEY `medal_id` (`medal_id`), KEY `granted_by` (`granted_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Warnings
$db->query("
    CREATE TABLE IF NOT EXISTS `warnings` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `warned_by` INT UNSIGNED NOT NULL,
        `reason` TEXT NOT NULL,
        `notes` TEXT DEFAULT NULL,
        `expires_at` DATETIME DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `user_id` (`user_id`),
        KEY `warned_by` (`warned_by`), KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Moderator Notes
$db->query("
    CREATE TABLE IF NOT EXISTS `moderator_notes` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `staff_id` INT UNSIGNED NOT NULL,
        `note` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `user_id` (`user_id`),
        KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Activity Logs
$db->query("
    CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED DEFAULT NULL,
        `action` VARCHAR(100) NOT NULL,
        `details` TEXT DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` VARCHAR(500) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `user_id` (`user_id`),
        KEY `action` (`action`), KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Login Logs
$db->query("
    CREATE TABLE IF NOT EXISTS `login_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED DEFAULT NULL,
        `username` VARCHAR(50) DEFAULT NULL,
        `status` ENUM('success','failed','locked','banned') NOT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` VARCHAR(500) DEFAULT NULL,
        `reason` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `user_id` (`user_id`),
        KEY `username` (`username`), KEY `status` (`status`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// User Roles
$db->query("
    CREATE TABLE IF NOT EXISTS `user_roles` (
        `user_id` INT UNSIGNED NOT NULL,
        `role_id` INT UNSIGNED NOT NULL,
        `assigned_by` INT UNSIGNED DEFAULT NULL,
        `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`, `role_id`),
        KEY `role_id` (`role_id`), KEY `assigned_by` (`assigned_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ============================================================
// 11. Create Default Users
// ============================================================
$defaultUsers = [
    ['admin',        'admin@lspd.gov',    'admin123',  'Administrator',     $deptMap['LSPD']],
    ['john_smith',  'john@lspd.gov',     'lspd123',   'LSPD Chief',        $deptMap['LSPD']],
    ['jane_doe',    'jane@lspd.gov',     'lspd123',   'LSPD Captain',      $deptMap['LSPD']],
    ['sheriff_01',  'sheriff@lssd.gov',  'lssd123',   'LSSD Sheriff',      $deptMap['LSSD']],
    ['fire_chief',  'chief@lsfd.gov',    'lsfd123',   'LSFD Chief',       $deptMap['LSFD']],
    ['news_director','director@lsn.gov', 'lsn123',    'LSN Director',     $deptMap['LSN']],
    ['citizen_01',  'citizen@email.com', 'user123',   'Community Member',  $deptMap['COMM']],
];

$userIds = [];
foreach ($defaultUsers as $u) {
    [$username, $email, $password, $rankName, $deptId] = $u;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $uuid = generateUUID();
    $rankId = $roleMap[$rankName] ?? null;

    $stmt = $db->prepare("INSERT IGNORE INTO users (uuid, username, email, password, department_id, rank_id, join_date, last_seen, is_verified) VALUES (?,?,?,?,?,?,NOW(),NOW(),1)");
    $stmt->bind_param('ssssii', $uuid, $username, $email, $hash, $deptId, $rankId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($userRow) {
        $userId = $userRow['id'];
        $userIds[$username] = $userId;

        // Assign role
        if (isset($roleMap[$rankName])) {
            $roleId = $roleMap[$rankName];
            $stmt2 = $db->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?,?)");
            $stmt2->bind_param('ii', $userId, $roleId);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

// ============================================================
// 12. Create Sample Threads & Posts
// ============================================================
$db->query("
    CREATE TABLE IF NOT EXISTS `thread_tags` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(50) NOT NULL, `color` VARCHAR(7) DEFAULT '#6B7280',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$tags = [
    ['Announcement', '#EF4444'],
    ['Important', '#F59E0B'],
    ['Training', '#3B82F6'],
    ['Recruitment', '#10B981'],
    ['SOP', '#8B5CF6'],
    ['Discussion', '#6B7280'],
    ['Question', '#06B6D4'],
    ['Resolved', '#22C55E'],
];

foreach ($tags as $t) {
    [$name, $color] = $t;
    $stmt = $db->prepare("INSERT IGNORE INTO thread_tags (name, color) VALUES (?,?)");
    $stmt->bind_param('ss', $name, $color);
    $stmt->execute();
    $stmt->close();
}

$db->query("
    CREATE TABLE IF NOT EXISTS `thread_tag_map` (
        `thread_id` INT UNSIGNED NOT NULL, `tag_id` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`thread_id`, `tag_id`), KEY `tag_id` (`tag_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Sample threads
if (isset($userIds['admin']) && isset($catMap['Information Desk'])) {
    $adminId = $userIds['admin'];
    $catId = $catMap['Information Desk'];

    $sampleThreads = [
        [
            'Welcome to LSPD Forum 2026',
            'Welcome to the official Los Santos Police Department Forum. This forum serves as the central hub for all LSPD personnel, community members, and stakeholders. Please read the rules and introduce yourself in the appropriate section.',
            'Announcement',
            'Welcome to the official Los Santos Police Department Forum for the 2026 roleplay season. This forum serves as the central hub for all LSPD personnel, community members, and stakeholders.\n\n[b]Forum Rules:[/b]\n1. Respect all members\n2. No out-of-character discussions in character sections\n3. Follow the Penal Code\n4. Use appropriate prefixes\n5. No discrimination or harassment\n\nWe look forward to seeing you around!',
        ],
        [
            'LSPD Recruitment Open — Apply Now',
            'The Los Santos Police Department is now accepting applications for new officers. Join our ranks and serve the community.',
            'Recruitment',
            'The Los Santos Police Department is currently accepting applications for the following positions:\n\n[b]Open Positions:[/b]\n- Police Officer (Multiple)\n- Detective (Limited)\n- Traffic Officer (Limited)\n\n[b]Requirements:[/b]\n- Minimum 18 years of age\n- Clean record\n- Completion of Basic Academy\n- Passing score on final examination\n- Interview with command staff\n\n[b]How to Apply:[/b]\nSubmit your application through the Cadet Applications section. Include your background, motivation letter, and availability.\n\nApplications close on the 15th of each month.',
        ],
    ];

    foreach ($sampleThreads as $idx => $t) {
        [$title, $excerpt, $prefix, $content] = $t;
        $threadUuid = generateUUID();
        $slug = slugify($title);

        $stmt = $db->prepare("INSERT IGNORE INTO threads (uuid, category_id, user_id, title, slug, content, prefix, is_pinned, is_sticky, last_reply_at, last_reply_by) VALUES (?,?,?,?,?,?,?,1,1,NOW(),?)");
        $isPinned = 1; $isSticky = 1;
        $stmt->bind_param('siisssii', $threadUuid, $catId, $adminId, $title, $slug, $content, $prefix, $adminId);
        $stmt->execute();
        $threadId = $stmt->insert_id;
        $stmt->close();

        if ($threadId) {
            $postUuid = generateUUID();
            $stmt2 = $db->prepare("INSERT INTO posts (uuid, thread_id, user_id, content, is_first_post) VALUES (?,?,?,?,1)");
            $stmt2->bind_param('siis', $postUuid, $threadId, $adminId, $content);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

// ============================================================
// Helper Functions
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LSPD Forum — Seed Complete</title>
<style>
  body { background: #0a0f1a; color: #d6e4f0; font-family: 'Segoe UI', sans-serif; padding: 40px; }
  .card { background: #1e2d45; border: 1px solid #2a3a56; border-radius: 8px; padding: 24px; max-width: 600px; margin: 0 auto; }
  h1 { color: #8ab4f8; font-size: 1.5rem; margin-bottom: 20px; }
  ul { list-style: none; padding: 0; }
  li { padding: 8px 0; border-bottom: 1px solid #2a3a56; }
  li:last-child { border: none; }
  strong { color: #f59e0b; }
  .ok { color: #4ade80; }
  a { color: #8ab4f8; }
</style>
</head>
<body>
<div class="card">
  <h1>Seed Complete</h1>
  <p>Database has been initialized with all required data.</p>
  <h3>Default Users:</h3>
  <ul>
    <li><strong>admin</strong> / admin123 — Administrator</li>
    <li><strong>john_smith</strong> / lspd123 — LSPD Chief</li>
    <li><strong>jane_doe</strong> / lspd123 — LSPD Captain</li>
    <li><strong>sheriff_01</strong> / lssd123 — LSSD Sheriff</li>
    <li><strong>fire_chief</strong> / lsfd123 — LSFD Chief</li>
    <li><strong>news_director</strong> / lsn123 — LSN Director</li>
    <li><strong>citizen_01</strong> / user123 — Community Member</li>
  </ul>
  <p><a href="../index.php">Go to Forum &rarr;</a></p>
</div>
</body>
</html>
