-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               5.6.12-log - MySQL Community Server (GPL)
-- Server OS:                    Win32
-- HeidiSQL Version:             8.0.0.4396
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Dumping structure for table skeleton.accounts
DROP TABLE IF EXISTS `accounts`;
CREATE TABLE IF NOT EXISTS `accounts` (
  `account_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `owner_id` mediumint(8) unsigned NOT NULL,
  `created_on` datetime NOT NULL,
  PRIMARY KEY (`account_id`),
  KEY `FK_accounts__owner_id` (`owner_id`),
  CONSTRAINT `FK_accounts__owner_id` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.accounts_users
DROP TABLE IF EXISTS `accounts_users`;
CREATE TABLE IF NOT EXISTS `accounts_users` (
  `account_id` mediumint(8) unsigned DEFAULT NULL,
  `user_id` mediumint(8) unsigned DEFAULT NULL,
  `inviter_id` mediumint(8) unsigned DEFAULT NULL,
  `status` enum('linked','invited','left','removed') DEFAULT NULL,
  UNIQUE KEY `account_id_user_id` (`account_id`,`user_id`),
  KEY `FK_accounts_users__invited_by` (`inviter_id`),
  KEY `FK_accounts_users__user_id` (`user_id`),
  CONSTRAINT `FK_accounts_users__account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_accounts_users__invited_by` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `FK_accounts_users__user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.account_deletion_requests
DROP TABLE IF EXISTS `account_deletion_requests`;
CREATE TABLE IF NOT EXISTS `account_deletion_requests` (
  `request_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` mediumint(8) unsigned NOT NULL,
  `requested_by` mediumint(8) unsigned NOT NULL,
  `requested_on` datetime NOT NULL,
  PRIMARY KEY (`request_id`),
  KEY `requested_on` (`requested_on`),
  KEY `FK_account_deletion_requests__account_id` (`account_id`),
  KEY `FK_account_deletion_requests__requested_by` (`requested_by`),
  CONSTRAINT `FK_account_deletion_requests__account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_account_deletion_requests__requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.account_invitation_links
DROP TABLE IF EXISTS `account_invitation_links`;
CREATE TABLE IF NOT EXISTS `account_invitation_links` (
  `link_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` mediumint(8) unsigned NOT NULL,
  `inviter_id` mediumint(8) unsigned NOT NULL,
  `invitee_id` mediumint(8) unsigned NOT NULL,
  `secure_key` varchar(32) NOT NULL,
  `email` varchar(64) NOT NULL,
  `expires_on` datetime NOT NULL,
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `secure_key` (`secure_key`),
  KEY `expires_on` (`expires_on`),
  KEY `FK_account_invitation_links__account_id` (`account_id`),
  KEY `FK_account_invitation_links__invitee_id` (`invitee_id`),
  KEY `FK_account_invitation_links__inviter_id` (`inviter_id`),
  CONSTRAINT `FK_account_invitation_links__account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_account_invitation_links__invitee_id` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `FK_account_invitation_links__inviter_id` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.account_permissions
DROP TABLE IF EXISTS `account_permissions`;
CREATE TABLE IF NOT EXISTS `account_permissions` (
  `account_id` mediumint(8) unsigned DEFAULT NULL,
  `user_id` mediumint(8) unsigned DEFAULT NULL,
  `permission` varchar(32) DEFAULT NULL,
  UNIQUE KEY `account_id_user_id_permission` (`account_id`,`user_id`,`permission`),
  KEY `FK_accounts_permissions__user_id` (`user_id`),
  CONSTRAINT `FK_accounts_permissions__account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_accounts_permissions__user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.projects
DROP TABLE IF EXISTS `projects`;
CREATE TABLE IF NOT EXISTS `projects` (
  `project_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` mediumint(8) unsigned NOT NULL,
  `created_by` mediumint(8) unsigned DEFAULT NULL,
  `name` varchar(512) NOT NULL,
  `description` varchar(1024) NOT NULL,
  `archived` tinyint(1) unsigned NOT NULL,
  `deleted` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`project_id`),
  KEY `account_id` (`account_id`),
  KEY `FK_projects__created_by` (`created_by`),
  CONSTRAINT `FK_projects__account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_projects__created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.projects_users
DROP TABLE IF EXISTS `projects_users`;
CREATE TABLE IF NOT EXISTS `projects_users` (
  `project_id` mediumint(8) unsigned DEFAULT NULL,
  `user_id` mediumint(8) unsigned DEFAULT NULL,
  `starred` tinyint(3) unsigned DEFAULT '0',
  UNIQUE KEY `project_id_user_id` (`project_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `FK_projects_users__project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_projects_users__user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.project_deletion_requests
DROP TABLE IF EXISTS `project_deletion_requests`;
CREATE TABLE IF NOT EXISTS `project_deletion_requests` (
  `request_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` mediumint(8) unsigned NOT NULL,
  `requested_on` datetime NOT NULL,
  PRIMARY KEY (`request_id`),
  KEY `requested_on` (`requested_on`),
  KEY `FK_project_deletion_requests__project_id` (`project_id`),
  CONSTRAINT `FK_project_deletion_requests__project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.subscriptions
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `subscription_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` mediumint(8) unsigned NOT NULL,
  `plan` varchar(32) NOT NULL,
  `expires_on` datetime DEFAULT NULL,
  `expired` tinyint(1) unsigned NOT NULL COMMENT 'When expired = 1, it means that the subscription was expired and also the grace period ended',
  `paused` tinyint(1) unsigned NOT NULL,
  `canceled` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`subscription_id`),
  KEY `FK_subscriptions__account_id` (`account_id`),
  CONSTRAINT `FK_subscriptions__account_id` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.


-- Dumping structure for table skeleton.subscription_events
DROP TABLE IF EXISTS `subscription_events`;
CREATE TABLE IF NOT EXISTS `subscription_events` (
  `event_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` mediumint(8) unsigned NOT NULL,
  `from_plan` varchar(32) NOT NULL,
  `to_plan` varchar(32) NOT NULL,
  `date` datetime NOT NULL,
  `expires_on` datetime DEFAULT NULL,
  `payment_id` mediumint(8) unsigned DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `FK_subscription_events__subscription_id` (`subscription_id`),
  CONSTRAINT `FK_subscription_events__subscription_id` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`subscription_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Data exporting was unselected.
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
