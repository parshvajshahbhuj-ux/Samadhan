CREATE TABLE IF NOT EXISTS organization_memberships (
    organization_id int(11) NOT NULL,
    user_id int(11) NOT NULL,
    role enum('user','admin') NOT NULL DEFAULT 'user',
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (organization_id, user_id),
    KEY idx_organization_memberships_user (user_id, organization_id),
    CONSTRAINT fk_organization_memberships_organization
        FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_organization_memberships_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO organization_memberships (organization_id, user_id, role)
SELECT organization_id, id, role
FROM users;
