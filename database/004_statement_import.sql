CREATE TABLE IF NOT EXISTS import_batches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE,
 household_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 filename VARCHAR(190) NOT NULL, file_hash CHAR(64) NOT NULL, sheet VARCHAR(190) NOT NULL,
 source_json MEDIUMTEXT NOT NULL, mapping_json TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'uploaded',
 created_at DATETIME(6) NOT NULL, UNIQUE(household_id,id),
 FOREIGN KEY(household_id,account_id) REFERENCES accounts(household_id,id), FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS import_rows (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, household_id BIGINT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NOT NULL,
 source_row INT NOT NULL, effective_date DATE NULL, description VARCHAR(500) NOT NULL,
 reference_text VARCHAR(500) NOT NULL, amount DECIMAL(20,6) NULL, direction VARCHAR(10) NULL,
 bank_balance DECIMAL(20,6) NULL, fingerprint CHAR(64) NULL, error_text VARCHAR(500) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending', transaction_id BIGINT UNSIGNED NULL,
 UNIQUE(batch_id,source_row), FOREIGN KEY(household_id,batch_id) REFERENCES import_batches(household_id,id),
 FOREIGN KEY(household_id,transaction_id) REFERENCES transactions(household_id,id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS import_links (
 household_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NOT NULL, fingerprint CHAR(64) NOT NULL,
 transaction_id BIGINT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(household_id,account_id,fingerprint), UNIQUE(household_id,account_id,transaction_id),
 FOREIGN KEY(household_id,account_id) REFERENCES accounts(household_id,id),
 FOREIGN KEY(household_id,transaction_id) REFERENCES transactions(household_id,id),
 FOREIGN KEY(household_id,batch_id) REFERENCES import_batches(household_id,id)
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_migrations VALUES ('004_statement_import',UTC_TIMESTAMP(6));
