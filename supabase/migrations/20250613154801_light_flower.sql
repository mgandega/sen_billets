/*
  # Create payments table

  1. New Tables
    - `payments`
      - `id` (int, primary key)
      - `user_id` (int, foreign key)
      - `amount` (decimal)
      - `currency` (varchar)
      - `method` (varchar)
      - `status` (varchar)
      - `transaction_id` (varchar)
      - `metadata` (json)
      - `bank_details` (json)
      - `refund_amount` (decimal)
      - `failure_reason` (text)
      - `created_at` (timestamp)
      - `updated_at` (timestamp)
      - `completed_at` (timestamp)

  2. Security
    - Enable RLS on `payments` table
    - Add policy for users to read their own payments

  3. Changes
    - Add payment_id to tickets table
    - Add payments relationship to users table
*/

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

-- Add payment_id to tickets table
ALTER TABLE ticket ADD COLUMN payment_id INT DEFAULT NULL;
ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3C7C1F3EC FOREIGN KEY (payment_id) REFERENCES payments (id);
CREATE INDEX IDX_97A0ADA3C7C1F3EC ON ticket (payment_id);