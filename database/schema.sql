-- AQUASENSE+ database foundation through Phase 6: select an empty target database, then import this file.
-- No DROP statements: an existing installation is never silently overwritten.
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
    notes TEXT NULL,
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
    empty_distance_cm DECIMAL(10,2) NULL,
    full_distance_cm DECIMAL(10,2) NULL,
    installation_date DATE NULL,
    last_service_date DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    CHECK (capacity_liters > 0),
    CHECK (low_threshold >= 0 AND low_threshold < medium_threshold
       AND medium_threshold < high_threshold AND high_threshold < critical_threshold
       AND critical_threshold <= 100),
    CHECK ((empty_distance_cm IS NULL AND full_distance_cm IS NULL)
       OR (full_distance_cm >= 2 AND empty_distance_cm > full_distance_cm AND empty_distance_cm <= 400))
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
    device_reported_percent DECIMAL(5,2) NULL,
    temperature_c DECIMAL(6,2) NULL,
    turbidity_ntu DECIMAL(10,2) NULL,
    flow_rate_lpm DECIMAL(10,3) NULL,
    gas_value DECIMAL(10,2) NULL,
    level_status ENUM('NORMAL','LOW','MEDIUM','HIGH','CRITICAL','OVERFLOW','WARNING') NOT NULL,
    is_simulated BOOLEAN NOT NULL DEFAULT FALSE,
    is_test BOOLEAN NOT NULL DEFAULT FALSE,
    reading_uuid CHAR(36) NULL,
    sequence_number BIGINT UNSIGNED NULL,
    payload_hash CHAR(64) NULL,
    recorded_at DATETIME NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_assignment_id) REFERENCES device_assignments(id),
    CHECK (waste_level_percent BETWEEN 0 AND 100),
    CHECK (device_reported_percent IS NULL OR device_reported_percent BETWEEN 0 AND 100),
    CHECK (ultrasonic_distance_cm IS NULL OR ultrasonic_distance_cm >= 0),
    CHECK (turbidity_ntu IS NULL OR turbidity_ntu >= 0),
    CHECK (flow_rate_lpm IS NULL OR flow_rate_lpm >= 0),
    CHECK (gas_value IS NULL OR gas_value >= 0),
    INDEX idx_reading_assignment_time (device_assignment_id, recorded_at),
    INDEX idx_reading_time (recorded_at),
    INDEX idx_reading_received (received_at),
    UNIQUE (device_assignment_id, reading_uuid),
    UNIQUE (device_assignment_id, sequence_number)
) ENGINE=InnoDB;

CREATE TABLE alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_assignment_id BIGINT UNSIGNED NOT NULL,
    sensor_reading_id BIGINT UNSIGNED NULL,
    alert_type ENUM('HIGH_LEVEL','CRITICAL_LEVEL','OVERFLOW_WARNING','OVERFLOW','HIGH_TEMPERATURE',
        'EMULSION_WARNING','HIGH_TURBIDITY','ABNORMAL_FLOW','DEVICE_OFFLINE') NOT NULL,
    severity ENUM('INFO','WARNING','CRITICAL') NOT NULL,
    sensor_name VARCHAR(40) NULL,
    sensor_value DECIMAL(12,3) NULL,
    threshold_value DECIMAL(12,3) NULL,
    message VARCHAR(500) NOT NULL,
    status ENUM('ACTIVE','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'ACTIVE',
    first_triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    trigger_count INT UNSIGNED NOT NULL DEFAULT 1,
    acknowledged_by BIGINT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    resolution_note VARCHAR(1000) NULL,
    active_alert_key VARCHAR(180) AS (IF(status IN ('ACTIVE','ACKNOWLEDGED'), CONCAT(device_assignment_id,':',alert_type), NULL)) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_assignment_id) REFERENCES device_assignments(id),
    FOREIGN KEY (sensor_reading_id) REFERENCES sensor_readings(id),
    FOREIGN KEY (acknowledged_by) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id),
    INDEX idx_alert_status_time (status, created_at),
    INDEX idx_alert_type_time (alert_type, created_at),
    INDEX idx_alert_assignment_history (device_assignment_id, last_triggered_at),
    INDEX idx_alert_severity_status (severity, status),
    UNIQUE (active_alert_key)
) ENGINE=InnoDB;

CREATE TABLE oil_surrenders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(40) NOT NULL UNIQUE,
    submission_uuid CHAR(36) NULL UNIQUE,
    establishment_id BIGINT UNSIGNED NOT NULL,
    grease_trap_id BIGINT UNSIGNED NULL,
    device_id BIGINT UNSIGNED NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    surrendered_at DATETIME NOT NULL,
    oil_quantity DECIMAL(10,3) NOT NULL,
    oil_unit ENUM('L','kg') NOT NULL DEFAULT 'L',
    notes TEXT NULL,
    related_sensor_reading_id BIGINT UNSIGNED NULL,
    status ENUM('PENDING','UNDER_REVIEW','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    verification_status ENUM('UNVERIFIED','VERIFIED','DISCREPANCY') NOT NULL DEFAULT 'UNVERIFIED',
    review_started_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    remarks TEXT NULL,
    review_version INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    FOREIGN KEY (grease_trap_id) REFERENCES grease_traps(id),
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (related_sensor_reading_id) REFERENCES sensor_readings(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    CHECK (oil_quantity > 0),
    INDEX idx_surrender_establishment_date (establishment_id, surrendered_at),
    INDEX idx_surrender_status (status),
    INDEX idx_surrender_review_queue (status, surrendered_at),
    INDEX idx_surrender_trap_date (grease_trap_id, surrendered_at)
) ENGINE=InnoDB;

CREATE TABLE oil_surrender_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    oil_surrender_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL UNIQUE,
    original_filename VARCHAR(255) NULL,
    mime_type VARCHAR(50) NULL,
    file_size BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (oil_surrender_id) REFERENCES oil_surrenders(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_surrender_photo_parent (oil_surrender_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE incentive_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    minimum_oil_quantity DECIMAL(10,3) NOT NULL,
    oil_unit ENUM('L','kg') NOT NULL,
    rice_reward_quantity DECIMAL(10,3) NOT NULL,
    rice_unit ENUM('kg','g') NOT NULL DEFAULT 'kg',
    calculation_type ENUM('FIXED_PER_THRESHOLD','FIXED_TRANSACTION') NOT NULL DEFAULT 'FIXED_PER_THRESHOLD',
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_test BOOLEAN NOT NULL DEFAULT FALSE,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    CHECK (minimum_oil_quantity > 0 AND rice_reward_quantity > 0),
    CHECK (end_date IS NULL OR end_date > effective_date),
    INDEX idx_rule_effective (is_active, effective_date, end_date),
    INDEX idx_rule_unit_active_dates (oil_unit,is_active,effective_date,end_date)
) ENGINE=InnoDB;

CREATE TABLE incentive_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(40) NOT NULL UNIQUE,
    oil_surrender_id BIGINT UNSIGNED NOT NULL UNIQUE,
    establishment_id BIGINT UNSIGNED NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    rule_id BIGINT UNSIGNED NOT NULL,
    oil_quantity DECIMAL(10,3) NOT NULL,
    oil_unit ENUM('L','kg') NOT NULL,
    rule_name_snapshot VARCHAR(150) NOT NULL,
    oil_threshold_snapshot DECIMAL(10,3) NOT NULL,
    rule_reward_snapshot DECIMAL(10,3) NOT NULL,
    calculation_type_snapshot ENUM('FIXED_PER_THRESHOLD','FIXED_TRANSACTION') NOT NULL,
    qualifying_blocks INT UNSIGNED NOT NULL DEFAULT 1,
    rice_quantity DECIMAL(10,3) NOT NULL,
    rice_unit ENUM('kg','g') NOT NULL DEFAULT 'kg',
    status ENUM('CALCULATED','APPROVED_FOR_DISTRIBUTION','DISTRIBUTED','CANCELLED') NOT NULL DEFAULT 'CALCULATED',
    calculated_by BIGINT UNSIGNED NOT NULL,
    processed_at DATETIME NOT NULL,
    distributed_by BIGINT UNSIGNED NULL,
    distributed_at DATETIME NULL,
    distribution_notes VARCHAR(2000) NULL,
    status_version INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (oil_surrender_id) REFERENCES oil_surrenders(id),
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    FOREIGN KEY (owner_user_id) REFERENCES users(id),
    FOREIGN KEY (rule_id) REFERENCES incentive_rules(id),
    FOREIGN KEY (calculated_by) REFERENCES users(id),
    FOREIGN KEY (distributed_by) REFERENCES users(id),
    CHECK (rice_quantity > 0),
    INDEX idx_incentive_establishment_status (establishment_id,status),
    INDEX idx_incentive_status_processed (status,processed_at),
    INDEX idx_incentive_processed_at (processed_at),
    INDEX idx_incentive_distributed_at (distributed_at)
) ENGINE=InnoDB;

CREATE TABLE compliance_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_code VARCHAR(40) NOT NULL UNIQUE,
    event_type VARCHAR(60) NOT NULL,
    establishment_id BIGINT UNSIGNED NULL,
    grease_trap_id BIGINT UNSIGNED NULL,
    device_id BIGINT UNSIGNED NULL,
    related_record_type VARCHAR(60) NULL,
    related_record_id BIGINT UNSIGNED NULL,
    description TEXT NOT NULL,
    event_timestamp DATETIME NOT NULL,
    dedupe_key VARCHAR(190) NULL UNIQUE,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    FOREIGN KEY (grease_trap_id) REFERENCES grease_traps(id),
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_ledger_establishment_time (establishment_id, event_timestamp),
    INDEX idx_ledger_event_time (event_type, event_timestamp),
    INDEX idx_ledger_related_record (related_record_type,related_record_id),
    INDEX idx_ledger_device_time (device_id,event_timestamp),
    INDEX idx_ledger_trap_time (grease_trap_id,event_timestamp)
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
    INDEX idx_audit_action_time (action, created_at),
    INDEX idx_audit_created_at (created_at)
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
