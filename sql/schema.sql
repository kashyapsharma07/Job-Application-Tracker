-- JobTracker Database Schema
-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS jobtracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jobtracker;

-- Users table
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    job_title VARCHAR(100),
    avatar VARCHAR(255),
    plan ENUM('free','premium') DEFAULT 'free',
    email_alerts TINYINT(1) DEFAULT 1,
    interview_reminders TINYINT(1) DEFAULT 1,
    marketing_comms TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Applications table
CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company VARCHAR(150) NOT NULL,
    job_title VARCHAR(150) NOT NULL,
    job_url VARCHAR(500),
    job_type ENUM('remote','hybrid','onsite','not_specified') DEFAULT 'not_specified',
    status ENUM('wishlist','applied','interviewing','offer','rejected') DEFAULT 'wishlist',
    salary_range VARCHAR(100),
    resume_id INT UNSIGNED,
    notes TEXT,
    applied_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Resume versions
CREATE TABLE resumes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED,
    version_label VARCHAR(100),
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add FK after resumes table created
ALTER TABLE applications ADD FOREIGN KEY (resume_id) REFERENCES resumes(id) ON DELETE SET NULL;

-- Interview stages / timeline events
CREATE TABLE application_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    event_type ENUM('status_change','interview','follow_up','deadline','note','offer') NOT NULL,
    title VARCHAR(200),
    description TEXT,
    event_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Reminders / scheduled follow-ups
CREATE TABLE reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    remind_at DATETIME NOT NULL,
    sent TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Password reset tokens
CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_applications_user ON applications(user_id);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_reminders_remind_at ON reminders(remind_at, sent);
CREATE INDEX idx_events_application ON application_events(application_id);
CREATE INDEX idx_events_date ON application_events(event_date);
