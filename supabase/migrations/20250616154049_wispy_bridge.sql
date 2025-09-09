-- Initialize database schema
CREATE DATABASE IF NOT EXISTS sen_billets;
USE sen_billets;

-- Create user table if not exists
CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    roles JSON NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_8D93D649E7927C74 (email)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create event table if not exists
CREATE TABLE IF NOT EXISTS event (
    id INT AUTO_INCREMENT NOT NULL,
    organizer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NOT NULL,
    event_date DATETIME NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(255) NOT NULL,
    address VARCHAR(500) NOT NULL,
    category VARCHAR(100) NOT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    capacity INT NOT NULL,
    sold_tickets INT NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT "draft",
    tags JSON NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY(id),
    INDEX IDX_3BAE0AA7876C4DDA (organizer_id),
    CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES user (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create ticket_type table if not exists
CREATE TABLE IF NOT EXISTS ticket_type (
    id INT AUTO_INCREMENT NOT NULL,
    event_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    price INT NOT NULL,
    quantity INT NOT NULL,
    sold INT NOT NULL DEFAULT 0,
    description LONGTEXT DEFAULT NULL,
    early_bird TINYINT(1) NOT NULL DEFAULT 0,
    end_date DATETIME DEFAULT NULL,
    PRIMARY KEY(id),
    INDEX IDX_E4E2FD7871F7E88B (event_id),
    CONSTRAINT FK_E4E2FD7871F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create ticket table if not exists
CREATE TABLE IF NOT EXISTS ticket (
    id INT AUTO_INCREMENT NOT NULL,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    ticket_type_id INT NOT NULL,
    qr_code VARCHAR(255) NOT NULL UNIQUE,
    purchased_at DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT "valid",
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    used_at DATETIME DEFAULT NULL,
    validated_by_id INT DEFAULT NULL,
    validation_location VARCHAR(255) DEFAULT NULL,
    validation_device VARCHAR(255) DEFAULT NULL,
    validation_ip VARCHAR(45) DEFAULT NULL,
    payment_id INT DEFAULT NULL,
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_97A0ADA3C0D3C5C1 (qr_code),
    INDEX IDX_97A0ADA371F7E88B (event_id),
    INDEX IDX_97A0ADA3A76ED395 (user_id),
    INDEX IDX_97A0ADA3C98F5B8E (ticket_type_id),
    INDEX IDX_97A0ADA3C69DE5E5 (validated_by_id),
    INDEX IDX_97A0ADA3_QR_CODE (qr_code),
    INDEX IDX_97A0ADA3_STATUS (status),
    INDEX IDX_97A0ADA3_USED_AT (used_at),
    CONSTRAINT FK_97A0ADA371F7E88B FOREIGN KEY (event_id) REFERENCES event (id),
    CONSTRAINT FK_97A0ADA3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id),
    CONSTRAINT FK_97A0ADA3C98F5B8E FOREIGN KEY (ticket_type_id) REFERENCES ticket_type (id),
    CONSTRAINT FK_97A0ADA3C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create payments table if not exists
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    amount NUMERIC(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'XOF',
    method VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    transaction_id VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    bank_details JSON DEFAULT NULL,
    refund_amount NUMERIC(10, 2) DEFAULT NULL,
    failure_reason TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    PRIMARY KEY(id),
    INDEX IDX_2FE7E2DDA76ED395 (user_id),
    INDEX IDX_2FE7E2DD7B00651C (status),
    INDEX IDX_2FE7E2DD6AC99F1 (method),
    CONSTRAINT FK_2FE7E2DDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Add payment_id foreign key to tickets table if not exists
ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3C7C1F3EC FOREIGN KEY (payment_id) REFERENCES payments (id);
CREATE INDEX IDX_97A0ADA3C7C1F3EC ON ticket (payment_id);

-- Create cart_items table if not exists
CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    ticket_type_id INT NOT NULL,
    quantity INT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY(id),
    INDEX IDX_BEF48445A76ED395 (user_id),
    INDEX IDX_BEF48445C98F5B8E (ticket_type_id),
    CONSTRAINT FK_BEF48445A76ED395 FOREIGN KEY (user_id) REFERENCES user (id),
    CONSTRAINT FK_BEF48445C98F5B8E FOREIGN KEY (ticket_type_id) REFERENCES ticket_type (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Insert demo users
INSERT INTO user (email, roles, password, name, phone, created_at)
VALUES 
('organizer@test.com', '["ROLE_USER", "ROLE_ORGANIZER"]', '$2y$13$A8MQM2ZNOi99EW.ML7srhOJsCaybSbexAj/0yXrJs4gQ/2BqMMW2K', 'Organisateur Demo', '+221123456789', NOW()),
('client@test.com', '["ROLE_USER"]', '$2y$13$A8MQM2ZNOi99EW.ML7srhOJsCaybSbexAj/0yXrJs4gQ/2BqMMW2K', 'Client Demo', '+221987654321', NOW());
-- Note: Password is 'password' for both users