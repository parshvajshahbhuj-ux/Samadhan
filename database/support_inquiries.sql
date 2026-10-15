CREATE TABLE IF NOT EXISTS support_inquiries (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    ticket_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_support_inquiries_user_created (user_id, created_at),
    KEY idx_support_inquiries_ticket (ticket_id),
    CONSTRAINT fk_support_inquiries_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_support_inquiries_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS support_inquiry_notifications (
    id INT NOT NULL AUTO_INCREMENT,
    admin_user_id INT NOT NULL,
    support_inquiry_id INT NOT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_inquiry_notification (admin_user_id, support_inquiry_id),
    KEY idx_support_inquiry_notification_unread (admin_user_id, read_at, created_at),
    CONSTRAINT fk_support_inquiry_notification_admin
        FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_support_inquiry_notification_inquiry
        FOREIGN KEY (support_inquiry_id) REFERENCES support_inquiries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS support_inquiry_messages (
    id INT NOT NULL AUTO_INCREMENT,
    support_inquiry_id INT NOT NULL,
    user_id INT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role ENUM('user', 'admin') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_support_inquiry_messages_thread (support_inquiry_id, created_at, id),
    CONSTRAINT fk_support_inquiry_messages_inquiry
        FOREIGN KEY (support_inquiry_id) REFERENCES support_inquiries (id) ON DELETE CASCADE,
    CONSTRAINT fk_support_inquiry_messages_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
