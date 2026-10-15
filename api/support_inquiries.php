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
$isAdmin = isSupportStaff();

function supportInquiryResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): void
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
        $action = trim((string)($_GET["action"] ?? ""));
        if ($action === "list") {
            $listSql = "
                SELECT
                    inquiry.id,
                    inquiry.name,
                    inquiry.email,
                    inquiry.subject,
                    inquiry.ticket_id,
                    inquiry.created_at,
                    COALESCE(latest.message, inquiry.message) AS latest_message,
                    latest.created_at AS latest_reply_at,
                    latest.user_role AS latest_reply_role
                FROM support_inquiries inquiry
                LEFT JOIN (
                    SELECT thread.support_inquiry_id, thread.message, thread.created_at, thread.user_role
                    FROM support_inquiry_messages thread
                    INNER JOIN (
                        SELECT support_inquiry_id, MAX(id) AS latest_id
                        FROM support_inquiry_messages
                        GROUP BY support_inquiry_id
                    ) latest_ids ON latest_ids.latest_id = thread.id
                ) latest ON latest.support_inquiry_id = inquiry.id
            ";
            $listSql .= " WHERE inquiry.organization_id = :organization_id";
            $params = [":organization_id" => $organizationId];
            if (!$isAdmin) {
                $listSql .= " AND inquiry.user_id = :user_id";
                $params[":user_id"] = $sessionUserId;
            }
            $listSql .= " ORDER BY COALESCE(latest.created_at, inquiry.created_at) DESC, inquiry.id DESC";
            $listStmt = $conn->prepare($listSql);
            $listStmt->execute($params);
            supportInquiryResponse(true, "Support requests loaded.", [
                "data" => $listStmt->fetchAll(PDO::FETCH_ASSOC),
            ]);
        }

        $inquiryId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        if (!$inquiryId || $inquiryId <= 0) {
            supportInquiryResponse(false, "Invalid support request ID.", [], 400);
        }

        $inquirySql = "
            SELECT id, user_id, name, email, subject, message, ticket_id, created_at
            FROM support_inquiries
            WHERE id = :id
              AND organization_id = :organization_id
        ";
        $inquiryParams = [
            ":id" => $inquiryId,
            ":organization_id" => $organizationId,
        ];
        if (!$isAdmin) {
            $inquirySql .= " AND user_id = :user_id";
            $inquiryParams[":user_id"] = $sessionUserId;
        }
        $inquirySql .= " LIMIT 1";
        $stmt = $conn->prepare($inquirySql);
        $stmt->execute($inquiryParams);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inquiry) {
            supportInquiryResponse(false, "Support request not found.", [], 404);
        }

        $messagesStmt = $conn->prepare("
            SELECT id, user_id, user_name, user_role, message, created_at
            FROM support_inquiry_messages
            WHERE support_inquiry_id = :support_inquiry_id
            ORDER BY created_at ASC, id ASC
        ");
        $messagesStmt->execute([":support_inquiry_id" => $inquiryId]);
        supportInquiryResponse(true, "Support request loaded.", [
            "data" => $inquiry,
            "messages" => $messagesStmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        header("Allow: GET, POST");
        supportInquiryResponse(false, "Method not allowed.", [], 405);
    }

    $action = trim((string)($_POST["action"] ?? ""));
    if ($action === "reply") {
        $csrfToken = (string)($_POST["csrf_token"] ?? "");
        $expectedToken = (string)($_SESSION["support_inquiry_csrf"] ?? "");
        if ($expectedToken === "" || !hash_equals($expectedToken, $csrfToken)) {
            supportInquiryResponse(false, "Your request could not be verified. Refresh the page and try again.", [], 400);
        }

        if (time() - (int)($_SESSION["support_inquiry_reply_last_sent"] ?? 0) < 5) {
            supportInquiryResponse(false, "Please wait a few seconds before sending another reply.", [], 429);
        }

        $inquiryId = filter_var($_POST["inquiry_id"] ?? null, FILTER_VALIDATE_INT);
        $message = trim((string)($_POST["message"] ?? ""));
        $messageLength = preg_match_all("/./us", $message);
        if (!$inquiryId || $inquiryId <= 0) {
            supportInquiryResponse(false, "Invalid support request ID.", [], 400);
        }
        if ($message === "" || $messageLength === false || $messageLength > 5000) {
            supportInquiryResponse(false, "Enter a reply of up to 5,000 characters.", [], 400);
        }

        $inquirySql = "
            SELECT id, user_id, name, email, subject
            FROM support_inquiries
            WHERE id = :id
              AND organization_id = :organization_id
        ";
        $inquiryParams = [
            ":id" => $inquiryId,
            ":organization_id" => $organizationId,
        ];
        if (!$isAdmin) {
            $inquirySql .= " AND user_id = :user_id";
            $inquiryParams[":user_id"] = $sessionUserId;
        }
        $inquirySql .= " LIMIT 1";
        $inquiryStmt = $conn->prepare($inquirySql);
        $inquiryStmt->execute($inquiryParams);
        $inquiry = $inquiryStmt->fetch(PDO::FETCH_ASSOC);
        if (!$inquiry) {
            supportInquiryResponse(false, "Support request not found.", [], 404);
        }

        $senderStmt = $conn->prepare("
            SELECT name
            FROM users
            WHERE id = :id
              AND EXISTS (
                  SELECT 1
                  FROM organization_memberships membership
                  WHERE membership.user_id = users.id
                    AND membership.organization_id = :organization_id
              )
            LIMIT 1
        ");
        $senderStmt->execute([
            ":id" => $sessionUserId,
            ":organization_id" => $organizationId,
        ]);
        $senderNameValue = $senderStmt->fetchColumn();
        $senderName = (string)($senderNameValue ?: ($_SESSION["user_name"] ?? "Samadhan"));
        $senderRole = $isAdmin ? "admin" : "user";

        $conn->beginTransaction();
        try {
            $insertMessage = $conn->prepare("
                INSERT INTO support_inquiry_messages
                    (support_inquiry_id, user_id, user_name, user_role, message)
                VALUES
                    (:support_inquiry_id, :user_id, :user_name, :user_role, :message)
            ");
            $insertMessage->execute([
                ":support_inquiry_id" => $inquiryId,
                ":user_id" => $sessionUserId,
                ":user_name" => $senderName,
                ":user_role" => $senderRole,
                ":message" => $message,
            ]);
            $messageId = (int)$conn->lastInsertId();

            if (!$isAdmin) {
                $adminsStmt = $conn->prepare("
                    SELECT id
                    FROM users
                    INNER JOIN organization_memberships membership
                        ON membership.user_id = users.id
                    WHERE membership.role IN ('admin', 'support')
                      AND membership.organization_id = :organization_id
                ");
                $adminsStmt->execute([":organization_id" => $organizationId]);
                $admins = $adminsStmt->fetchAll(PDO::FETCH_COLUMN);
                $notifyAdmin = $conn->prepare("
                    INSERT INTO support_inquiry_notifications (admin_user_id, support_inquiry_id)
                    VALUES (:admin_user_id, :support_inquiry_id)
                    ON DUPLICATE KEY UPDATE read_at = NULL, created_at = CURRENT_TIMESTAMP
                ");
                foreach ($admins as $adminId) {
                    $notifyAdmin->execute([
                        ":admin_user_id" => (int)$adminId,
                        ":support_inquiry_id" => $inquiryId,
                    ]);
                }
            } else {
                createUserNotification(
                    $conn,
                    (int)$inquiry["user_id"],
                    "inquiry_reply",
                    $messageId,
                    null,
                    $inquiryId,
                    "Support team replied to your request",
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

        $_SESSION["support_inquiry_reply_last_sent"] = time();
        $_SESSION["support_inquiry_csrf"] = bin2hex(random_bytes(32));
        $safeSender = htmlspecialchars($senderName, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
        $safeSubject = htmlspecialchars((string)$inquiry["subject"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        if ($isAdmin) {
            $emailSent = sendHelpDeskEmail(
                (string)$inquiry["email"],
                "Samadhan - Reply to support request #" . $inquiryId,
                "<h2>Samadhan support replied</h2><p>Hello "
                    . htmlspecialchars((string)$inquiry["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")
                    . ",</p><p>There is a new reply to your request <strong>{$safeSubject}</strong>:</p>"
                    . "<p>{$safeMessage}</p><p>Sign in to Samadhan to continue the conversation.</p>"
            );
        } else {
            $adminEmailsStmt = $conn->prepare("
                SELECT email
                FROM users
                INNER JOIN organization_memberships membership
                    ON membership.user_id = users.id
                WHERE membership.role IN ('admin', 'support')
                  AND membership.organization_id = :organization_id
                ORDER BY id
            ");
            $adminEmailsStmt->execute([":organization_id" => $organizationId]);
            $adminEmails = $adminEmailsStmt->fetchAll(PDO::FETCH_COLUMN);
            $emailSent = (bool)$adminEmails;
            foreach ($adminEmails as $adminEmail) {
                if (!sendHelpDeskEmail(
                    (string)$adminEmail,
                    "Samadhan - Customer reply on request #" . $inquiryId,
                    "<h2>Customer replied to a support request</h2><p><strong>From:</strong> {$safeSender} ("
                        . htmlspecialchars((string)$inquiry["email"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")
                        . ")</p><p><strong>Request:</strong> {$safeSubject}</p><p>{$safeMessage}</p>"
                        . "<p>Open Customer Requests in Samadhan to respond.</p>"
                )) {
                    $emailSent = false;
                }
            }
        }
        if (!$emailSent) {
            error_log("HelpDesk Pro inquiry reply #{$messageId} was saved, but its email notification failed.");
        }

        supportInquiryResponse(true, $emailSent
            ? "Reply sent."
            : "Reply saved, but the email notification could not be sent.", [
                "message_id" => $messageId,
                "email_sent" => $emailSent,
                "csrf_token" => $_SESSION["support_inquiry_csrf"],
            ]);
    }

    if ($action !== "create") {
        supportInquiryResponse(false, "Invalid support request action.", [], 400);
    }

    if ($isAdmin) {
        supportInquiryResponse(false, "Only user accounts can submit a support request.", [], 403);
    }

    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    $expectedToken = (string)($_SESSION["support_contact_csrf"] ?? "");
    if ($expectedToken === "" || !hash_equals($expectedToken, $csrfToken)) {
        supportInquiryResponse(false, "Your request could not be verified. Refresh the page and try again.", [], 400);
    }

    if (time() - (int)($_SESSION["support_contact_last_sent"] ?? 0) < 60) {
        supportInquiryResponse(false, "Please wait a minute before sending another support request.", [], 429);
    }

    $subject = trim((string)($_POST["subject"] ?? ""));
    $message = trim((string)($_POST["message"] ?? ""));
    $subjectLength = preg_match_all("/./us", $subject);
    $messageLength = preg_match_all("/./us", $message);
    if ($subject === "" || $subjectLength === false || $subjectLength > 150) {
        supportInquiryResponse(false, "Enter a subject of up to 150 characters.", [], 400);
    }
    if ($message === "" || $messageLength === false || $messageLength > 5000) {
        supportInquiryResponse(false, "Enter a message of up to 5,000 characters.", [], 400);
    }

    $userStmt = $conn->prepare("
        SELECT id, name, email
        FROM users
        WHERE id = :id
          AND EXISTS (
              SELECT 1
              FROM organization_memberships membership
              WHERE membership.user_id = users.id
                AND membership.organization_id = :organization_id
                AND membership.role = 'user'
          )
        LIMIT 1
    ");
    $userStmt->execute([
        ":id" => (int)($_SESSION["user_id"] ?? 0),
        ":organization_id" => $organizationId,
    ]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        supportInquiryResponse(false, "Unable to identify your user account.", [], 403);
    }

    $conn->beginTransaction();
    try {
        $insertInquiry = $conn->prepare("
            INSERT INTO support_inquiries
                (organization_id, user_id, name, email, subject, message)
            VALUES
                (:organization_id, :user_id, :name, :email, :subject, :message)
        ");
        $insertInquiry->execute([
            ":organization_id" => $organizationId,
            ":user_id" => (int)$user["id"],
            ":name" => (string)$user["name"],
            ":email" => (string)$user["email"],
            ":subject" => $subject,
            ":message" => $message,
        ]);
        $inquiryId = (int)$conn->lastInsertId();

        $adminsStmt = $conn->prepare("
            SELECT id, email
            FROM users
            INNER JOIN organization_memberships membership
                ON membership.user_id = users.id
            WHERE membership.role IN ('admin', 'support')
              AND membership.organization_id = :organization_id
        ");
        $adminsStmt->execute([":organization_id" => $organizationId]);
        $admins = $adminsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$admins) {
            throw new RuntimeException("No administrator accounts are configured.");
        }

        $notifyAdmin = $conn->prepare("
            INSERT INTO support_inquiry_notifications (admin_user_id, support_inquiry_id)
            VALUES (:admin_user_id, :support_inquiry_id)
        ");
        foreach ($admins as $admin) {
            $notifyAdmin->execute([
                ":admin_user_id" => (int)$admin["id"],
                ":support_inquiry_id" => $inquiryId,
            ]);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    $_SESSION["support_contact_last_sent"] = time();
    $_SESSION["support_contact_csrf"] = bin2hex(random_bytes(32));

    $safeName = htmlspecialchars((string)$user["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $safeEmail = htmlspecialchars((string)$user["email"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
    $adminBody = "
        <h2>New Samadhan support request</h2>
        <p><strong>From:</strong> {$safeName} ({$safeEmail})</p>
        <p><strong>Subject:</strong> {$safeSubject}</p>
        <p><strong>Message:</strong></p>
        <p>{$safeMessage}</p>
        <p>Open the administrator notifications in Samadhan to review this request.</p>
    ";
    $adminEmailSent = true;
    foreach ($admins as $admin) {
        if (!sendHelpDeskEmail(
            (string)$admin["email"],
            "Samadhan - New support request #{$inquiryId}",
            $adminBody
        )) {
            $adminEmailSent = false;
        }
    }
    if (!$adminEmailSent) {
        error_log("HelpDesk Pro support request #{$inquiryId} was saved, but admin email delivery failed.");
    }

    $confirmationBody = "
        <h2>Thank you for contacting Samadhan</h2>
        <p>Hello {$safeName},</p>
        <p>We received your support request: <strong>{$safeSubject}</strong>.</p>
        <p>Our support team will review your message and aims to respond within 24 hours.</p>
        <p>Thank you,<br>Samadhan Support Team</p>
    ";
    $confirmationSent = sendHelpDeskEmail(
        (string)$user["email"],
        "Samadhan - We received your support request",
        $confirmationBody
    );
    if (!$confirmationSent) {
        error_log("HelpDesk Pro support request confirmation email failed for request #{$inquiryId}.");
    }

    supportInquiryResponse(true, "Your support request was sent.", [
        "inquiry_id" => $inquiryId,
        "admin_email_sent" => $adminEmailSent,
        "confirmation_sent" => $confirmationSent,
        "csrf_token" => $_SESSION["support_contact_csrf"],
    ]);
} catch (PDOException $e) {
    error_log("HelpDesk Pro support inquiry API database error: " . $e->getMessage());
    supportInquiryResponse(false, "Unable to submit or load the support request.", [], 500);
} catch (Throwable $e) {
    error_log("HelpDesk Pro support inquiry processing error: " . $e->getMessage());
    supportInquiryResponse(false, "Unable to submit the support request. Please try again later.", [], 500);
}
