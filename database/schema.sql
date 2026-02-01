CREATE TABLE IF NOT EXISTS `{{prefix}}users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(120) NOT NULL,
  `username` VARCHAR(32) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(32) NULL,
  `bio` VARCHAR(255) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `language` VARCHAR(10) NOT NULL DEFAULT 'fa',
  `active_photo_id` BIGINT UNSIGNED NULL,
  `allow_voice_calls` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_active_photo` (`active_photo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}user_profile_photos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `thumbnail_name` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_photos_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}media_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('voice', 'file', 'photo', 'video') NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `size_bytes` INT UNSIGNED NOT NULL,
  `duration` INT UNSIGNED NULL,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `thumbnail_name` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  CONSTRAINT `fk_media_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_one_id` INT UNSIGNED NOT NULL,
  `user_two_id` INT UNSIGNED NOT NULL,
  `last_message_id` BIGINT UNSIGNED NULL,
  `last_message_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pair` (`user_one_id`, `user_two_id`),
  KEY `idx_last_message_at` (`last_message_at`),
  KEY `idx_user_one` (`user_one_id`),
  KEY `idx_user_two` (`user_two_id`),
  CONSTRAINT `fk_conv_user_one` FOREIGN KEY (`user_one_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conv_user_two` FOREIGN KEY (`user_two_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) NULL,
  `avatar_path` VARCHAR(255) NULL,
  `privacy_type` ENUM('private', 'public') NOT NULL DEFAULT 'private',
  `public_handle` VARCHAR(32) NULL,
  `private_invite_token` VARCHAR(64) NULL,
  `allow_member_invites` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_photos` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_videos` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_voice` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_files` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_public_handle` (`public_handle`),
  UNIQUE KEY `uniq_private_invite_token` (`private_invite_token`),
  KEY `idx_owner_user_id` (`owner_user_id`),
  KEY `idx_privacy` (`privacy_type`),
  CONSTRAINT `fk_groups_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}group_members` (
  `group_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner', 'member') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'removed') NOT NULL DEFAULT 'active',
  `joined_at` DATETIME NOT NULL,
  `removed_at` DATETIME NULL,
  PRIMARY KEY (`group_id`, `user_id`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_group_status` (`group_id`, `status`),
  CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `{{prefix}}groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NULL,
  `group_id` BIGINT UNSIGNED NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NULL,
  `client_id` VARCHAR(36) NULL,
  `type` ENUM('text', 'voice', 'file', 'photo', 'video', 'media') NOT NULL DEFAULT 'text',
  `body` TEXT NULL,
  `media_id` BIGINT UNSIGNED NULL,
  `attachments_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `reply_to_message_id` BIGINT UNSIGNED NULL,
  `is_deleted_for_all` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_client` (`sender_id`, `client_id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_group` (`group_id`),
  KEY `idx_group_created` (`group_id`, `created_at`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_recipient` (`recipient_id`),
  KEY `idx_reply_to` (`reply_to_message_id`),
  KEY `idx_media_id` (`media_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_msg_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `{{prefix}}conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_group` FOREIGN KEY (`group_id`) REFERENCES `{{prefix}}groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_media` FOREIGN KEY (`media_id`) REFERENCES `{{prefix}}media_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}message_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `media_id` BIGINT UNSIGNED NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_media` (`message_id`, `media_id`),
  KEY `idx_message` (`message_id`),
  KEY `idx_media` (`media_id`),
  CONSTRAINT `fk_msgatt_message` FOREIGN KEY (`message_id`) REFERENCES `{{prefix}}messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msgatt_media` FOREIGN KEY (`media_id`) REFERENCES `{{prefix}}media_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}message_receipts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('delivered', 'seen') NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_user_status` (`message_id`, `user_id`, `status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_id_user` (`id`, `user_id`),
  CONSTRAINT `fk_receipt_message` FOREIGN KEY (`message_id`) REFERENCES `{{prefix}}messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_receipt_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}call_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `caller_id` INT UNSIGNED NOT NULL,
  `callee_id` INT UNSIGNED NOT NULL,
  `started_at` DATETIME NOT NULL,
  `answered_at` DATETIME NULL,
  `ended_at` DATETIME NULL,
  `end_reason` ENUM('completed', 'declined', 'missed', 'busy', 'failed', 'canceled') NULL,
  `duration_seconds` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_call_conversation` (`conversation_id`),
  KEY `idx_call_caller` (`caller_id`),
  KEY `idx_call_callee` (`callee_id`),
  KEY `idx_call_started` (`started_at`),
  CONSTRAINT `fk_call_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `{{prefix}}conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_call_caller` FOREIGN KEY (`caller_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_call_callee` FOREIGN KEY (`callee_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}message_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reaction_emoji` VARCHAR(16) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_user` (`message_id`, `user_id`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_message_emoji` (`message_id`, `reaction_emoji`),
  CONSTRAINT `fk_reaction_message` FOREIGN KEY (`message_id`) REFERENCES `{{prefix}}messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reaction_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}message_deletions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `deleted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_user` (`message_id`, `user_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_del_message` FOREIGN KEY (`message_id`) REFERENCES `{{prefix}}messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_del_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{{prefix}}login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `identifier` VARCHAR(190) NOT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at` DATETIME NOT NULL,
  `lock_until` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip`),
  KEY `idx_identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
