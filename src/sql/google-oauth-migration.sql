-- Migration to add Google OAuth support
-- Add google_id column to users table

ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE;
ALTER TABLE users ADD INDEX idx_google_id (google_id);

-- Make password field nullable for OAuth users
ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL;