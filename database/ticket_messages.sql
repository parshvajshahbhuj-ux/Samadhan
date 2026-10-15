CREATE TABLE IF NOT EXISTS ticket_messages (
    id INT NOT NULL AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket_messages_ticket_created (ticket_id, created_at, id),
    KEY idx_ticket_messages_role_created (user_role, created_at),
    CONSTRAINT fk_ticket_messages_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
