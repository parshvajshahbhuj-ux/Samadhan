ALTER TABLE users
    MODIFY role enum('user','admin','support') NOT NULL DEFAULT 'user';

ALTER TABLE organization_memberships
    MODIFY role enum('user','admin','support') NOT NULL DEFAULT 'user';

ALTER TABLE organization_invitations
    MODIFY role enum('user','admin','support') NOT NULL DEFAULT 'user';

UPDATE organization_memberships AS membership
LEFT JOIN organization_memberships AS earlier_admin
    ON earlier_admin.organization_id = membership.organization_id
    AND earlier_admin.role = 'admin'
    AND (
        earlier_admin.created_at < membership.created_at
        OR (
            earlier_admin.created_at = membership.created_at
            AND earlier_admin.user_id < membership.user_id
        )
    )
SET membership.role = 'support'
WHERE membership.role = 'admin'
    AND earlier_admin.user_id IS NOT NULL;

UPDATE organization_invitations
SET role = 'support'
WHERE role = 'admin';

UPDATE users
INNER JOIN organization_memberships
    ON organization_memberships.user_id = users.id
    AND organization_memberships.organization_id = users.organization_id
SET users.role = organization_memberships.role;

UPDATE tickets
INNER JOIN organization_memberships AS assignee_membership
    ON assignee_membership.user_id = tickets.assigned_to_user_id
    AND assignee_membership.organization_id = tickets.organization_id
SET tickets.assigned_to_user_id = NULL
WHERE assignee_membership.role = 'admin';
