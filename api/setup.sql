-- ============================================================
-- LSPD Roleplay Forum — Complete Database Schema
-- Los Santos Police Department Community Forum
-- ============================================================
-- Run: mysql -u root -p lspd_db < setup.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- Core Tables
-- ============================================================

-- Users
CREATE TABLE IF NOT EXISTS `users` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`              CHAR(36)            NOT NULL,
    `username`          VARCHAR(50)         NOT NULL,
    `email`             VARCHAR(255)        NOT NULL,
    `password`          VARCHAR(255)        NOT NULL,
    `avatar`            VARCHAR(500)        DEFAULT NULL,
    `cover_photo`       VARCHAR(500)        DEFAULT NULL,
    `biography`         TEXT                DEFAULT NULL,
    `signature`         TEXT                DEFAULT NULL,
    `department_id`     INT UNSIGNED        DEFAULT NULL,
    `rank_id`           INT UNSIGNED        DEFAULT NULL,
    `join_date`         DATETIME            DEFAULT NULL,
    `last_seen`         DATETIME            DEFAULT NULL,
    `total_threads`     INT UNSIGNED        DEFAULT 0,
    `total_posts`       INT UNSIGNED        DEFAULT 0,
    `is_verified`       TINYINT(1)          DEFAULT 0,
    `is_active`         TINYINT(1)          DEFAULT 1,
    `is_suspended`      TINYINT(1)          DEFAULT 0,
    `suspended_until`   DATETIME            DEFAULT NULL,
    `suspended_reason`  TEXT                DEFAULT NULL,
    `is_banned`         TINYINT(1)          DEFAULT 0,
    `banned_reason`     TEXT                DEFAULT NULL,
    `banned_at`         DATETIME            DEFAULT NULL,
    `verify_token`      CHAR(64)            DEFAULT NULL,
    `reset_token`       CHAR(64)            DEFAULT NULL,
    `reset_expires`     DATETIME            DEFAULT NULL,
    `remember_token`    CHAR(64)            DEFAULT NULL,
    `login_count`       INT UNSIGNED        DEFAULT 0,
    `failed_logins`     INT UNSIGNED        DEFAULT 0,
    `locked_until`      DATETIME            DEFAULT NULL,
    `created_at`        DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `department_id` (`department_id`),
    KEY `rank_id` (`rank_id`),
    KEY `is_active` (`is_active`),
    KEY `is_banned` (`is_banned`),
    KEY `last_seen` (`last_seen`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments
CREATE TABLE IF NOT EXISTS `departments` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(10)  NOT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT        DEFAULT NULL,
    `color`       VARCHAR(7)   DEFAULT '#1E40AF',
    `icon`        VARCHAR(50) DEFAULT NULL,
    `sort_order`  INT UNSIGNED DEFAULT 0,
    `is_active`   TINYINT(1)  DEFAULT 1,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`),
    KEY `is_active` (`is_active`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(100) NOT NULL,
    `department_id` INT UNSIGNED DEFAULT NULL,
    `type`         ENUM('global','department') DEFAULT 'global',
    `is_staff`     TINYINT(1)   DEFAULT 0,
    `color`        VARCHAR(7)   DEFAULT '#6B7280',
    `badge`        VARCHAR(50)  DEFAULT NULL,
    `sort_order`   INT UNSIGNED DEFAULT 0,
    `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `department_id` (`department_id`),
    KEY `is_staff` (`is_staff`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`  VARCHAR(100) NOT NULL,
    `key`   VARCHAR(100) NOT NULL,
    `group` VARCHAR(50)  DEFAULT 'general',
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role Permissions (junction)
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id`         INT UNSIGNED NOT NULL,
    `permission_id`   INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Roles (junction)
CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id`   INT UNSIGNED NOT NULL,
    `role_id`   INT UNSIGNED NOT NULL,
    `assigned_by` INT UNSIGNED DEFAULT NULL,
    `assigned_at` DATETIME   DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `role_id` (`role_id`),
    KEY `assigned_by` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Forum Structure
-- ============================================================

-- Forums (top-level containers)
CREATE TABLE IF NOT EXISTS `forums` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id`  INT UNSIGNED DEFAULT NULL,
    `name`           VARCHAR(200) NOT NULL,
    `description`    TEXT         DEFAULT NULL,
    `icon`           VARCHAR(50)  DEFAULT NULL,
    `color`          VARCHAR(7)   DEFAULT '#1E40AF',
    `sort_order`     INT UNSIGNED DEFAULT 0,
    `is_active`      TINYINT(1)   DEFAULT 1,
    `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `department_id` (`department_id`),
    KEY `is_active` (`is_active`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `forum_id`       INT UNSIGNED NOT NULL,
    `name`           VARCHAR(200) NOT NULL,
    `description`    TEXT         DEFAULT NULL,
    `icon`           VARCHAR(50)  DEFAULT NULL,
    `color`          VARCHAR(7)   DEFAULT '#374151',
    `sort_order`     INT UNSIGNED DEFAULT 0,
    `is_active`      TINYINT(1)   DEFAULT 1,
    `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `forum_id` (`forum_id`),
    KEY `is_active` (`is_active`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Category Access Control
CREATE TABLE IF NOT EXISTS `category_access` (
    `category_id`  INT UNSIGNED NOT NULL,
    `role_id`       INT UNSIGNED NOT NULL,
    `can_view`      TINYINT(1)   DEFAULT 1,
    `can_post`      TINYINT(1)   DEFAULT 1,
    `can_thread`    TINYINT(1)   DEFAULT 1,
    PRIMARY KEY (`category_id`, `role_id`),
    KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Thread & Post System
-- ============================================================

-- Threads
CREATE TABLE IF NOT EXISTS `threads` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)     NOT NULL,
    `category_id`     INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `title`           VARCHAR(300) NOT NULL,
    `slug`            VARCHAR(350) NOT NULL,
    `content`         LONGTEXT     NOT NULL,
    `views`           INT UNSIGNED DEFAULT 0,
    `replies`         INT UNSIGNED DEFAULT 0,
    `likes`           INT UNSIGNED DEFAULT 0,
    `is_pinned`       TINYINT(1)   DEFAULT 0,
    `is_locked`       TINYINT(1)   DEFAULT 0,
    `is_sticky`       TINYINT(1)   DEFAULT 0,
    `is_archived`     TINYINT(1)   DEFAULT 0,
    `is_deleted`      TINYINT(1)   DEFAULT 0,
    `deleted_by`      INT UNSIGNED DEFAULT NULL,
    `deleted_at`      DATETIME     DEFAULT NULL,
    `prefix`          VARCHAR(50)  DEFAULT NULL,
    `tags`            VARCHAR(500) DEFAULT NULL,
    `last_reply_at`   DATETIME     DEFAULT NULL,
    `last_reply_by`   INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    KEY `category_id` (`category_id`),
    KEY `user_id` (`user_id`),
    KEY `is_pinned` (`is_pinned`),
    KEY `is_sticky` (`is_sticky`),
    KEY `is_archived` (`is_archived`),
    KEY `is_deleted` (`is_deleted`),
    KEY `last_reply_at` (`last_reply_at`),
    KEY `created_at` (`created_at`),
    KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Posts
CREATE TABLE IF NOT EXISTS `posts` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)     NOT NULL,
    `thread_id`       INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `content`         LONGTEXT     NOT NULL,
    `likes`           INT UNSIGNED DEFAULT 0,
    `is_first_post`   TINYINT(1)   DEFAULT 0,
    `is_deleted`      TINYINT(1)   DEFAULT 0,
    `deleted_by`      INT UNSIGNED DEFAULT NULL,
    `deleted_at`      DATETIME     DEFAULT NULL,
    `edit_count`      INT UNSIGNED DEFAULT 0,
    `last_edited_at`  DATETIME     DEFAULT NULL,
    `last_edited_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    KEY `thread_id` (`thread_id`),
    KEY `user_id` (`user_id`),
    KEY `is_deleted` (`is_deleted`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thread Tags
CREATE TABLE IF NOT EXISTS `thread_tags` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(50)  NOT NULL,
    `color`      VARCHAR(7)   DEFAULT '#6B7280',
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thread-Tag Junction
CREATE TABLE IF NOT EXISTS `thread_tag_map` (
    `thread_id` INT UNSIGNED NOT NULL,
    `tag_id`    INT UNSIGNED NOT NULL,
    PRIMARY KEY (`thread_id`, `tag_id`),
    KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Poll System
-- ============================================================

-- Polls
CREATE TABLE IF NOT EXISTS `polls` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `thread_id`   INT UNSIGNED NOT NULL UNIQUE,
    `question`    VARCHAR(500) NOT NULL,
    `max_options` INT UNSIGNED DEFAULT 1,
    `expires_at`  DATETIME     DEFAULT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Poll Options
CREATE TABLE IF NOT EXISTS `poll_options` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `poll_id`    INT UNSIGNED NOT NULL,
    `option_text` VARCHAR(300) NOT NULL,
    `sort_order` INT UNSIGNED   DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `poll_id` (`poll_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Poll Votes
CREATE TABLE IF NOT EXISTS `poll_votes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `poll_id`    INT UNSIGNED NOT NULL,
    `option_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `poll_user` (`poll_id`, `user_id`),
    KEY `option_id` (`option_id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Reactions
-- ============================================================

CREATE TABLE IF NOT EXISTS `reactions` (
    `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`  VARCHAR(50)  NOT NULL,
    `icon`  VARCHAR(100) NOT NULL,
    `color` VARCHAR(7)   DEFAULT '#6B7280',
    `type`  ENUM('post','thread') DEFAULT 'post',
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_reactions` (
    `post_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `reaction_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`post_id`, `user_id`),
    KEY `user_id` (`user_id`),
    KEY `reaction_id` (`reaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `thread_reactions` (
    `thread_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `reaction_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`thread_id`, `user_id`),
    KEY `user_id` (`user_id`),
    KEY `reaction_id` (`reaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Bookmarks & Watch
-- ============================================================

CREATE TABLE IF NOT EXISTS `bookmarks` (
    `user_id`    INT UNSIGNED NOT NULL,
    `thread_id`  INT UNSIGNED NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `thread_id`),
    KEY `thread_id` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `thread_watch` (
    `user_id`    INT UNSIGNED NOT NULL,
    `thread_id`  INT UNSIGNED NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `thread_id`),
    KEY `thread_id` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Notifications
-- ============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `type`        ENUM('mention','quote','reply','pm','staff','reaction','system') NOT NULL,
    `from_user_id` INT UNSIGNED DEFAULT NULL,
    `reference_type` ENUM('thread','post','pm','user') DEFAULT NULL,
    `reference_id`  INT UNSIGNED DEFAULT NULL,
    `thread_id`   INT UNSIGNED DEFAULT NULL,
    `post_id`     INT UNSIGNED DEFAULT NULL,
    `message`     VARCHAR(500) DEFAULT NULL,
    `is_read`     TINYINT(1)   DEFAULT 0,
    `read_at`     DATETIME     DEFAULT NULL,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `is_read` (`is_read`),
    KEY `created_at` (`created_at`),
    KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Private Messages
-- ============================================================

CREATE TABLE IF NOT EXISTS `private_messages` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`         CHAR(36)     NOT NULL,
    `sender_id`    INT UNSIGNED NOT NULL,
    `recipient_id` INT UNSIGNED NOT NULL,
    `subject`      VARCHAR(300) NOT NULL,
    `content`      LONGTEXT     NOT NULL,
    `is_read`      TINYINT(1)   DEFAULT 0,
    `is_deleted_by_sender`  TINYINT(1) DEFAULT 0,
    `is_deleted_by_recipient` TINYINT(1) DEFAULT 0,
    `is_starred`  TINYINT(1)   DEFAULT 0,
    `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    KEY `sender_id` (`sender_id`),
    KEY `recipient_id` (`recipient_id`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Medals & Awards
-- ============================================================

CREATE TABLE IF NOT EXISTS `medals` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `icon`        VARCHAR(100) DEFAULT NULL,
    `color`       VARCHAR(7)   DEFAULT '#F59E0B',
    `type`        ENUM('valor','service','injury','community','training','other') DEFAULT 'other',
    `sort_order`  INT UNSIGNED DEFAULT 0,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_medals` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `medal_id`   INT UNSIGNED NOT NULL,
    `granted_by` INT UNSIGNED DEFAULT NULL,
    `reason`     TEXT         DEFAULT NULL,
    `granted_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `medal_id` (`medal_id`),
    KEY `granted_by` (`granted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Moderation
-- ============================================================

CREATE TABLE IF NOT EXISTS `warnings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `warned_by`  INT UNSIGNED NOT NULL,
    `reason`     TEXT         NOT NULL,
    `notes`      TEXT         DEFAULT NULL,
    `expires_at` DATETIME     DEFAULT NULL,
    `is_active`  TINYINT(1)   DEFAULT 1,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `warned_by` (`warned_by`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moderator_notes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `staff_id`   INT UNSIGNED NOT NULL,
    `note`       TEXT         NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Activity & Login Logs
-- ============================================================

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `action`     VARCHAR(100) NOT NULL,
    `details`    TEXT         DEFAULT NULL,
    `ip_address` VARCHAR(45)  DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `username`   VARCHAR(50)  DEFAULT NULL,
    `status`     ENUM('success','failed','locked','banned') NOT NULL,
    `ip_address` VARCHAR(45)  DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `reason`     VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `username` (`username`),
    KEY `status` (`status`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Settings
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         DEFAULT NULL,
    `type`       VARCHAR(20)  DEFAULT 'string',
    `autoload`   TINYINT(1)   DEFAULT 0,
    `updated_at` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`),
    KEY `autoload` (`autoload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Foreign Key Constraints
-- ============================================================

ALTER TABLE `users`
    ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_users_rank` FOREIGN KEY (`rank_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL;

ALTER TABLE `user_roles`
    ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ur_assigned` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `role_permissions`
    ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE;

ALTER TABLE `forums`
    ADD CONSTRAINT `fk_forums_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL;

ALTER TABLE `categories`
    ADD CONSTRAINT `fk_cat_forum` FOREIGN KEY (`forum_id`) REFERENCES `forums`(`id`) ON DELETE CASCADE;

ALTER TABLE `category_access`
    ADD CONSTRAINT `fk_ca_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ca_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE;

ALTER TABLE `threads`
    ADD CONSTRAINT `fk_threads_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_threads_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_threads_delby` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_threads_lastreply` FOREIGN KEY (`last_reply_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `posts`
    ADD CONSTRAINT `fk_posts_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_posts_delby` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_posts_editby` FOREIGN KEY (`last_edited_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `thread_tag_map`
    ADD CONSTRAINT `fk_ttm_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ttm_tag` FOREIGN KEY (`tag_id`) REFERENCES `thread_tags`(`id`) ON DELETE CASCADE;

ALTER TABLE `polls`
    ADD CONSTRAINT `fk_polls_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads`(`id`) ON DELETE CASCADE;

ALTER TABLE `poll_options`
    ADD CONSTRAINT `fk_po_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE;

ALTER TABLE `poll_votes`
    ADD CONSTRAINT `fk_pv_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pv_option` FOREIGN KEY (`option_id`) REFERENCES `poll_options`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pv_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `post_reactions`
    ADD CONSTRAINT `fk_pr_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pr_reaction` FOREIGN KEY (`reaction_id`) REFERENCES `reactions`(`id`) ON DELETE CASCADE;

ALTER TABLE `thread_reactions`
    ADD CONSTRAINT `fk_tr_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_tr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_tr_reaction` FOREIGN KEY (`reaction_id`) REFERENCES `reactions`(`id`) ON DELETE CASCADE;

ALTER TABLE `bookmarks`
    ADD CONSTRAINT `fk_bm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_bm_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads`(`id`) ON DELETE CASCADE;

ALTER TABLE `thread_watch`
    ADD CONSTRAINT `fk_tw_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_tw_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads`(`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
    ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_notif_from` FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_notif_thread` FOREIGN KEY (`thread_id`) REFERENCES `threads`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_notif_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE;

ALTER TABLE `private_messages`
    ADD CONSTRAINT `fk_pm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pm_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `user_medals`
    ADD CONSTRAINT `fk_um_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_um_medal` FOREIGN KEY (`medal_id`) REFERENCES `medals`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_um_granted` FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `warnings`
    ADD CONSTRAINT `fk_warn_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_warn_by` FOREIGN KEY (`warned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `moderator_notes`
    ADD CONSTRAINT `fk_mn_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_mn_staff` FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `activity_logs`
    ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `login_logs`
    ADD CONSTRAINT `fk_ll_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
