-- Migration to add Google OAuth support
-- Add google_id column to users table

ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE;
ALTER TABLE users ADD INDEX idx_google_id (google_id);

-- Make password field nullable for OAuth users
ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL;

--
-- Table structure for password reset tokens
-- Allows secure password reset via email tokens instead of sending passwords directly
--

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `token_id` int(11) NOT NULL auto_increment,
  `userid` int(11) NOT NULL,
  `token` varchar(64) NOT NULL UNIQUE,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL default '0',
  PRIMARY KEY (`token_id`),
  KEY `idx_userid` (`userid`)
);

-- Clean up expired and used tokens periodically (consider adding a cron job)
-- DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = 1;

-- Add indexes for better performance
CREATE INDEX idx_password_reset_tokens_userid ON password_reset_tokens(userid);
CREATE INDEX idx_password_reset_tokens_expires_at ON password_reset_tokens(expires_at);
