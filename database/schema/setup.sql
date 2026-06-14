-- ============================================================
-- Los Santos Roleplay Community Portal
-- Database Schema - Phase 1 Foundation
-- ============================================================
-- Run: mysql -u root -p lspd_portal < database/schema/setup.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Drop existing tables (for fresh install)
-- ============================================================
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `login_logs`;
DROP TABLE IF EXISTS `refresh_tokens`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `user_medals`;
DROP TABLE IF EXISTS `user_departments`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

-- ============================================================
-- Users Table
-- ============================================================
CREATE TABLE `users` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `username`        VARCHAR(50)         NOT NULL,
    `email`           VARCHAR(255)        NOT NULL,
    `password`        VARCHAR(255)         NOT NULL,
    `avatar`          VARCHAR(500)        DEFAULT NULL,
    `cover_photo`     VARCHAR(500)        DEFAULT NULL,
    `biography`       TEXT                DEFAULT NULL,
    `signature`       TEXT                DEFAULT NULL,
    `is_verified`     TINYINT(1)          DEFAULT 1,
    `is_active`      TINYINT(1)          DEFAULT 1,
    `is_suspended`    TINYINT(1)          DEFAULT 0,
    `suspended_until` DATETIME            DEFAULT NULL,
    `suspended_reason` TEXT               DEFAULT NULL,
    `is_banned`       TINYINT(1)          DEFAULT 0,
    `banned_reason`   TEXT                DEFAULT NULL,
    `banned_at`       DATETIME            DEFAULT NULL,
    `banned_by`       INT UNSIGNED        DEFAULT NULL,
    `verify_token`    CHAR(64)            DEFAULT NULL,
    `reset_token`     CHAR(64)            DEFAULT NULL,
    `reset_expires`   DATETIME            DEFAULT NULL,
    `login_count`     INT UNSIGNED        DEFAULT 0,
    `failed_logins`   INT UNSIGNED        DEFAULT 0,
    `locked_until`    DATETIME            DEFAULT NULL,
    `last_seen`       DATETIME            DEFAULT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `is_active` (`is_active`),
    KEY `is_banned` (`is_banned`),
    KEY `last_seen` (`last_seen`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Departments Table
-- ============================================================
CREATE TABLE `departments` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `code`            VARCHAR(10)         NOT NULL,
    `name`            VARCHAR(100)        NOT NULL,
    `slug`            VARCHAR(100)        NOT NULL,
    `description`     TEXT                DEFAULT NULL,
    `color`           VARCHAR(7)          DEFAULT '#2563EB',
    `icon`            VARCHAR(100)        DEFAULT NULL,
    `logo`            VARCHAR(500)        DEFAULT NULL,
    `banner`          VARCHAR(500)        DEFAULT NULL,
    `sort_order`      INT UNSIGNED        DEFAULT 0,
    `is_active`       TINYINT(1)          DEFAULT 1,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `code` (`code`),
    UNIQUE KEY `slug` (`slug`),
    KEY `is_active` (`is_active`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Roles Table
-- ============================================================
CREATE TABLE `roles` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `name`            VARCHAR(100)        NOT NULL,
    `slug`            VARCHAR(100)        NOT NULL,
    `description`     TEXT                DEFAULT NULL,
    `department_id`   INT UNSIGNED        DEFAULT NULL,
    `type`            ENUM('global','department') DEFAULT 'global',
    `is_leader`       TINYINT(1)          DEFAULT 0,
    `is_staff`        TINYINT(1)          DEFAULT 0,
    `is_admin`        TINYINT(1)          DEFAULT 0,
    `color`           VARCHAR(7)          DEFAULT '#94A3B8',
    `badge`           VARCHAR(50)         DEFAULT NULL,
    `icon`            VARCHAR(100)        DEFAULT NULL,
    `hierarchy`       INT UNSIGNED        DEFAULT 0,
    `sort_order`      INT UNSIGNED        DEFAULT 0,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `slug` (`slug`),
    KEY `department_id` (`department_id`),
    KEY `type` (`type`),
    KEY `is_leader` (`is_leader`),
    KEY `is_staff` (`is_staff`),
    KEY `hierarchy` (`hierarchy`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Permissions Table
-- ============================================================
CREATE TABLE `permissions` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `name`            VARCHAR(100)        NOT NULL,
    `key`             VARCHAR(100)        NOT NULL,
    `description`     TEXT                DEFAULT NULL,
    `group`           VARCHAR(50)         DEFAULT 'general',
    `sort_order`      INT UNSIGNED        DEFAULT 0,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `key` (`key`),
    KEY `group` (`group`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Role Permissions Junction Table
-- ============================================================
CREATE TABLE `role_permissions` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `role_id`         INT UNSIGNED        NOT NULL,
    `permission_id`  INT UNSIGNED        NOT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `role_permission` (`role_id`, `permission_id`),
    KEY `role_id` (`role_id`),
    KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- User Roles Junction Table
-- ============================================================
CREATE TABLE `user_roles` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED        NOT NULL,
    `role_id`         INT UNSIGNED        NOT NULL,
    `department_id`   INT UNSIGNED        DEFAULT NULL,
    `assigned_by`     INT UNSIGNED        DEFAULT NULL,
    `assigned_at`     DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `expires_at`      DATETIME            DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_role_dept` (`user_id`, `role_id`, `department_id`),
    KEY `user_id` (`user_id`),
    KEY `role_id` (`role_id`),
    KEY `department_id` (`department_id`),
    KEY `assigned_by` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- User Departments Junction Table
-- ============================================================
CREATE TABLE `user_departments` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED        NOT NULL,
    `department_id`   INT UNSIGNED        NOT NULL,
    `is_primary`      TINYINT(1)          DEFAULT 0,
    `joined_at`       DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_department` (`user_id`, `department_id`),
    KEY `user_id` (`user_id`),
    KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- User Medals Junction Table
-- ============================================================
CREATE TABLE `user_medals` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED        NOT NULL,
    `medal_id`        INT UNSIGNED        NOT NULL,
    `granted_by`      INT UNSIGNED        DEFAULT NULL,
    `reason`          TEXT                DEFAULT NULL,
    `granted_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `medal_id` (`medal_id`),
    KEY `granted_by` (`granted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Refresh Tokens Table
-- ============================================================
CREATE TABLE `refresh_tokens` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED        NOT NULL,
    `token`           CHAR(64)            NOT NULL,
    `expires_at`      DATETIME            NOT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `revoked_at`      DATETIME            DEFAULT NULL,
    `ip_address`      VARCHAR(45)         DEFAULT NULL,
    `user_agent`      VARCHAR(500)         DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `token` (`token`),
    KEY `user_id` (`user_id`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Settings Table
-- ============================================================
CREATE TABLE `settings` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `key`             VARCHAR(100)        NOT NULL,
    `value`           TEXT                DEFAULT NULL,
    `type`            VARCHAR(20)         DEFAULT 'string',
    `group`           VARCHAR(50)         DEFAULT 'general',
    `autoload`        TINYINT(1)          DEFAULT 0,
    `description`     TEXT                DEFAULT NULL,
    `updated_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`),
    KEY `group` (`group`),
    KEY `autoload` (`autoload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Activity Logs Table
-- ============================================================
CREATE TABLE `activity_logs` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED        DEFAULT NULL,
    `action`          VARCHAR(100)        NOT NULL,
    `entity_type`     VARCHAR(50)         DEFAULT NULL,
    `entity_id`       INT UNSIGNED        DEFAULT NULL,
    `details`         TEXT                DEFAULT NULL,
    `ip_address`      VARCHAR(45)         DEFAULT NULL,
    `user_agent`      VARCHAR(500)         DEFAULT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `entity_type` (`entity_type`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Login Logs Table
-- ============================================================
CREATE TABLE `login_logs` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED        DEFAULT NULL,
    `username`        VARCHAR(50)         DEFAULT NULL,
    `email`           VARCHAR(255)         DEFAULT NULL,
    `status`          ENUM('success','failed','locked','banned') NOT NULL,
    `ip_address`      VARCHAR(45)         DEFAULT NULL,
    `user_agent`      VARCHAR(500)         DEFAULT NULL,
    `reason`          VARCHAR(255)        DEFAULT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `username` (`username`),
    KEY `status` (`status`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Foreign Key Constraints
-- ============================================================
ALTER TABLE `users`
    ADD CONSTRAINT `fk_users_banned_by` FOREIGN KEY (`banned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `roles`
    ADD CONSTRAINT `fk_roles_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL;

ALTER TABLE `role_permissions`
    ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE;

ALTER TABLE `user_roles`
    ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ur_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ur_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `user_departments`
    ADD CONSTRAINT `fk_ud_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ud_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE;

ALTER TABLE `user_medals`
    ADD CONSTRAINT `fk_um_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_um_medal` FOREIGN KEY (`medal_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_um_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `refresh_tokens`
    ADD CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Insert Default Departments
-- ============================================================
INSERT INTO `departments` (`uuid`, `code`, `name`, `slug`, `description`, `color`, `icon`, `sort_order`) VALUES
(UUID(), 'PORTAL', 'Los Santos Roleplay Community', 'portal', 'Official community portal connecting all departments: LSPD, LSSD, LSFD, and LSN.', '#9925EB', '🏛️', 0),
(UUID(), 'LSPD', 'Los Santos Police Department', 'lspd', 'The primary law enforcement agency serving the citizens of Los Santos.', '#2563EB', '🚔', 1),
(UUID(), 'LSSD', 'Los Santos Sheriff Department', 'lssd', 'The county law enforcement agency providing patrol and investigative services.', '#92400E', '🤠', 2),
(UUID(), 'LSFD', 'Los Santos Fire Department', 'lsfd', 'The fire and emergency medical services department protecting the community.', '#DC2626', '🚒', 3),
(UUID(), 'LSN', 'Los Santos News', 'lsn', 'The official news and media organization reporting on community events.', '#EA580C', '📺', 4);

-- ============================================================
-- Insert Default Global Roles
-- ============================================================
INSERT INTO `roles` (`uuid`, `name`, `slug`, `description`, `type`, `is_staff`, `is_admin`, `color`, `badge`, `hierarchy`, `sort_order`) VALUES
(UUID(), 'Community Member', 'community-member', 'Default role for all registered members', 'global', 0, 0, '#94A3B8', '👤', 100, 1),
(UUID(), 'Moderator', 'moderator', 'Community moderator with moderation capabilities', 'global', 1, 0, '#22C55E', '🛡️', 50, 2),
(UUID(), 'Administrator', 'administrator', 'Website administrator with full system access', 'global', 1, 1, '#8B5CF6', '⚡', 10, 3);

-- ============================================================
-- Insert Default Department Leader Roles
-- ============================================================
INSERT INTO `roles` (`uuid`, `name`, `slug`, `description`, `department_id`, `type`, `is_leader`, `is_staff`, `color`, `badge`, `hierarchy`, `sort_order`) VALUES
(UUID(), 'Chief of Police', 'chief-of-police', 'Highest ranking officer in LSPD', 2, 'department', 1, 1, '#2563EB', '⭐', 1, 1),
(UUID(), 'Sheriff', 'sheriff', 'Highest ranking officer in LSSD', 3, 'department', 1, 1, '#92400E', '⭐', 1, 1),
(UUID(), 'Fire Chief', 'fire-chief', 'Highest ranking officer in LSFD', 4, 'department', 1, 1, '#DC2626', '⭐', 1, 1),
(UUID(), 'News Director', 'news-director', 'Highest ranking officer in LSN', 5, 'department', 1, 1, '#EA580C', '⭐', 1, 1);

-- ============================================================
-- Insert Default Permissions
-- ============================================================
INSERT INTO `permissions` (`uuid`, `name`, `key`, `description`, `group`, `sort_order`) VALUES
-- General
(UUID(), 'View Forums', 'view_forums', 'Can view forum categories and topics', 'general', 1),
(UUID(), 'Create Topics', 'create_topics', 'Can create new topics', 'general', 2),
(UUID(), 'Reply to Topics', 'reply_topics', 'Can reply to existing topics', 'general', 3),
(UUID(), 'Edit Own Posts', 'edit_own_posts', 'Can edit own posts and topics', 'general', 4),
(UUID(), 'Delete Own Posts', 'delete_own_posts', 'Can delete own posts and topics', 'general', 5),
(UUID(), 'Use BBCode', 'use_bbcode', 'Can use BBCode formatting', 'general', 6),
(UUID(), 'Upload Attachments', 'upload_attachments', 'Can upload file attachments', 'general', 7),
(UUID(), 'Send PM', 'send_pm', 'Can send private messages', 'general', 8),
(UUID(), 'View Profiles', 'view_profiles', 'Can view member profiles', 'general', 9),

-- Moderation
(UUID(), 'Moderate Forum', 'moderate_forum', 'Can moderate forum content', 'moderation', 1),
(UUID(), 'Edit Any Post', 'edit_any_post', 'Can edit any post', 'moderation', 2),
(UUID(), 'Delete Any Post', 'delete_any_post', 'Can delete any post', 'moderation', 3),
(UUID(), 'Lock Topics', 'lock_topics', 'Can lock topics', 'moderation', 4),
(UUID(), 'Pin Topics', 'pin_topics', 'Can pin topics', 'moderation', 5),
(UUID(), 'Move Topics', 'move_topics', 'Can move topics between forums', 'moderation', 6),
(UUID(), 'Warn Users', 'warn_users', 'Can issue warnings to users', 'moderation', 7),
(UUID(), 'Suspend Users', 'suspend_users', 'Can suspend users temporarily', 'moderation', 8),
(UUID(), 'Ban Users', 'ban_users', 'Can ban users permanently', 'moderation', 9),
(UUID(), 'View Moderator Logs', 'view_mod_logs', 'Can view moderation logs', 'moderation', 10),

-- Department Management
(UUID(), 'Manage Department', 'manage_department', 'Can manage department settings', 'department', 1),
(UUID(), 'Create Roles', 'create_roles', 'Can create department roles', 'department', 2),
(UUID(), 'Edit Roles', 'edit_roles', 'Can edit department roles', 'department', 3),
(UUID(), 'Delete Roles', 'delete_roles', 'Can delete department roles', 'department', 4),
(UUID(), 'Assign Roles', 'assign_roles', 'Can assign roles to members', 'department', 5),
(UUID(), 'Remove Roles', 'remove_roles', 'Can remove roles from members', 'department', 6),
(UUID(), 'Manage Recruitment', 'manage_recruitment', 'Can manage recruitment applications', 'department', 7),
(UUID(), 'Create Announcements', 'create_announcements', 'Can create department announcements', 'department', 8),
(UUID(), 'Manage Medals', 'manage_medals', 'Can award medals to members', 'department', 9),

-- Admin
(UUID(), 'Access Admin Panel', 'access_admin', 'Can access administrator panel', 'admin', 1),
(UUID(), 'Manage Users', 'manage_users', 'Can manage all users', 'admin', 2),
(UUID(), 'Manage Departments', 'manage_departments', 'Can manage departments', 'admin', 3),
(UUID(), 'Assign Department Leaders', 'assign_dept_leaders', 'Can assign department leaders', 'admin', 4),
(UUID(), 'Manage Permissions', 'manage_permissions', 'Can manage permission system', 'admin', 5),
(UUID(), 'Manage Settings', 'manage_settings', 'Can manage website settings', 'admin', 6),
(UUID(), 'View Admin Logs', 'view_admin_logs', 'Can view administrative logs', 'admin', 7),
(UUID(), 'Manage Global Forums', 'manage_global_forums', 'Can manage global forum structure', 'admin', 8);

-- ============================================================
-- Assign Permissions to Roles
-- ============================================================
-- Community Member Permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'community-member'
AND p.key IN ('view_forums', 'create_topics', 'reply_topics', 'edit_own_posts', 'delete_own_posts', 'use_bbcode', 'send_pm', 'view_profiles');

-- Moderator Permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'moderator'
AND p.key IN ('view_forums', 'create_topics', 'reply_topics', 'edit_own_posts', 'delete_own_posts', 'use_bbcode', 'upload_attachments', 'send_pm', 'view_profiles', 'moderate_forum', 'edit_any_post', 'delete_any_post', 'lock_topics', 'pin_topics', 'move_topics', 'warn_users', 'view_mod_logs');

-- Administrator Permissions (all)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'administrator';

-- Department Leader Permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.type = 'department' AND r.is_leader = 1
AND p.key IN ('view_forums', 'create_topics', 'reply_topics', 'edit_own_posts', 'delete_own_posts', 'use_bbcode', 'upload_attachments', 'send_pm', 'view_profiles', 'moderate_forum', 'edit_any_post', 'delete_any_post', 'lock_topics', 'pin_topics', 'move_topics', 'warn_users', 'suspend_users', 'view_mod_logs', 'manage_department', 'create_roles', 'edit_roles', 'delete_roles', 'assign_roles', 'remove_roles', 'manage_recruitment', 'create_announcements', 'manage_medals');

-- ============================================================
-- Insert Default Settings
-- ============================================================
INSERT INTO `settings` (`key`, `value`, `type`, `group`, `autoload`, `description`) VALUES
-- Site Info
('site_name', 'Los Santos Roleplay Community', 'string', 'general', 1, 'Website name'),
('site_tagline', 'To Protect and Serve', 'string', 'general', 1, 'Website tagline'),
('site_description', 'Official community portal for Los Santos roleplay community', 'string', 'general', 1, 'Website meta description'),
('site_keywords', 'Los Santos, roleplay, LSPD, LSSD, LSFD, LSN, gaming', 'string', 'general', 1, 'Website meta keywords'),

-- URLs
('site_url', 'http://localhost', 'string', 'general', 1, 'Website base URL'),
('api_url', 'http://localhost/api', 'string', 'general', 1, 'API base URL'),

-- Auth Settings
('jwt_secret', 'CHANGE_THIS_IN_PRODUCTION_USE_STRONG_SECRET_KEY', 'string', 'auth', 1, 'JWT signing secret'),
('jwt_access_expires', '900', 'int', 'auth', 1, 'JWT access token expiry in seconds (15 min default)'),
('jwt_refresh_expires', '604800', 'int', 'auth', 1, 'JWT refresh token expiry in seconds (7 days default)'),
('max_login_attempts', '5', 'int', 'auth', 1, 'Maximum failed login attempts before lockout'),
('lockout_duration', '300', 'int', 'auth', 1, 'Account lockout duration in seconds'),
('min_password_length', '6', 'int', 'auth', 1, 'Minimum password length'),
('max_password_length', '128', 'int', 'auth', 1, 'Maximum password length'),
('min_username_length', '3', 'int', 'auth', 1, 'Minimum username length'),
('max_username_length', '50', 'int', 'auth', 1, 'Maximum username length'),
('allow_registration', '1', 'bool', 'auth', 1, 'Allow new user registration'),
('require_email_verification', '0', 'bool', 'auth', 1, 'Require email verification (disabled by default)'),

-- Forum Settings
('topics_per_page', '20', 'int', 'forum', 1, 'Number of topics per page'),
('posts_per_page', '15', 'int', 'forum', 1, 'Number of posts per page'),
('hot_topic_threshold', '10', 'int', 'forum', 1, 'Replies threshold for hot topic indicator'),
('max_topic_length', '300', 'int', 'forum', 1, 'Maximum topic title length'),
('max_post_length', '50000', 'int', 'forum', 1, 'Maximum post content length'),
('max_attachments', '5', 'int', 'forum', 1, 'Maximum attachments per post'),
('max_attachment_size', '5242880', 'int', 'forum', 1, 'Maximum attachment size in bytes (5MB)'),
('allowed_extensions', 'jpg,jpeg,png,gif,pdf,doc,docx,zip', 'string', 'forum', 1, 'Allowed file extensions'),

-- Pagination
('members_per_page', '24', 'int', 'pagination', 1, 'Members per page in directory'),
('notifications_per_page', '20', 'int', 'pagination', 1, 'Notifications per page'),
('pm_per_page', '20', 'int', 'pagination', 1, 'Private messages per page'),

-- Maintenance
('maintenance_mode', '0', 'bool', 'maintenance', 1, 'Enable maintenance mode'),
('maintenance_message', 'Website is under maintenance. Please try again later.', 'string', 'maintenance', 1, 'Maintenance mode message');

-- ============================================================
-- Create Indexes for Performance
-- ============================================================
-- Composite indexes for common queries
CREATE INDEX idx_users_active_banned ON users(is_active, is_banned);
CREATE INDEX idx_user_roles_user_dept ON user_roles(user_id, department_id);
CREATE INDEX idx_activity_logs_user_action ON activity_logs(user_id, action);
CREATE INDEX idx_login_logs_user_status ON login_logs(user_id, status);
CREATE INDEX idx_login_logs_created ON login_logs(created_at);
