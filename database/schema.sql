-- AQUASENSE+ Phase 1: import into a new database with phpMyAdmin.
-- No DROP statements: an existing installation is never silently overwritten.
CREATE DATABASE IF NOT EXISTS aquasense CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aquasense;
SET time_zone = '+00:00';

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    contact_number VARCHAR(30) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE establishments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_code VARCHAR(40) NOT NULL UNIQUE,
    business_name VARCHAR(190) NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    owner_name VARCHAR(150) NOT NULL,
    address VARCHAR(500) NOT NULL,
    contact_number VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    registration_date DATE NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_establishment_name (business_name)
) ENGINE=InnoDB;

CREATE TABLE grease_traps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    trap_code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    capacity_liters DECIMAL(10,2) NOT NULL,
    low_threshold DECIMAL(5,2) NOT NULL,
    medium_threshold DECIMAL(5,2) NOT NULL,
    high_threshold DECIMAL(5,2) NOT NULL,
    critical_threshold DECIMAL(5,2) NOT NULL,
    installation_date DATE NULL,
    last_service_date DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    CHECK (capacity_liters > 0),
    CHECK (low_threshold >= 0 AND low_threshold < medium_threshold
       AND medium_threshold < high_threshold AND high_threshold < critical_threshold
       AND critical_threshold <= 100)
) ENGINE=InnoDB;

CREATE TABLE devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    device_type VARCHAR(60) NOT NULL DEFAULT 'ESP32',
    firmware_version VARCHAR(40) NULL,
    api_key_hash CHAR(64) NULL UNIQUE,
    last_seen_at DATETIME NULL,
    installation_date DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_device_last_seen (last_seen_at)
) ENGINE=InnoDB;

-- Assignment history keeps old readings attached to the original trap if a device moves.
CREATE TABLE device_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    grease_trap_id BIGINT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    active_device_id BIGINT UNSIGNED AS (IF(ended_at IS NULL, device_id, NULL)) STORED,
    active_trap_id BIGINT UNSIGNED AS (IF(ended_at IS NULL, grease_trap_id, NULL)) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (active_device_id),
    UNIQUE (active_trap_id),
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (grease_trap_id) REFERENCES grease_traps(id),
    CHECK (ended_at IS NULL OR ended_at >= started_at)
) ENGINE=InnoDB;

CREATE TABLE sensor_readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_assignment_id BIGINT UNSIGNED NOT NULL,
    ultrasonic_distance_cm DECIMAL(10,2) NULL,
    waste_level_percent DECIMAL(5,2) NOT NULL,
    temperature_c DECIMAL(6,2) NOT NULL,
    turbidity_ntu DECIMAL(10,2) NULL,
    flow_rate_lpm DECIMAL(10,3) NULL,
    gas_value DECIMAL(10,2) NULL,
    level_status ENUM('NORMAL','LOW','MEDIUM','HIGH','CRITICAL','OVERFLOW') NOT NULL,
    is_simulated BOOLEAN NOT NULL DEFAULT FALSE,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_assignment_id) REFERENCES device_assignments(id),
    CHECK (waste_level_percent BETWEEN 0 AND 100),
    CHECK (ultrasonic_distance_cm IS NULL OR ultrasonic_distance_cm >= 0),
    CHECK (turbidity_ntu IS NULL OR turbidity_ntu >= 0),
    CHECK (flow_rate_lpm IS NULL OR flow_rate_lpm >= 0),
    CHECK (gas_value IS NULL OR gas_value >= 0),
    INDEX idx_reading_assignment_time (device_assignment_id, recorded_at),
    INDEX idx_reading_time (recorded_at)
) ENGINE=InnoDB;

CREATE TABLE alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_assignment_id BIGINT UNSIGNED NOT NULL,
    sensor_reading_id BIGINT UNSIGNED NULL,
    alert_type ENUM('HIGH_LEVEL','OVERFLOW_WARNING','CRITICAL_LEVEL','HIGH_TEMPERATURE',
        'EMULSION_WARNING','HIGH_TURBIDITY','ABNORMAL_FLOW','DEVICE_OFFLINE') NOT NULL,
    severity ENUM('INFO','WARNING','CRITICAL') NOT NULL,
    sensor_value DECIMAL(12,3) NULL,
    message VARCHAR(500) NOT NULL,
    status ENUM('OPEN','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'OPEN',
    acknowledged_by BIGINT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_assignment_id) REFERENCES device_assignments(id),
    FOREIGN KEY (sensor_reading_id) REFERENCES sensor_readings(id),
    FOREIGN KEY (acknowledged_by) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id),
    INDEX idx_alert_status_time (status, created_at),
    INDEX idx_alert_type_time (alert_type, created_at)
) ENGINE=InnoDB;

CREATE TABLE oil_surrenders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(40) NOT NULL UNIQUE,
    establishment_id BIGINT UNSIGNED NOT NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    surrendered_at DATETIME NOT NULL,
    oil_quantity DECIMAL(10,3) NOT NULL,
    oil_unit ENUM('L','kg') NOT NULL DEFAULT 'L',
    related_sensor_reading_id BIGINT UNSIGNED NULL,
    status ENUM('PENDING','UNDER_REVIEW','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    verification_status ENUM('UNVERIFIED','VERIFIED','DISCREPANCY') NOT NULL DEFAULT 'UNVERIFIED',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (related_sensor_reading_id) REFERENCES sensor_readings(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    CHECK (oil_quantity > 0),
    INDEX idx_surrender_establishment_date (establishment_id, surrendered_at),
    INDEX idx_surrender_status (status)
) ENGINE=InnoDB;

CREATE TABLE oil_surrender_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    oil_surrender_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL UNIQUE,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (oil_surrender_id) REFERENCES oil_surrenders(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE incentive_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    minimum_oil_quantity DECIMAL(10,3) NOT NULL,
    oil_unit ENUM('L','kg') NOT NULL,
    rice_reward_quantity DECIMAL(10,3) NOT NULL,
    rice_unit ENUM('kg') NOT NULL DEFAULT 'kg',
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    CHECK (minimum_oil_quantity > 0 AND rice_reward_quantity > 0),
    CHECK (end_date IS NULL OR end_date >= effective_date),
    INDEX idx_rule_effective (is_active, effective_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE incentive_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    oil_surrender_id BIGINT UNSIGNED NOT NULL UNIQUE,
    rule_id BIGINT UNSIGNED NOT NULL,
    rice_quantity DECIMAL(10,3) NOT NULL,
    rice_unit ENUM('kg') NOT NULL DEFAULT 'kg',
    status ENUM('PENDING','DISTRIBUTED') NOT NULL DEFAULT 'PENDING',
    calculated_by BIGINT UNSIGNED NOT NULL,
    distributed_by BIGINT UNSIGNED NULL,
    distributed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (oil_surrender_id) REFERENCES oil_surrenders(id),
    FOREIGN KEY (rule_id) REFERENCES incentive_rules(id),
    FOREIGN KEY (calculated_by) REFERENCES users(id),
    FOREIGN KEY (distributed_by) REFERENCES users(id),
    CHECK (rice_quantity >= 0)
) ENGINE=InnoDB;

CREATE TABLE compliance_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(60) NOT NULL,
    establishment_id BIGINT UNSIGNED NULL,
    related_record_type VARCHAR(60) NULL,
    related_record_id BIGINT UNSIGNED NULL,
    description TEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_ledger_establishment_time (establishment_id, created_at),
    INDEX idx_ledger_event_time (event_type, created_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    record_type VARCHAR(60) NULL,
    record_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_audit_user_time (user_id, created_at),
    INDEX idx_audit_action_time (action, created_at)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Only token hashes may be persisted. Delivery and token issuance are a later task.
CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_reset_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempt_email_time (email_hash, attempted_at),
    INDEX idx_attempt_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;
