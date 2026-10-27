-- D1 completed using the laboratory's field specifications.
-- Table definitions only. Application timestamps are stored in UTC.

CREATE TABLE IF NOT EXISTS customers (
    customer_id VARCHAR(32) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    marketing_opt_in TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,

    PRIMARY KEY (customer_id),
    UNIQUE KEY uq_customers_email (email),
    CONSTRAINT chk_customers_consent CHECK (marketing_opt_in IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_outbox (
    request_id CHAR(32) NOT NULL,
    campaign_id VARCHAR(64) NOT NULL,
    customer_id VARCHAR(32) NOT NULL,
    promo_code VARCHAR(64) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('PENDING', 'QUEUED') NOT NULL DEFAULT 'PENDING',
    created_at DATETIME NOT NULL,
    queued_at DATETIME NULL,
    sqs_message_id VARCHAR(100) NULL,

    PRIMARY KEY (request_id),
    UNIQUE KEY uq_outbox_campaign_customer (campaign_id, customer_id),
    CONSTRAINT fk_outbox_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No customer foreign key: unknown-customer requests can be recorded as SKIPPED.
CREATE TABLE IF NOT EXISTS sent_marketing_emails (
    campaign_id VARCHAR(64) NOT NULL,
    customer_id VARCHAR(32) NOT NULL,
    request_id VARCHAR(100) NOT NULL,
    promo_code VARCHAR(64) NOT NULL,
    recipient_email VARCHAR(254) NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('SENT', 'SKIPPED', 'FAILED') NOT NULL,
    provider VARCHAR(50) NULL,
    message_id VARCHAR(255) NULL,
    reason TEXT NULL,
    processed_at DATETIME NOT NULL,
    sent_at DATETIME NULL,

    PRIMARY KEY (campaign_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
