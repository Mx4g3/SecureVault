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
