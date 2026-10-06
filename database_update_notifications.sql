-- Adds notification support to an existing CRMC SASO database.
USE crmc_saso;

-- Adds in-app notifications (e.g. Good Moral status changes) for existing installs.
-- Safe to run once against a database that was created before this update.

CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    profile_id CHAR(36) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE CASCADE
);

CREATE INDEX idx_notifications_profile ON notifications(profile_id, is_read, created_at);
