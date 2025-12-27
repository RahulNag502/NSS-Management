-- =====================================
-- NSS DATABASE SCHEMA
-- Database: nss_db
-- =====================================

CREATE DATABASE IF NOT EXISTS nss_db;
USE nss_db;

-- =====================================
-- ADMINS
-- =====================================
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================
-- VOLUNTEERS
-- =====================================
CREATE TABLE volunteers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(15),
    department VARCHAR(50),
    year VARCHAR(20),
    total_hours INT DEFAULT 0,
    password VARCHAR(255) NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    profile_image VARCHAR(255),
    email_notifications TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- =====================================
-- EVENTS
-- =====================================
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    location VARCHAR(255),
    event_hours INT DEFAULT 8,
    event_type VARCHAR(50) DEFAULT 'regular',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================
-- EVENT REGISTRATIONS
-- =====================================
CREATE TABLE event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    volunteer_id VARCHAR(10) NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(volunteer_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================
-- ATTENDANCE
-- =====================================
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    volunteer_id VARCHAR(10) NOT NULL,
    status ENUM('Present','Absent') DEFAULT 'Absent',
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(volunteer_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================
-- VOLUNTEER HOURS
-- =====================================
CREATE TABLE volunteer_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id VARCHAR(10) NOT NULL,
    event_id INT NOT NULL,
    hours_earned INT NOT NULL,
    earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(volunteer_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================
-- CERTIFICATES
-- =====================================
CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id VARCHAR(10) NOT NULL,
    event_id INT DEFAULT NULL,
    certificate_code VARCHAR(100) NOT NULL UNIQUE,
    certificate_type ENUM('120_hours','240_hours','manual') DEFAULT 'manual',
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(volunteer_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================
-- CERTIFICATE VALIDATION
-- =====================================
CREATE TABLE certificate_validation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_code VARCHAR(100) NOT NULL,
    is_valid TINYINT(1) DEFAULT 1,
    verified_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- =====================================
-- FEEDBACK
-- =====================================
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id VARCHAR(10) NOT NULL,
    event_id INT NOT NULL,
    rating INT,
    comments TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(volunteer_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================
-- GALLERY
-- =====================================
CREATE TABLE gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    uploaded_by VARCHAR(50) NOT NULL,
    user_type ENUM('admin','volunteer') DEFAULT 'admin',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    event_id INT DEFAULT NULL,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================
-- LOGIN ACTIVITY
-- =====================================
CREATE TABLE login_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    user_type ENUM('admin','volunteer') NOT NULL,
    action ENUM('login','logout') DEFAULT 'login',
    ip_address VARCHAR(45),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================
-- NOTIFICATIONS
-- =====================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target ENUM('all','admin','volunteer') DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================
-- PASSWORD RESET TOKENS
-- =====================================
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id VARCHAR(10) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(volunteer_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================
-- END OF SCHEMA
-- =====================================
