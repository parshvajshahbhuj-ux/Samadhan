ALTER TABLE tickets
    ADD COLUMN assigned_to_user_id int(11) DEFAULT NULL AFTER user_id,
    ADD KEY idx_tickets_assigned_to (organization_id, assigned_to_user_id, status),
    ADD CONSTRAINT fk_tickets_assigned_to_user
        FOREIGN KEY (assigned_to_user_id) REFERENCES users (id) ON DELETE SET NULL;
