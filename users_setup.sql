-- =============================================
-- RUN THIS IN phpMyAdmin AFTER importing the main DB
-- Creates the users table for login
-- =============================================
USE payroll_system;

CREATE TABLE IF NOT EXISTS users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    full_name  VARCHAR(100) NOT NULL,
    role       ENUM('admin','hr','finance','staff') DEFAULT 'staff',
    is_active  BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default login accounts:
-- admin / admin123
-- hr    / hr1234
INSERT IGNORE INTO users (username, password, full_name, role) VALUES
('admin', SHA2('admin123', 256), 'System Administrator', 'admin'),
('hr',    SHA2('hr1234',   256), 'HR Manager',           'hr');
