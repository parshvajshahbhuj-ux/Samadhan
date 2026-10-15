CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT NOT NULL AUTO_INCREMENT,
    admin_user_id INT NOT NULL,
    ticket_message_id INT NOT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_notifications_recipient_message (admin_user_id, ticket_message_id),
    KEY idx_admin_notifications_unread (admin_user_id, read_at, created_at),
    CONSTRAINT fk_admin_notifications_admin
        FOREIGN KEY (admin_user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_admin_notifications_message
        FOREIGN KEY (ticket_message_id) REFERENCES ticket_messages (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO admin_notifications (admin_user_id, ticket_message_id)
SELECT admin.id, message.id
FROM users admin
INNER JOIN ticket_messages message
    ON message.user_role = 'user'
WHERE admin.role = 'admin';
