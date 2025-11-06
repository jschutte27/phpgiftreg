-- 
-- Database creation script for PHP Gift Registry (Idempotent Version)
-- This script can be safely rerun multiple times without errors
-- Updated: November 2025
-- 

CREATE DATABASE IF NOT EXISTS giftreg;

USE giftreg;

-- Note: Grant statements may fail if user doesn't exist, but that's expected
-- User creation should be done separately with proper authentication
GRANT ALL ON giftreg.* TO giftreg;

--
-- Table structure for table `allocs`
--

CREATE TABLE IF NOT EXISTS `allocs` (
  `itemid` int(11) NOT NULL default '0',
  `userid` int(11) NOT NULL default '0',
  `bought` tinyint(1) NOT NULL default '0',
  `quantity` int(11) NOT NULL default '0',
  PRIMARY KEY  (`itemid`,`userid`,`bought`)
);

--
-- Table structure for table `categories`
--

CREATE TABLE IF NOT EXISTS `categories` (
  `categoryid` int(11) NOT NULL auto_increment,
  `category` varchar(50) default NULL,
  PRIMARY KEY  (`categoryid`)
);

--
-- Dumping data for table `categories`
--

INSERT IGNORE INTO `categories` VALUES (1,'Books');
INSERT IGNORE INTO `categories` VALUES (2,'Music');
INSERT IGNORE INTO `categories` VALUES (3,'Video Games');
INSERT IGNORE INTO `categories` VALUES (4,'Clothing');
INSERT IGNORE INTO `categories` VALUES (5,'Movies/DVD');
INSERT IGNORE INTO `categories` VALUES (6,'Gift Certificates');
INSERT IGNORE INTO `categories` VALUES (7,'Hobbies');
INSERT IGNORE INTO `categories` VALUES (8,'Household');
INSERT IGNORE INTO `categories` VALUES (9,'Electronics');
INSERT IGNORE INTO `categories` VALUES (10,'Ornaments/Figurines');
INSERT IGNORE INTO `categories` VALUES (11,'Automotive');
INSERT IGNORE INTO `categories` VALUES (12,'Toys');
INSERT IGNORE INTO `categories` VALUES (13,'Jewellery');
INSERT IGNORE INTO `categories` VALUES (14,'Computer');
INSERT IGNORE INTO `categories` VALUES (15,'Games');
INSERT IGNORE INTO `categories` VALUES (16,'Tools');

--
-- Table structure for table `events`
--

CREATE TABLE IF NOT EXISTS `events` (
  `eventid` int(11) NOT NULL auto_increment,
  `userid` int(11) default NULL,
  `description` varchar(100) NOT NULL default '',
  `eventdate` date NOT NULL default '2000-01-01',
  `recurring` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`eventid`)
);

--
-- Dumping data for table `events`
--

INSERT IGNORE INTO `events` VALUES (1,NULL,'Christmas','2000-12-25',1);

--
-- Table structure for table `items`
--

CREATE TABLE IF NOT EXISTS `items` (
  `itemid` int(11) NOT NULL auto_increment,
  `userid` int(11) NOT NULL default '0',
  `description` varchar(255) NOT NULL default '',
  `price` decimal(7,2) default NULL,
  `source` varchar(255) NOT NULL default '',
  `ranking` int(11) NOT NULL default '0',
  `url` varchar(255) default NULL,
  `category` int(11) default NULL,
  `comment` text,
  `quantity` int(11) NOT NULL default '0',
  `image_filename` varchar(255) default NULL,
  PRIMARY KEY  (`itemid`)
);

--
-- Table structure for table `messages`
--

CREATE TABLE IF NOT EXISTS `messages` (
  `messageid` int(11) NOT NULL auto_increment,
  `sender` int(11) NOT NULL default '0',
  `recipient` int(11) NOT NULL default '0',
  `message` varchar(255) NOT NULL default '',
  `isread` tinyint(1) NOT NULL default '0',
  `created` date NOT NULL default '2000-01-01',
  PRIMARY KEY  (`messageid`)
);

--
-- Table structure for table `ranks`
--

CREATE TABLE IF NOT EXISTS `ranks` (
  `ranking` int(11) NOT NULL auto_increment,
  `title` varchar(50) NOT NULL default '',
  `rendered` varchar(255) NOT NULL default '',
  `rankorder` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ranking`)
);

--
-- Dumping data for table `ranks`
--

INSERT IGNORE INTO `ranks` VALUES (1,'1 - Wouldn\'t mind it','<img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_off.gif\" alt=\"\"><img src=\"images/star_off.gif\" alt=\"\"><img src=\"images/star_off.gif\" alt=\"\"><img src=\"images/star_off.gif\" alt=\"\">',1);
INSERT IGNORE INTO `ranks` VALUES (2,'2 - Would be nice to have','<img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_off.gif\" alt=\"\"><img src=\"images/star_off.gif\" alt=\"\"><img src=\"images/star_off.gif\" alt=\"\">',2);
INSERT IGNORE INTO `ranks` VALUES (3,'3 - Would make me happy','<img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_off.gif\" alt=\"\"><img src=\"images/star_off.gif\" alt=\"\">',3);
INSERT IGNORE INTO `ranks` VALUES (4,'4 - I would really, really like this','<img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_off.gif\" alt=\"\">',4);
INSERT IGNORE INTO `ranks` VALUES (5,'5 - I\'d love to get this','<img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\"><img src=\"images/star_on.gif\" alt=\"*\">',5);

--
-- Table structure for table `shoppers`
--

CREATE TABLE IF NOT EXISTS `shoppers` (
  `shopper` int(11) NOT NULL default '0',
  `mayshopfor` int(11) NOT NULL default '0',
  `pending` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`shopper`,`mayshopfor`)
);

--
-- Table structure for table `families`
--

CREATE TABLE IF NOT EXISTS `families` (
  familyid int(11) NOT NULL auto_increment,
  familyname varchar(255) NOT NULL default '',
  PRIMARY KEY  (familyid)
);

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `userid` int(11) NOT NULL auto_increment,
  `username` varchar(20) NOT NULL default '',
  `password` varchar(255) NULL default '',
  `fullname` varchar(50) NOT NULL default '',
  `email` varchar(255) default NULL,
  `google_id` varchar(255) NULL default NULL,
  `approved` tinyint(1) NOT NULL default '0',
  `admin` tinyint(1) NOT NULL default '0',
  `comment` text,
  `email_msgs` tinyint(1) NOT NULL default '0',
  `list_stamp` datetime default NULL,
  `initialfamilyid` int NULL,
  PRIMARY KEY  (`userid`),
  UNIQUE KEY `username` (`username`),  -- Ensure usernames are unique
  UNIQUE KEY `google_id` (`google_id`)  -- Ensure Google IDs are unique
);

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `token_id` int(11) NOT NULL auto_increment,
  `userid` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL default '0',
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `token` (`token`)
);

--
-- Table structure for table `memberships`
--

CREATE TABLE IF NOT EXISTS `memberships` (
  userid int(11) NOT NULL default '0',
  familyid int(11) NOT NULL default '0',
  PRIMARY KEY  (userid,familyid)
);

CREATE TABLE IF NOT EXISTS `subscriptions` (
	`publisher` int(11) NOT NULL,
	`subscriber` int(11) NOT NULL,
	`last_notified` datetime DEFAULT NULL,
	PRIMARY KEY (`publisher`,`subscriber`)
);

-- Add indexes for better performance (compatible with all MySQL versions)
-- Note: These will show warnings if indexes already exist, but won't fail
CREATE INDEX idx_items_userid ON items(userid);
CREATE INDEX idx_items_category ON items(category);
CREATE INDEX idx_messages_recipient ON messages(recipient);
CREATE INDEX idx_messages_sender ON messages(sender);
CREATE INDEX idx_allocs_itemid ON allocs(itemid);
CREATE INDEX idx_allocs_userid ON allocs(userid);
CREATE INDEX idx_events_userid ON events(userid);
CREATE INDEX idx_events_eventdate ON events(eventdate);
CREATE INDEX idx_password_reset_tokens_userid ON password_reset_tokens(userid);
CREATE INDEX idx_password_reset_tokens_expires_at ON password_reset_tokens(expires_at);

-- Update password column size for existing installations
-- This will safely modify the column if it's too small for bcrypt hashes
SET @sql = (SELECT IF(
    (SELECT CHARACTER_MAXIMUM_LENGTH 
     FROM information_schema.COLUMNS 
     WHERE table_schema = DATABASE() 
       AND table_name = 'users' 
       AND column_name = 'password') < 255,
    'ALTER TABLE users MODIFY COLUMN password varchar(255) NOT NULL default \'\';',
    'SELECT "Password column already correct size" as message;'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Script completion message
SELECT 'Database setup completed successfully. All tables and indexes are ready.' as status;