-- Import into a new database. Existing tables are preserved.
CREATE DATABASE IF NOT EXISTS securevault_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE securevault_db;
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(30) NOT NULL,
 email VARCHAR(254) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS documents (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 title VARCHAR(120) NOT NULL,
 document_type ENUM('Academic','Personal','Financial','Medical','Legal','Work','Other') NOT NULL,
 description TEXT NOT NULL,
 confidentiality ENUM('Public','Internal','Private') NOT NULL DEFAULT 'Private',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_document_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_owner_updated (user_id, updated_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS security_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 event_type VARCHAR(50) NOT NULL,
 description VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_log_user (user_id, id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 identity_hash CHAR(64) NOT NULL,
 remote_hash CHAR(64) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_identity_time (identity_hash, attempted_at),
 INDEX idx_remote_time (remote_hash, attempted_at),
 INDEX idx_attempt_time (attempted_at)
) ENGINE=InnoDB;

-- Additive migration for existing installations. Existing records are not changed.
USE securevault_db;
CREATE TABLE IF NOT EXISTS document_files (
 document_id INT UNSIGNED PRIMARY KEY,
 original_name VARCHAR(180) NOT NULL,
 mime_type VARCHAR(120) NOT NULL,
 byte_size INT UNSIGNED NOT NULL,
 content MEDIUMBLOB NOT NULL,
 CONSTRAINT fk_attachment_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

USE securevault_db;
CREATE TABLE IF NOT EXISTS verified_emails (
 user_id INT UNSIGNED PRIMARY KEY,
 verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS email_challenges (
 id CHAR(64) PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 code_hash VARCHAR(255) NOT NULL,
 remote_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 attempts INT NOT NULL DEFAULT 0,
 delivered TINYINT NOT NULL DEFAULT 0,
 consumed TINYINT NOT NULL DEFAULT 0,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX (user_id, created_at),
 INDEX (remote_hash, created_at)
) ENGINE=InnoDB;
