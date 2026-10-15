ALTER TABLE organization_memberships
    ADD COLUMN access_code_hash char(64) DEFAULT NULL AFTER role,
    ADD UNIQUE KEY uq_organization_memberships_access_code (access_code_hash);

ALTER TABLE organization_invitations
    ADD COLUMN access_code_hash char(64) DEFAULT NULL AFTER token_hash,
    ADD UNIQUE KEY uq_organization_invitation_access_code (access_code_hash);
