<?php
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../activity_helper.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

requireLogin();

function reopenTicketResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message,
    ], $extra));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    reopenTicketResponse(false, "Method not allowed.", [], 405);
}

if (isSupportStaff()) {
    reopenTicketResponse(false, "Only the customer who submitted a ticket can reopen it.", [], 403);
}

$csrfToken = (string)($_POST["csrf_token"] ?? "");
$expectedToken = (string)($_SESSION["ticket_messages_csrf"] ?? "");
if ($expectedToken === "" || !hash_equals($expectedToken, $csrfToken)) {
    reopenTicketResponse(false, "Your request could not be verified. Refresh the page and try again.", [], 400);
}

$ticketId = filter_input(INPUT_POST, "ticket_id", FILTER_VALIDATE_INT);
$reason = trim((string)($_POST["reason"] ?? ""));
$reasonLength = preg_match_all("/./us", $reason);
if (!$ticketId || $ticketId <= 0) {
    reopenTicketResponse(false, "Invalid ticket ID.", [], 400);
}
if ($reasonLength === false || $reasonLength < 5 || $reasonLength > 1000) {
    reopenTicketResponse(false, "Explain what still needs attention in 5 to 1,000 characters.", [], 400);
}

$userId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();

try {
    $conn->beginTransaction();

    $ticketStmt = $conn->prepare("
        SELECT id, user_id, status
        FROM tickets
        WHERE id = :ticket_id
          AND organization_id = :organization_id
          AND user_id = :user_id
        LIMIT 1
        FOR UPDATE
    ");
    $ticketStmt->execute([
        ":ticket_id" => $ticketId,
        ":organization_id" => $organizationId,
        ":user_id" => $userId,
    ]);
    $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        $conn->rollBack();
        reopenTicketResponse(false, "Ticket not found or access denied.", [], 404);
    }

    if ((string)$ticket["status"] !== "Resolved") {
        $conn->rollBack();
        reopenTicketResponse(false, "Only resolved tickets can be reopened.", [], 409);
    }

    $resolutionStmt = $conn->prepare("
        SELECT description,
               created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)
                   AND created_at <= CURRENT_TIMESTAMP AS within_reopen_window
        FROM activity_logs
        WHERE ticket_id = :ticket_id
          AND organization_id = :organization_id
          AND action = 'STATUS_CHANGE'
          AND description LIKE :resolved_transition
          AND NOT EXISTS (
              SELECT 1
              FROM activity_logs AS later_status_log
              WHERE later_status_log.ticket_id = activity_logs.ticket_id
                AND later_status_log.organization_id = activity_logs.organization_id
                AND later_status_log.action = 'STATUS_CHANGE'
                AND later_status_log.id > activity_logs.id
          )
        ORDER BY id DESC
        LIMIT 1
    ");
    $resolutionStmt->execute([
        ":ticket_id" => $ticketId,
        ":organization_id" => $organizationId,
        ":resolved_transition" => "Ticket #" . $ticketId . " status changed from % to Resolved.",
    ]);
    $resolution = $resolutionStmt->fetch(PDO::FETCH_ASSOC);
    $resolutionPattern = '/^Ticket #' . $ticketId
        . ' status changed from (Open|In Progress|Closed) to Resolved\\.$/D';
    if (
        !$resolution ||
        !preg_match($resolutionPattern, (string)$resolution["description"]) ||
        (int)$resolution["within_reopen_window"] !== 1
    ) {
        $conn->rollBack();
        reopenTicketResponse(false, "This ticket is outside the 7-day reopening period.", [], 409);
    }

    $updateStmt = $conn->prepare("
        UPDATE tickets
        SET status = 'Open'
        WHERE id = :ticket_id
          AND organization_id = :organization_id
          AND user_id = :user_id
          AND status = 'Resolved'
    ");
    $updateStmt->execute([
        ":ticket_id" => $ticketId,
        ":organization_id" => $organizationId,
        ":user_id" => $userId,
    ]);
    if ($updateStmt->rowCount() !== 1) {
        $conn->rollBack();
        reopenTicketResponse(false, "The ticket status changed before it could be reopened. Refresh and try again.", [], 409);
    }

    $inquiryStmt = $conn->prepare("
        UPDATE support_inquiries
        SET completed_at = NULL
        WHERE ticket_id = :ticket_id
          AND organization_id = :organization_id
    ");
    $inquiryStmt->execute([
        ":ticket_id" => $ticketId,
        ":organization_id" => $organizationId,
    ]);

    logActivity(
        $conn,
        "STATUS_CHANGE",
        "Ticket #{$ticketId} status changed from Resolved to Open.",
        $ticketId
    );

    $message = "Reopen request: " . $reason;
    $messageStmt = $conn->prepare("
        INSERT INTO ticket_messages
            (ticket_id, user_id, user_name, user_role, message)
        VALUES
            (:ticket_id, :user_id, :user_name, 'user', :message)
    ");
    $messageStmt->execute([
        ":ticket_id" => $ticketId,
        ":user_id" => $userId,
        ":user_name" => (string)($_SESSION["user_name"] ?? "Customer"),
        ":message" => $message,
    ]);
    $messageId = (int)$conn->lastInsertId();

    $adminsStmt = $conn->prepare("
        SELECT user_id
        FROM organization_memberships
        WHERE organization_id = :organization_id
          AND role IN ('admin', 'support')
    ");
    $adminsStmt->execute([":organization_id" => $organizationId]);
    $notificationStmt = $conn->prepare("
        INSERT INTO admin_notifications (admin_user_id, ticket_message_id)
        VALUES (:admin_user_id, :ticket_message_id)
    ");
    foreach ($adminsStmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
        $notificationStmt->execute([
            ":admin_user_id" => (int)$adminId,
            ":ticket_message_id" => $messageId,
        ]);
    }

    $conn->commit();
    reopenTicketResponse(true, "Ticket reopened. Support has been notified.", [
        "ticket_id" => (int)$ticketId,
        "status" => "Open",
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("HelpDesk Pro ticket reopen failed for ticket " . (int)$ticketId . ": " . $e->getMessage());
    reopenTicketResponse(false, "Unable to reopen the ticket right now. Please try again.", [], 500);
}
