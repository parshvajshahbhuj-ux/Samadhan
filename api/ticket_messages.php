
<?php
require_once "../db.php";
require_once "../auth.php";
require_once "../email_helper.php";
require_once "../user_notifications_helper.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

requireLogin();

$sessionUserId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isSupportStaff = isSupportStaff();

function ticketMessagesResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message,
    ], $extra));
    exit;
}

function findAccessibleMessageTicket(PDO $conn, int $ticketId, int $userId, int $organizationId, bool $isSupportStaff): ?array
{
    if ($isSupportStaff) {
        $stmt = $conn->prepare("
            SELECT id, user_id, assigned_to_user_id, name, email, subject
            FROM tickets
            WHERE id = :ticket_id
              AND organization_id = :organization_id
            LIMIT 1
        ");
        $stmt->execute([
            ":ticket_id" => $ticketId,
            ":organization_id" => $organizationId,
        ]);
    } else {
        $stmt = $conn->prepare("
            SELECT id, user_id, assigned_to_user_id, name, email, subject
            FROM tickets
            WHERE id = :ticket_id
              AND organization_id = :organization_id
              AND user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            ":ticket_id" => $ticketId,
            ":organization_id" => $organizationId,
            ":user_id" => $userId,
        ]);
    }

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    return $ticket ?: null;
}

function notifyTicketMessage(PDO $conn, array $ticket, string $message, int $organizationId, bool $isSupportStaff): bool
{
    $safeSubject = htmlspecialchars((string)$ticket["subject"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
    $replyingName = htmlspecialchars((string)($_SESSION["user_name"] ?? "Samadhan user"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $body = "
        <div style=\"font-family:Arial,sans-serif;color:#172033;line-height:1.6\">
            <h2>New reply on ticket #" . (int)$ticket["id"] . "</h2>
            <p><strong>" . $safeSubject . "</strong></p>
            <p>" . $safeMessage . "</p>
            <p>Reply from: " . $replyingName . "</p>
            <p>Sign in to Samadhan to view the conversation and reply.</p>
        </div>
    ";

    try {
        if ($isSupportStaff) {
            return sendHelpDeskEmail(
                (string)$ticket["email"],
                "Samadhan - New reply on ticket #" . (int)$ticket["id"],
                $body
            );
        }

        $adminStmt = $conn->prepare("
            SELECT DISTINCT users.email
            FROM users
            INNER JOIN organization_memberships membership
                ON membership.user_id = users.id
            WHERE membership.role IN ('admin', 'support')
              AND membership.organization_id = :organization_id
            ORDER BY users.id
        ");
        $adminStmt->execute([":organization_id" => $organizationId]);
        $adminEmails = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$adminEmails) {
            error_log("HelpDesk Pro message notification could not find an administrator recipient for ticket " . (int)$ticket["id"]);
            return false;
        }

        $sentToAll = true;
        foreach ($adminEmails as $adminEmail) {
            if (!sendHelpDeskEmail(
                (string)$adminEmail,
                "Samadhan - New customer reply on ticket #" . (int)$ticket["id"],
                $body
            )) {
                $sentToAll = false;
            }
        }
        return $sentToAll;
    } catch (Throwable $e) {
        error_log("HelpDesk Pro ticket message notification failed for ticket " . (int)$ticket["id"] . ": " . $e->getMessage());
        return false;
    }
}

$ticketId = filter_input(INPUT_POST, "ticket_id", FILTER_VALIDATE_INT);
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $ticketId = filter_input(INPUT_GET, "ticket_id", FILTER_VALIDATE_INT);
} elseif ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: GET, POST");
    ticketMessagesResponse(false, "Method not allowed.", [], 405);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    $expectedToken = (string)($_SESSION["ticket_messages_csrf"] ?? "");
    if ($expectedToken === "" || !hash_equals($expectedToken, $csrfToken)) {
        ticketMessagesResponse(false, "Your request could not be verified. Refresh the page and try again.", [], 400);
    }
}

if (!$ticketId || $ticketId <= 0) {
    ticketMessagesResponse(false, "Invalid ticket ID.", [], 400);
}

try {
    $ticket = findAccessibleMessageTicket(
        $conn,
        $ticketId,
        $sessionUserId,
        $organizationId,
        $isSupportStaff
    );
    if (!$ticket) {
        ticketMessagesResponse(false, "Ticket not found or access denied.", [], 404);
    }

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $stmt = $conn->prepare("
            SELECT id, user_id, user_name, user_role, message, created_at
            FROM ticket_messages
            WHERE ticket_id = :ticket_id
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([":ticket_id" => $ticketId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ticketMessagesResponse(true, "Conversation loaded.", [
            "ticket_id" => $ticketId,
            "data" => $messages,
            "count" => count($messages),
        ]);
    }

    $message = trim((string)($_POST["message"] ?? ""));
    if ($message === "" || !preg_match("//u", $message)) {
        ticketMessagesResponse(false, "Enter a valid reply.", [], 400);
    }

    $characterCount = preg_match_all("/./us", $message);
    if ($characterCount === false || $characterCount > 5000) {
        ticketMessagesResponse(false, "Replies cannot exceed 5,000 characters.", [], 400);
    }

    $conn->beginTransaction();
    try {
        $insertStmt = $conn->prepare("
            INSERT INTO ticket_messages
                (ticket_id, user_id, user_name, user_role, message)
            VALUES
                (:ticket_id, :user_id, :user_name, :user_role, :message)
        ");
        $insertStmt->execute([
            ":ticket_id" => $ticketId,
            ":user_id" => $sessionUserId,
            ":user_name" => (string)($_SESSION["user_name"] ?? "Samadhan user"),
            ":user_role" => $isSupportStaff ? ($_SESSION["role"] ?? "admin") : "user",
            ":message" => $message,
        ]);
        $messageId = (int)$conn->lastInsertId();

        if (!$isSupportStaff) {
            $adminsStmt = $conn->prepare("
                SELECT users.id
                FROM users
                INNER JOIN organization_memberships membership
                    ON membership.user_id = users.id
                    AND membership.organization_id = users.organization_id
                WHERE membership.organization_id = :organization_id
                  AND membership.role IN ('admin', 'support')
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
        } elseif (!empty($ticket["user_id"])) {
            createUserNotification(
                $conn,
                (int)$ticket["user_id"],
                "ticket_reply",
                $messageId,
                $ticketId,
                null,
                "Support team replied to ticket #" . $ticketId,
                $message
            );
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    $notificationSent = notifyTicketMessage($conn, $ticket, $message, $organizationId, $isSupportStaff);
    if (!$notificationSent) {
        error_log("HelpDesk Pro reply saved, but email notification was not delivered for ticket " . $ticketId . ".");
    }

    ticketMessagesResponse(true, $notificationSent
        ? "Reply posted and email notification sent."
        : "Reply posted, but the email notification could not be sent.", [
            "ticket_id" => $ticketId,
            "message_id" => $messageId,
            "email_sent" => $notificationSent,
        ]);
} catch (PDOException $e) {
    error_log("HelpDesk Pro ticket messages API database error: " . $e->getMessage());
    ticketMessagesResponse(false, "Unable to load or save the conversation.", [], 500);
}
