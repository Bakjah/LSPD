-- ============================================================
-- Forum System Tables
-- Phase 2: Categories, Topics, Posts, Reactions
-- ============================================================
-- Run: mysql -u root -p lspd_portal < database/schema/forum.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Drop existing forum tables
-- ============================================================













-- ============================================================
-- Forums (Top-level containers per department)
-- ============================================================
CREATE TABLE `forums` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `department_id`   INT UNSIGNED        DEFAULT NULL,
    `name`            VARCHAR(200)        NOT NULL,
    `slug`            VARCHAR(200)        NOT NULL,
    `description`     TEXT                DEFAULT NULL,
    `icon`            VARCHAR(100)       DEFAULT NULL,
    `color`           VARCHAR(7)         DEFAULT '#3B82F6',
    `sort_order`      INT UNSIGNED        DEFAULT 0,
    `is_active`       TINYINT(1)         DEFAULT 1,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `slug` (`slug`),
    KEY `department_id` (`department_id`),
    KEY `is_active` (`is_active`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Categories (Within forums)
-- ============================================================
CREATE TABLE `categories` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `forum_id`        INT UNSIGNED        NOT NULL,
    `name`            VARCHAR(200)        NOT NULL,
    `slug`            VARCHAR(200)        NOT NULL,
    `description`     TEXT                DEFAULT NULL,
    `icon`            VARCHAR(100)       DEFAULT NULL,
    `color`           VARCHAR(7)         DEFAULT '#374151',
    `sort_order`      INT UNSIGNED        DEFAULT 0,
    `is_active`       TINYINT(1)         DEFAULT 1,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    KEY `forum_id` (`forum_id`),
    KEY `is_active` (`is_active`),
    KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Topics
-- ============================================================
CREATE TABLE `topics` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `category_id`     INT UNSIGNED        NOT NULL,
    `user_id`         INT UNSIGNED        NOT NULL,
    `title`           VARCHAR(300)        NOT NULL,
    `slug`            VARCHAR(350)        NOT NULL,
    `content`         LONGTEXT            NOT NULL,
    `views`           INT UNSIGNED        DEFAULT 0,
    `replies`         INT UNSIGNED        DEFAULT 0,
    `likes`           INT UNSIGNED        DEFAULT 0,
    `is_pinned`       TINYINT(1)         DEFAULT 0,
    `is_locked`       TINYINT(1)         DEFAULT 0,
    `is_sticky`       TINYINT(1)         DEFAULT 0,
    `is_archived`     TINYINT(1)         DEFAULT 0,
    `is_deleted`      TINYINT(1)         DEFAULT 0,
    `deleted_by`      INT UNSIGNED        DEFAULT NULL,
    `deleted_at`      DATETIME            DEFAULT NULL,
    `prefix`          VARCHAR(50)         DEFAULT NULL,
    `last_reply_at`   DATETIME            DEFAULT NULL,
    `last_reply_by`   INT UNSIGNED        DEFAULT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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

-- ============================================================
-- Posts (Replies)
-- ============================================================
CREATE TABLE `posts` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `topic_id`        INT UNSIGNED        NOT NULL,
    `user_id`         INT UNSIGNED        NOT NULL,
    `content`         LONGTEXT            NOT NULL,
    `likes`           INT UNSIGNED        DEFAULT 0,
    `is_first_post`   TINYINT(1)         DEFAULT 0,
    `is_deleted`      TINYINT(1)         DEFAULT 0,
    `deleted_by`      INT UNSIGNED        DEFAULT NULL,
    `deleted_at`      DATETIME            DEFAULT NULL,
    `edit_count`      INT UNSIGNED        DEFAULT 0,
    `last_edited_at`  DATETIME            DEFAULT NULL,
    `last_edited_by`  INT UNSIGNED        DEFAULT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    KEY `topic_id` (`topic_id`),
    KEY `user_id` (`user_id`),
    KEY `is_deleted` (`is_deleted`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Topic Tags
-- ============================================================
CREATE TABLE `topic_tags` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `name`            VARCHAR(50)         NOT NULL,
    `slug`            VARCHAR(50)         NOT NULL,
    `color`           VARCHAR(7)          DEFAULT '#6B7280',
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `name` (`name`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Topic-Tag Junction
-- ============================================================
CREATE TABLE `topic_tag_map` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `topic_id`        INT UNSIGNED        NOT NULL,
    `tag_id`          INT UNSIGNED        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `topic_tag` (`topic_id`, `tag_id`),
    KEY `topic_id` (`topic_id`),
    KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Polls
-- ============================================================
CREATE TABLE `polls` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `topic_id`        INT UNSIGNED        NOT NULL UNIQUE,
    `question`        VARCHAR(500)        NOT NULL,
    `max_options`     INT UNSIGNED        DEFAULT 1,
    `expires_at`      DATETIME            DEFAULT NULL,
    `is_active`       TINYINT(1)         DEFAULT 1,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    KEY `topic_id` (`topic_id`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Poll Options
-- ============================================================
CREATE TABLE `poll_options` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)            NOT NULL,
    `poll_id`         INT UNSIGNED        NOT NULL,
    `option_text`     VARCHAR(300)        NOT NULL,
    `sort_order`      INT UNSIGNED        DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    KEY `poll_id` (`poll_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Poll Votes
-- ============================================================
CREATE TABLE `poll_votes` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `poll_id`         INT UNSIGNED        NOT NULL,
    `option_id`       INT UNSIGNED        NOT NULL,
    `user_id`         INT UNSIGNED        NOT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `poll_user` (`poll_id`, `user_id`),
    KEY `poll_id` (`poll_id`),
    KEY `option_id` (`option_id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Topic Reactions
-- ============================================================
CREATE TABLE `topic_reactions` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `topic_id`        INT UNSIGNED        NOT NULL,
    `user_id`         INT UNSIGNED        NOT NULL,
    `reaction`        VARCHAR(50)         NOT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
    KEY `topic_id` (`topic_id`),
    KEY `user_id` (`user_id`),
    KEY `reaction` (`reaction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Post Reactions
-- ============================================================
CREATE TABLE `post_reactions` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `post_id`         INT UNSIGNED        NOT NULL,
    `user_id`         INT UNSIGNED        NOT NULL,
    `reaction`        VARCHAR(50)         NOT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `post_user` (`post_id`, `user_id`),
    KEY `post_id` (`post_id`),
    KEY `user_id` (`user_id`),
    KEY `reaction` (`reaction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Topic Watch (Notifications)
-- ============================================================
CREATE TABLE `topic_watch` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `topic_id`        INT UNSIGNED        NOT NULL,
    `user_id`         INT UNSIGNED        NOT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
    KEY `topic_id` (`topic_id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Topic Bookmarks
-- ============================================================
CREATE TABLE `topic_bookmarks` (
    `id`              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `topic_id`        INT UNSIGNED        NOT NULL,
    `user_id`         INT UNSIGNED        NOT NULL,
    `created_at`      DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
    KEY `topic_id` (`topic_id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Foreign Key Constraints
-- ============================================================
ALTER TABLE `forums`
    ADD CONSTRAINT `fk_forums_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL;

ALTER TABLE `categories`
    ADD CONSTRAINT `fk_categories_forum` FOREIGN KEY (`forum_id`) REFERENCES `forums`(`id`) ON DELETE CASCADE;

ALTER TABLE `topics`
    ADD CONSTRAINT `fk_topics_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_topics_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_topics_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_topics_last_reply_by` FOREIGN KEY (`last_reply_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `posts`
    ADD CONSTRAINT `fk_posts_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_posts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_posts_last_edited_by` FOREIGN KEY (`last_edited_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `topic_tag_map`
    ADD CONSTRAINT `fk_ttm_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ttm_tag` FOREIGN KEY (`tag_id`) REFERENCES `topic_tags`(`id`) ON DELETE CASCADE;

ALTER TABLE `polls`
    ADD CONSTRAINT `fk_polls_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE;

ALTER TABLE `poll_options`
    ADD CONSTRAINT `fk_po_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE;

ALTER TABLE `poll_votes`
    ADD CONSTRAINT `fk_pv_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pv_option` FOREIGN KEY (`option_id`) REFERENCES `poll_options`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pv_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `topic_reactions`
    ADD CONSTRAINT `fk_tr_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_tr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `post_reactions`
    ADD CONSTRAINT `fk_pr_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `topic_watch`
    ADD CONSTRAINT `fk_tw_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_tw_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `topic_bookmarks`
    ADD CONSTRAINT `fk_tb_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_tb_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Insert Default Forums
-- ============================================================
INSERT INTO `forums` (`uuid`, `department_id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`) VALUES
-- LSPD Forums
(UUID(), 2, 'LSPD General', 'lspd-general', 'General discussions for LSPD personnel', '💬', '#2563EB', 1),
(UUID(), 2, 'LSPD Announcements', 'lspd-announcements', 'Official announcements from LSPD command', '📢', '#2563EB', 2),
(UUID(), 2, 'LSPD Recruitment', 'lspd-recruitment', 'Recruitment applications and status updates', '📋', '#2563EB', 3),
(UUID(), 2, 'LSPD Training', 'lspd-training', 'Training materials and resources', '📚', '#2563EB', 4),
(UUID(), 2, 'LSPD Archive', 'lspd-archive', 'Archived topics and completed discussions', '📦', '#2563EB', 5),

-- LSSD Forums
(UUID(), 3, 'LSSD General', 'lssd-general', 'General discussions for LSSD personnel', '💬', '#92400E', 1),
(UUID(), 3, 'LSSD Announcements', 'lssd-announcements', 'Official announcements from LSSD command', '📢', '#92400E', 2),
(UUID(), 3, 'LSSD Recruitment', 'lssd-recruitment', 'Recruitment applications and status updates', '📋', '#92400E', 3),
(UUID(), 3, 'LSSD Archive', 'lssd-archive', 'Archived topics and completed discussions', '📦', '#92400E', 4),

-- LSFD Forums
(UUID(), 4, 'LSFD General', 'lsfd-general', 'General discussions for LSFD personnel', '💬', '#DC2626', 1),
(UUID(), 4, 'LSFD Announcements', 'lsfd-announcements', 'Official announcements from LSFD command', '📢', '#DC2626', 2),
(UUID(), 4, 'LSFD Recruitment', 'lsfd-recruitment', 'Recruitment applications and status updates', '📋', '#DC2626', 3),
(UUID(), 4, 'LSFD Training', 'lsfd-training', 'Training materials and resources', '📚', '#DC2626', 4),
(UUID(), 4, 'LSFD Archive', 'lsfd-archive', 'Archived topics and completed discussions', '📦', '#DC2626', 5),

-- LSN Forums
(UUID(), 5, 'LSN General', 'lsn-general', 'General discussions for LSN personnel', '💬', '#EA580C', 1),
(UUID(), 5, 'LSN Announcements', 'lsn-announcements', 'Official announcements from LSN management', '📢', '#EA580C', 2),
(UUID(), 5, 'LSN Recruitment', 'lsn-recruitment', 'Recruitment applications and status updates', '📋', '#EA580C', 3),
(UUID(), 5, 'LSN Archive', 'lsn-archive', 'Archived topics and completed discussions', '📦', '#EA580C', 4),

-- Community Forums (Global)
(UUID(), NULL, 'Community Hub', 'community-hub', 'General community discussions', '🌐', '#9925EB', 1),
(UUID(), NULL, 'Introductions', 'introductions', 'Introduce yourself to the community', '👋', '#9925EB', 2),
(UUID(), NULL, 'Off-Topic', 'off-topic', 'Non-roleplay discussions', '🎮', '#9925EB', 3);

-- ============================================================
-- Insert Default Categories
-- ============================================================
INSERT INTO `categories` (`uuid`, `forum_id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`) VALUES
-- LSPD General Categories
(UUID(), 1, 'General Discussion', 'general-discussion', 'General LSPD discussions', '💬', '#2563EB', 1),
(UUID(), 1, 'Patrol Operations', 'patrol-operations', 'Patrol-related discussions', '🚔', '#2563EB', 2),
(UUID(), 1, 'Detective Bureau', 'detective-bureau', 'Detective unit discussions', '🔍', '#2563EB', 3),
(UUID(), 1, 'Traffic Division', 'traffic-division', 'Traffic-related discussions', '🚦', '#2563EB', 4),
(UUID(), 1, 'SWAT Division', 'swat-division', 'SWAT unit discussions', '⚡', '#2563EB', 5),

-- LSPD Announcements Categories
(UUID(), 2, 'Official Orders', 'official-orders', 'Official orders from command', '📜', '#2563EB', 1),
(UUID(), 2, 'Policy Updates', 'policy-updates', 'Updates to department policies', '📋', '#2563EB', 2),

-- LSPD Recruitment Categories
(UUID(), 3, 'Cadet Applications', 'cadet-applications', 'Cadet application submissions', '📝', '#2563EB', 1),
(UUID(), 3, 'Officer Applications', 'officer-applications', 'Lateral entry applications', '📝', '#2563EB', 2),

-- LSSD General Categories
(UUID(), 6, 'General Discussion', 'lssd-general-discussion', 'General LSSD discussions', '💬', '#92400E', 1),
(UUID(), 6, 'Patrol Operations', 'lssd-patrol-operations', 'Patrol-related discussions', '🚔', '#92400E', 2),
(UUID(), 6, 'Investigations', 'lssd-investigations', 'Investigation bureau discussions', '🔍', '#92400E', 3),

-- LSFD General Categories
(UUID(), 10, 'General Discussion', 'lsfd-general-discussion', 'General LSFD discussions', '💬', '#DC2626', 1),
(UUID(), 10, 'Fire Operations', 'fire-operations', 'Firefighting operations', '🔥', '#DC2626', 2),
(UUID(), 10, 'EMS Division', 'ems-division', 'Emergency medical services', '🚑', '#DC2626', 3),

-- LSN General Categories
(UUID(), 14, 'General Discussion', 'lsn-general-discussion', 'General LSN discussions', '💬', '#EA580C', 1),
(UUID(), 14, 'News Reports', 'news-reports', 'News reports and articles', '📰', '#EA580C', 2),
(UUID(), 14, 'Media Discussions', 'media-discussions', 'Media production discussions', '🎬', '#EA580C', 3),

-- Community Hub Categories
(UUID(), 18, 'General Chat', 'general-chat', 'General community chat', '💬', '#9925EB', 1),
(UUID(), 18, 'Events', 'community-events', 'Community events and meetups', '🎉', '#9925EB', 2),
(UUID(), 18, 'Feedback', 'feedback', 'Suggestions and feedback', '💡', '#9925EB', 3);

-- ============================================================
-- Insert Default Topic Tags
-- ============================================================
INSERT INTO `topic_tags` (`uuid`, `name`, `slug`, `color`) VALUES
(UUID(), 'Announcement', 'announcement', '#F59E0B'),
(UUID(), 'Important', 'important', '#EF4444'),
(UUID(), 'Discussion', 'discussion', '#3B82F6'),
(UUID(), 'Question', 'question', '#8B5CF6'),
(UUID(), 'Guide', 'guide', '#22C55E'),
(UUID(), 'Resolved', 'resolved', '#6B7280'),
(UUID(), 'Hot', 'hot', '#F97316');

-- ============================================================
-- Insert Sample Topics
-- ============================================================
INSERT INTO `topics` (`uuid`, `category_id`, `user_id`, `title`, `slug`, `content`, `views`, `replies`, `is_pinned`, `created_at`) VALUES
-- LSPD Announcement
(UUID(), 7, 1, 'Welcome to LSPD Forum Portal', 'welcome-to-lspd-forum-portal', '[b]Welcome to the Los Santos Police Department Forum Portal![/b]\n\nThis is the official forum for all LSPD personnel. Please familiarize yourself with the forum rules and guidelines.\n\n[b]Forum Rules:[/b]\n[list]\n[*]Maintain professional conduct at all times\n[*]Respect fellow officers and staff\n[*]Follow department SOPs\n[*]Report any issues to command staff\n[/list]\n\nWelcome aboard, Officer!', 156, 3, 1, NOW()),

-- LSPD General Discussion
(UUID(), 4, 1, 'Standard Patrol Procedures Update', 'standard-patrol-procedures-update', '[b]IMPORTANT: Standard Patrol Procedures Update[/b]\n\nAll officers are required to review the updated patrol procedures effective immediately.\n\n[b]Key Changes:[/b]\n[list]\n[*]Traffic stops now require body camera activation\n[*]Use of force reports must be filed within 24 hours\n[*]Dispatch communication protocols updated\n[/list]\n\nQuestions? Contact your supervisor.', 89, 2, 0, NOW()),

-- LSSD Discussion
(UUID(), 11, 1, 'Sheriff Department Monthly Briefing', 'sheriff-department-monthly-briefing', '[b]Monthly Briefing - June 2026[/b]\n\nWelcome to the monthly briefing for all LSSD personnel.\n\n[b]This Month\\'s Focus:[/b]\n[list]\n[*]Community policing initiatives\n[*]New patrol vehicle assignments\n[*]Training schedule updates\n[/list]\n\nStay safe out there, Deputies!', 45, 1, 0, NOW()),

-- LSFD Discussion
(UUID(), 15, 1, 'Fire Safety Week Announcement', 'fire-safety-week-announcement', '[b]Fire Safety Week 2026[/b]\n\nFire Safety Week is approaching! All LSFD personnel should review the schedule and prepare for community outreach events.\n\n[b]Event Schedule:[/b]\n[list]\n[*]Monday: School visits\n[*]Tuesday: Community center demonstrations\n[*]Wednesday: Open house at all stations\n[/list]\n\nLet\\'s make this year\\'s Fire Safety Week the best yet!', 67, 2, 0, NOW()),

-- LSN Discussion
(UUID(), 19, 1, 'Welcome to LSN News', 'welcome-to-lsn-news', '[b]Welcome to Los Santos News![/b]\n\nLSN is the official news organization of Los Santos. Our mission is to provide accurate, timely news coverage to the community.\n\n[b]How to Contribute:[/b]\n[list]\n[*]Submit news reports for review\n[*]Participate in media productions\n[*]Attend community events for coverage\n[/list]\n\nLooking forward to working with all of you!', 34, 0, 0, NOW()),

-- Community Introduction
(UUID(), 22, 2, 'Introduction: John Doe', 'introduction-john-doe', 'Hello everyone!\n\nI\\'m John Doe, a new member of the LSPD. Looking forward to serving the community and meeting everyone!\n\n[b]Background:[/b]\nI have experience in law enforcement roleplay and excited to be part of LSPD.\n\nBest regards,\nJohn', 23, 1, 0, NOW());

-- ============================================================
-- Insert Sample Posts
-- ============================================================
INSERT INTO `posts` (`uuid`, `topic_id`, `user_id`, `content`, `likes`, `is_first_post`, `created_at`) VALUES
-- First topic posts
(UUID(), 1, 2, 'Thank you for the welcome! Excited to be part of the team.', 2, 0, NOW()),
(UUID(), 1, 3, 'Welcome aboard! Feel free to reach out if you need any help.', 1, 0, NOW()),
(UUID(), 1, 4, 'Great to have this forum operational. Well done, Command!', 3, 0, NOW()),

-- Second topic posts
(UUID(), 2, 2, 'Will the body camera requirement apply to off-duty carry as well?', 1, 0, NOW()),
(UUID(), 2, 1, '[quote]Will the body camera requirement apply to off-duty carry as well?[/quote]\n\nYes, any official action while armed requires camera activation.', 2, 0, NOW()),

-- Third topic posts
(UUID(), 3, 2, 'Looking forward to the new patrol assignments. Any word on which vehicles we\\'ll be getting?', 0, 0, NOW()),

-- Fourth topic posts
(UUID(), 4, 2, 'I\\'ll be available for the school visits on Monday. Count me in!', 1, 0, NOW()),
(UUID(), 4, 3, 'The community center demonstration last year was a huge success. Let\\'s make this year even better!', 2, 0, NOW()),

-- Sixth topic posts
(UUID(), 6, 1, 'Welcome to the community, John! If you have any questions about LSPD procedures, don\\'t hesitate to ask.', 1, 0, NOW());
