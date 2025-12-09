-- API Tokens table for REST API authentication
-- Run this to add API token support to existing database

DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `name` varchar(100) DEFAULT NULL COMMENT 'Token description/name',
  `permissions` text COMMENT 'JSON array of permitted endpoints',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_used` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add API access field to users table
ALTER TABLE `users` ADD COLUMN `api_access` tinyint(1) NOT NULL DEFAULT '1' AFTER `is_active`;
