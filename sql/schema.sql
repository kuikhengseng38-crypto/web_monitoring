-- Website Monitoring System
-- Select your database in phpMyAdmin, then Import this file.
-- Do not run CREATE DATABASE on cPanel (the database already exists).

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS websites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    url VARCHAR(500) NOT NULL,
    interval_minutes INT UNSIGNED NOT NULL DEFAULT 5,
    status VARCHAR(10) NOT NULL DEFAULT 'UNKNOWN',
    last_checked DATETIME NULL,
    response_time INT UNSIGNED NULL,
    http_code INT UNSIGNED NULL,
    is_slow TINYINT(1) NOT NULL DEFAULT 0,
    slow_threshold_ms INT UNSIGNED NOT NULL DEFAULT 3000,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id INT UNSIGNED NOT NULL,
    status VARCHAR(10) NOT NULL,
    response_time INT UNSIGNED NULL,
    http_code INT UNSIGNED NULL,
    checked_at DATETIME NOT NULL,
    is_status_change TINYINT(1) NOT NULL DEFAULT 0,
    alert_type VARCHAR(20) NULL,
    CONSTRAINT fk_logs_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    INDEX idx_logs_checked (checked_at),
    INDEX idx_logs_website (website_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('telegram_bot_token', ''),
    ('telegram_chat_id', ''),
    ('check_timeout', '10'),
    ('slow_threshold_ms', '3000'),
    ('treat_4xx_as_down', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Default admin / admin123 (password_hash PASSWORD_DEFAULT)
INSERT INTO admins (username, password) VALUES
    ('admin', '$2y$12$HjisOJiy43m4r3bV7cyTd.16XZ9SOhu7iwgrkAK6Djrqx45EJvV0S')
ON DUPLICATE KEY UPDATE username = username;
