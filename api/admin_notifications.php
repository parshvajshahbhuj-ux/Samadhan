<?php
require_once "../db.php";
require_once "../auth.php";
require_once "../support_inquiry_sla.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
requireLogin();

if (!isSupportStaff()) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Support staff access required."]);
    exit;
}

$adminUserId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();

function adminNotificationsResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message,
    ], $extra));
    exit;
}

try {
    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        createOverdueSupportInquiryNotifications($conn);

        $unreadStmt = $conn->prepare("
            SELECT COUNT(*)
            FROM admin_notifications notification
            INNER JOIN ticket_messages message
                ON message.id = notification.ticket_message_id
            INNER JOIN tickets ticket
                ON ticket.id = message.ticket_id
            WHERE notification.admin_user_id = :admin_user_id
              AND ticket.organization_id = :organization_id
              AND notification.read_at IS NULL
        ");
        $unreadStmt->execute([
            ":admin_user_id" => $adminUserId,
            ":organization_id" => $organizationId,
        ]);

        $inquiryUnreadStmt = $conn->prepare("
            SELECT COUNT(*)
            FROM support_inquiry_notifications notification
            INNER JOIN support_inquiries inquiry
                ON inquiry.id = notification.support_inquiry_id
            WHERE notification.admin_user_id = :admin_user_id
              AND inquiry.organization_id = :organization_id
              AND notification.read_at IS NULL
        ");
        $inquiryUnreadStmt->execute([
            ":admin_user_id" => $adminUserId,
            ":organization_id" => $organizationId,
        ]);

        $overdueUnreadStmt = $conn->prepare("
            SELECT COUNT(*)
            FROM support_inquiry_overdue_notifications notification
            INNER JOIN support_inquiries inquiry
                ON inquiry.id = notification.support_inquiry_id
            WHERE notification.admin_user_id = :admin_user_id
              AND inquiry.organization_id = :organization_id
              AND notification.read_at IS NULL
        ");
        $overdueUnreadStmt->execute([
            ":admin_user_id" => $adminUserId,
            ":organization_id" => $organizationId,
        ]);

        $ticketListStmt = $conn->prepare("
            SELECT
                notification.id,
                'reply' AS notification_type,
                notification.ticket_message_id,
                notification.read_at,
                notification.created_at,
                message.ticket_id,
                message.user_name AS sender_name,
                LEFT(message.message, 220) AS message_preview,
                ticket.subject AS ticket_subject
            FROM admin_notifications notification
            INNER JOIN ticket_messages message
                ON message.id = notification.ticket_message_id
            INNER JOIN tickets ticket
                ON ticket.id = message.ticket_id
                AND ticket.organization_id = :organization_id
            WHERE notification.admin_user_id = :admin_user_id
            ORDER BY notification.read_at IS NULL DESC, notification.created_at DESC, notification.id DESC
            LIMIT 10
        ");
        $ticketListStmt->execute([
            ":admin_user_id" => $adminUserId,
            ":organization_id" => $organizationId,
        ]);

        $inquiryListStmt = $conn->prepare("
            SELECT
                notification.id,
                'inquiry' AS notification_type,
                notification.support_inquiry_id,
                notification.read_at,
                notification.created_at,
                inquiry.id AS ticket_id,
                inquiry.name AS sender_name,
                LEFT(inquiry.message, 220) AS message_preview,
                inquiry.subject AS ticket_subject
            FROM support_inquiry_notifications notification
            INNER JOIN support_inquiries inquiry
                ON inquiry.id = notification.support_inquiry_id
                AND inquiry.organization_id = :organization_id
            WHERE notification.admin_user_id = :admin_user_id
            ORDER BY notification.read_at IS NULL DESC, notification.created_at DESC, notification.id DESC
            LIMIT 10
        ");
        $inquiryListStmt->execute([
            ":admin_user_id" => $adminUserId,
            ":organization_id" => $organizationId,
        ]);

        $overdueListStmt = $conn->prepare("
            SELECT
                notification.id,
                'overdue' AS notification_type,
                notification.support_inquiry_id,
                notification.read_at,
                notification.created_at,
                inquiry.id AS ticket_id,
                inquiry.name AS sender_name,
                CONCAT(
                    'Pending beyond the 24-hour completion target. Deadline: ',
                    DATE_FORMAT(DATE_ADD(inquiry.created_at, INTERVAL 24 HOUR), '%b %e, %Y %h:%i %p')
                ) AS message_preview,
                inquiry.subject AS ticket_subject
            FROM support_inquiry_overdue_notifications notification
            INNER JOIN support_inquiries inquiry
                ON inquiry.id = notification.support_inquiry_id
                AND inquiry.organization_id = :organization_id
            WHERE notification.admin_user_id = :admin_user_id
            ORDER BY notification.read_at IS NULL DESC, notification.created_at DESC, notification.id DESC
            LIMIT 10
        ");
        $overdueListStmt->execute([
            ":admin_user_id" => $adminUserId,
            ":organization_id" => $organizationId,
        ]);

        $notifications = array_merge(
            $ticketListStmt->fetchAll(PDO::FETCH_ASSOC),
            $inquiryListStmt->fetchAll(PDO::FETCH_ASSOC),
            $overdueListStmt->fetchAll(PDO::FETCH_ASSOC)
        );
        foreach ($notifications as &$notification) {
            $notification["notification_id"] = (int)$notification["id"];
            $notification["id"] = $notification["notification_type"] . ":" . (int)$notification["id"];
        }
        unset($notification);
        usort($notifications, static function (array $left, array $right): int {
            $leftUnread = $left["read_at"] === null ? 1 : 0;
            $rightUnread = $right["read_at"] === null ? 1 : 0;
            if ($leftUnread !== $rightUnread) {
                return $rightUnread <=> $leftUnread;
            }
            $timeOrder = strcmp((string)$right["created_at"], (string)$left["created_at"]);
            return $timeOrder !== 0
                ? $timeOrder
                : ((int)$right["notification_id"] <=> (int)$left["notification_id"]);
        });
        $notifications = array_slice($notifications, 0, 10);

        adminNotificationsResponse(true, "Notifications loaded.", [
            "unread_count" => (int)$unreadStmt->fetchColumn()
                + (int)$inquiryUnreadStmt->fetchColumn()
                + (int)$overdueUnreadStmt->fetchColumn(),
            "data" => $notifications,
        ]);
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        header("Allow: GET, POST");
        adminNotificationsResponse(false, "Method not allowed.", [], 405);
    }

    $action = trim((string)($_POST["action"] ?? ""));
    if ($action === "mark_all_read") {
        $stmt = $conn->prepare("
            UPDATE admin_notifications
            SET read_at = CURRENT_TIMESTAMP
            WHERE admin_user_id = :admin_user_id
              AND read_at IS NULL
        ");
        $stmt->execute([":admin_user_id" => $adminUserId]);
        $inquiryStmt = $conn->prepare("
            UPDATE support_inquiry_notifications
            SET read_at = CURRENT_TIMESTAMP
            WHERE admin_user_id = :admin_user_id
              AND read_at IS NULL
        ");
        $inquiryStmt->execute([":admin_user_id" => $adminUserId]);
        $overdueStmt = $conn->prepare("
            UPDATE support_inquiry_overdue_notifications
            SET read_at = CURRENT_TIMESTAMP
            WHERE admin_user_id = :admin_user_id
              AND read_at IS NULL
        ");
        $overdueStmt->execute([":admin_user_id" => $adminUserId]);
        adminNotificationsResponse(true, "Notifications marked as read.");
    }

    if ($action === "mark_read") {
        $notificationId = filter_var($_POST["notification_id"] ?? null, FILTER_VALIDATE_INT);
        if (!$notificationId || $notificationId <= 0) {
            adminNotificationsResponse(false, "Invalid notification ID.", [], 400);
        }

        $notificationType = trim((string)($_POST["notification_type"] ?? "reply"));
        if (!in_array($notificationType, ["reply", "inquiry", "overdue"], true)) {
            adminNotificationsResponse(false, "Invalid notification type.", [], 400);
        }

        if ($notificationType === "inquiry" || $notificationType === "overdue") {
            $notificationTable = $notificationType === "overdue"
                ? "support_inquiry_overdue_notifications"
                : "support_inquiry_notifications";
            $stmt = $conn->prepare("
                UPDATE {$notificationTable}
                SET read_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND admin_user_id = :admin_user_id
                  AND read_at IS NULL
            ");
            $stmt->execute([
                ":id" => $notificationId,
                ":admin_user_id" => $adminUserId,
            ]);
            adminNotificationsResponse(true, "Notification marked as read.");
        }

        $stmt = $conn->prepare("
            UPDATE admin_notifications
            SET read_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND admin_user_id = :admin_user_id
              AND read_at IS NULL
        ");
        $stmt->execute([
            ":id" => $notificationId,
            ":admin_user_id" => $adminUserId,
        ]);
        adminNotificationsResponse(true, "Notification marked as read.");
    }

    adminNotificationsResponse(false, "Invalid notification action.", [], 400);
} catch (PDOException $e) {
    error_log("HelpDesk Pro admin notifications API error: " . $e->getMessage());
    adminNotificationsResponse(false, "Unable to load or update notifications.", [], 500);
}
