ALTER TABLE support_inquiries
    ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER ticket_id;

UPDATE support_inquiries inquiry
INNER JOIN tickets ticket
    ON ticket.id = inquiry.ticket_id
LEFT JOIN (
    SELECT
        ticket_id,
        MAX(CASE
            WHEN description LIKE '% to Resolved.'
                OR description LIKE '% to Closed.'
            THEN created_at
        END) AS completed_at
    FROM activity_logs
    WHERE action = 'STATUS_CHANGE'
    GROUP BY ticket_id
) status_events
    ON status_events.ticket_id = ticket.id
SET inquiry.completed_at = COALESCE(status_events.completed_at, ticket.updated_at)
WHERE ticket.status IN ('Resolved', 'Closed')
  AND inquiry.completed_at IS NULL;

CREATE TABLE IF NOT EXISTS support_inquiry_overdue_notifications (
    id INT NOT NULL AUTO_INCREMENT,
    admin_user_id INT NOT NULL,
    support_inquiry_id INT NOT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_inquiry_overdue_notification (admin_user_id, support_inquiry_id),
    KEY idx_support_inquiry_overdue_unread (admin_user_id, read_at, created_at),
    CONSTRAINT fk_support_inquiry_overdue_admin
        FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_support_inquiry_overdue_inquiry
        FOREIGN KEY (support_inquiry_id) REFERENCES support_inquiries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
