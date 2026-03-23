-- Add column to track if user needs to setup 2FA on next login
ALTER TABLE users ADD COLUMN twofa_setup_required BOOLEAN DEFAULT 0 COMMENT 'User needs to setup 2FA during next login';
