CREATE TABLE organizations (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(150) NOT NULL,
    slug varchar(80) NOT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_organizations_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO organizations (name, slug)
VALUES ('Samadhan', 'samadhan');

ALTER TABLE users
    ADD COLUMN organization_id int(11) NULL AFTER id,
    ADD KEY idx_users_organization_role (organization_id, role);

UPDATE users
SET organization_id = (SELECT id FROM organizations WHERE slug = 'samadhan' LIMIT 1);

ALTER TABLE users
    MODIFY organization_id int(11) NOT NULL,
    ADD CONSTRAINT fk_users_organization
        FOREIGN KEY (organization_id) REFERENCES organizations (id);

ALTER TABLE tickets
    ADD COLUMN organization_id int(11) NULL AFTER id,
    ADD KEY idx_tickets_organization_created (organization_id, created_at),
    ADD KEY idx_tickets_organization_status (organization_id, status);

UPDATE tickets
SET organization_id = (SELECT id FROM organizations WHERE slug = 'samadhan' LIMIT 1);

ALTER TABLE tickets
    MODIFY organization_id int(11) NOT NULL,
    ADD CONSTRAINT fk_tickets_organization
        FOREIGN KEY (organization_id) REFERENCES organizations (id);

ALTER TABLE support_inquiries
    ADD COLUMN organization_id int(11) NULL AFTER id,
    ADD KEY idx_support_inquiries_organization_created (organization_id, created_at);

UPDATE support_inquiries
SET organization_id = (SELECT id FROM organizations WHERE slug = 'samadhan' LIMIT 1);

ALTER TABLE support_inquiries
    MODIFY organization_id int(11) NOT NULL,
    ADD CONSTRAINT fk_support_inquiries_organization
        FOREIGN KEY (organization_id) REFERENCES organizations (id);

ALTER TABLE activity_logs
    ADD COLUMN organization_id int(11) NULL AFTER id,
    ADD KEY idx_activity_logs_organization_created (organization_id, created_at);

UPDATE activity_logs
SET organization_id = (SELECT id FROM organizations WHERE slug = 'samadhan' LIMIT 1);

ALTER TABLE activity_logs
    MODIFY organization_id int(11) NOT NULL,
    ADD CONSTRAINT fk_activity_logs_organization
        FOREIGN KEY (organization_id) REFERENCES organizations (id);

CREATE TABLE organization_invitations (
    id int(11) NOT NULL AUTO_INCREMENT,
    organization_id int(11) NOT NULL,
    invited_by int(11) NOT NULL,
    email varchar(150) NOT NULL,
    role enum('user','admin') NOT NULL DEFAULT 'user',
    token_hash char(64) NOT NULL,
    expires_at datetime NOT NULL,
    accepted_at datetime DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_organization_invitation_token (token_hash),
    KEY idx_organization_invitation_email (organization_id, email, accepted_at),
    CONSTRAINT fk_organization_invitation_org
        FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_organization_invitation_inviter
        FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
