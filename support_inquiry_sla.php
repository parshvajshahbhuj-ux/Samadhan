<?php

function createOverdueSupportInquiryNotifications(PDO $conn): int
{
    $organizationId = getOrganizationId();
    if ($organizationId <= 0 && PHP_SAPI !== "cli") {
        throw new RuntimeException("A workspace is required to create overdue support request notifications.");
    }

    $organizationFilter = $organizationId > 0
        ? " AND admin_membership.organization_id = :organization_id"
        : "";
    $stmt = $conn->prepare("
        INSERT IGNORE INTO support_inquiry_overdue_notifications
            (admin_user_id, support_inquiry_id)
        SELECT admin.id, inquiry.id
        FROM users admin
        INNER JOIN organization_memberships admin_membership
            ON admin_membership.user_id = admin.id
        CROSS JOIN support_inquiries inquiry
        LEFT JOIN tickets linked_ticket
            ON linked_ticket.id = inquiry.ticket_id
            AND linked_ticket.organization_id = inquiry.organization_id
        WHERE admin_membership.role IN ('admin', 'support')
          AND admin_membership.organization_id = inquiry.organization_id
          AND inquiry.created_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)
          {$organizationFilter}
          AND (
              linked_ticket.id IS NULL
              OR linked_ticket.status NOT IN ('Resolved', 'Closed')
              OR COALESCE(
                  inquiry.completed_at,
                  CASE
                      WHEN linked_ticket.status IN ('Resolved', 'Closed') THEN linked_ticket.updated_at
                  END
              ) > DATE_ADD(inquiry.created_at, INTERVAL 24 HOUR)
          )
    ");
    $stmt->execute($organizationId > 0 ? [":organization_id" => $organizationId] : []);
    return $stmt->rowCount();
}
