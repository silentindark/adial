-- MySQL dump 10.14  Distrib 5.5.65-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: adialer
-- ------------------------------------------------------
-- Server version	5.5.65-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `active_channels`
--

DROP TABLE IF EXISTS `active_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `active_channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) DEFAULT NULL,
  `channel_id` varchar(255) NOT NULL,
  `state` varchar(50) DEFAULT NULL,
  `caller` varchar(100) DEFAULT NULL,
  `connected` varchar(100) DEFAULT NULL,
  `accountcode` varchar(100) DEFAULT NULL,
  `dialplan_app` varchar(100) DEFAULT NULL,
  `dialplan_appdata` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_id` (`channel_id`),
  KEY `campaign_id` (`campaign_id`),
  CONSTRAINT `active_channels_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaign_numbers`
--

DROP TABLE IF EXISTS `campaign_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `status` enum('pending','calling','answered','failed','no_answer','busy','cancel','chanunavail','congestion','originate_failed') NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT '0',
  `last_attempt` timestamp NULL DEFAULT NULL,
  `data` text COMMENT 'Additional JSON data for the number',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `status` (`status`),
  KEY `phone_number` (`phone_number`),
  CONSTRAINT `campaign_numbers_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `trunk_type` enum('custom','pjsip','sip') NOT NULL DEFAULT 'custom',
  `trunk_value` varchar(255) NOT NULL COMMENT 'Trunk name or custom dial string',
  `callerid` varchar(100) DEFAULT NULL,
  `agent_dest_type` enum('custom','exten','ivr','queue') NOT NULL DEFAULT 'custom',
  `agent_dest_value` varchar(255) DEFAULT NULL COMMENT 'Destination value based on type',
  `record_calls` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('stopped','running','paused') NOT NULL DEFAULT 'stopped',
  `concurrent_calls` int(11) NOT NULL DEFAULT '1',
  `retry_times` int(11) NOT NULL DEFAULT '0',
  `retry_delay` int(11) NOT NULL DEFAULT '300' COMMENT 'Retry delay in seconds',
  `dial_timeout` int(11) NOT NULL DEFAULT '30' COMMENT 'Time to wait for answer (15-180 seconds)',
  `call_timeout` int(11) NOT NULL DEFAULT '600' COMMENT 'Max call duration after answer (30-3600 seconds)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cdr`
--

DROP TABLE IF EXISTS `cdr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cdr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) DEFAULT NULL,
  `campaign_number_id` int(11) DEFAULT NULL,
  `channel_id` varchar(255) DEFAULT NULL,
  `uniqueid` varchar(255) DEFAULT NULL,
  `callerid` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `agent` varchar(100) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `answer_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration` int(11) DEFAULT '0' COMMENT 'Duration in seconds',
  `billsec` int(11) DEFAULT '0' COMMENT 'Billable seconds',
  `disposition` enum('answered','no_answer','busy','failed','cancelled') DEFAULT NULL,
  `recording_file` varchar(255) DEFAULT NULL,
  `recording_leg1` varchar(255) DEFAULT NULL COMMENT 'Recording file for leg 1',
  `recording_leg2` varchar(255) DEFAULT NULL COMMENT 'Recording file for leg 2',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `campaign_number_id` (`campaign_number_id`),
  KEY `uniqueid` (`uniqueid`),
  KEY `start_time` (`start_time`),
  CONSTRAINT `cdr_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cdr_ibfk_2` FOREIGN KEY (`campaign_number_id`) REFERENCES `campaign_numbers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ivr_actions`
--

DROP TABLE IF EXISTS `ivr_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ivr_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ivr_menu_id` int(11) NOT NULL,
  `dtmf_digit` varchar(10) NOT NULL,
  `action_type` enum('exten','queue','hangup','playback','goto_ivr') NOT NULL,
  `action_value` varchar(255) NOT NULL,
  `channel_type` enum('sip','pjsip') NOT NULL DEFAULT 'sip' COMMENT 'Channel type for exten actions (SIP or PJSIP)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_menu_dtmf` (`ivr_menu_id`,`dtmf_digit`),
  KEY `ivr_menu_id` (`ivr_menu_id`),
  CONSTRAINT `ivr_actions_ibfk_1` FOREIGN KEY (`ivr_menu_id`) REFERENCES `ivr_menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ivr_menus`
--

DROP TABLE IF EXISTS `ivr_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ivr_menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) DEFAULT NULL COMMENT 'Campaign ID (optional) - NULL for standalone IVR menus',
  `name` varchar(255) NOT NULL,
  `audio_file` varchar(255) NOT NULL COMMENT 'Path to audio file',
  `timeout` int(11) NOT NULL DEFAULT '10',
  `max_digits` int(11) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  CONSTRAINT `ivr_menus_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL
)ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `endpoints_cache`
--

DROP TABLE IF EXISTS `endpoints_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `endpoints_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `technology` enum('SIP','PJSIP') NOT NULL,
  `resource` varchar(100) NOT NULL,
  `state` varchar(50) DEFAULT NULL,
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_endpoint` (`technology`,`resource`),
  KEY `technology` (`technology`),
  KEY `last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT '1',
  `api_access` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
-- Default admin user: username=admin, password=admin
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `is_active`, `api_access`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$oR5Dcs4NJMByGLwuxHwF3uoLxBspqSfjm1E0zDJwxGRmbtXojWM96', 'admin@localhost', 'Administrator', 'admin', 1, 1, NOW(), NOW());

--
-- Table structure for table `api_tokens`
--

DROP TABLE IF EXISTS `api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'adialer'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-05 17:02:56
