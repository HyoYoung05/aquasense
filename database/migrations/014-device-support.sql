-- AQUASENSE+ role-separated device support communication.
-- Additive and safe to run more than once on the supported MariaDB/MySQL target.

CREATE TABLE IF NOT EXISTS device_support_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(40) NOT NULL UNIQUE,
    device_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    assigned_to BIGINT UNSIGNED NULL,
    subject VARCHAR(150) NOT NULL,
    category ENUM('CONNECTION','SENSOR','POWER','FIRMWARE','OTHER') NOT NULL,
    priority ENUM('NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
    status ENUM('OPEN','IN_PROGRESS','RESOLVED','CLOSED') NOT NULL DEFAULT 'OPEN',
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    INDEX idx_support_status_updated (status, updated_at),
    INDEX idx_support_requester_updated (requested_by, updated_at),
    INDEX idx_support_device_updated (device_id, updated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_support_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES device_support_requests(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    INDEX idx_support_message_request (request_id, created_at)
) ENGINE=InnoDB;
