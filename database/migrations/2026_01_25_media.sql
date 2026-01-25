-- Add media support for messages (voice/file/photo/video)

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

ALTER TABLE `{{prefix}}messages`
  ADD COLUMN `type` ENUM('text', 'voice', 'file', 'photo', 'video') NOT NULL DEFAULT 'text' AFTER `recipient_id`,
  MODIFY COLUMN `body` TEXT NULL,
  ADD COLUMN `media_id` BIGINT UNSIGNED NULL AFTER `body`,
  ADD KEY `idx_media_id` (`media_id`);

ALTER TABLE `{{prefix}}messages`
  ADD CONSTRAINT `fk_msg_media` FOREIGN KEY (`media_id`) REFERENCES `{{prefix}}media_files` (`id`) ON DELETE SET NULL;
