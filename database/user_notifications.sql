CREATE TABLE IF NOT EXISTS user_notifications (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    source_type VARCHAR(30) NOT NULL,
    source_id INT NOT NULL,
    ticket_id INT NULL,
    support_inquiry_id INT NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(255) NOT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_notification_source (user_id, source_type, source_id),
    KEY idx_user_notification_unread (user_id, read_at, created_at),
    CONSTRAINT fk_user_notification_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_notification_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_notification_inquiry
        FOREIGN KEY (support_inquiry_id) REFERENCES support_inquiries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
