<?php
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../auth.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

requireLogin();

function ticketFeedbackResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message,
    ], $extra));
    exit;
}

$userId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$requestMethod = (string)($_SERVER["REQUEST_METHOD"] ?? "");

if ($requestMethod !== "GET" && $requestMethod !== "POST") {
    header("Allow: GET, POST");
    ticketFeedbackResponse(false, "Method not allowed.", [], 405);
}

$ticketId = filter_var(
    $requestMethod === "GET"
        ? ($_GET["ticket_id"] ?? null)
        : ($_POST["ticket_id"] ?? null),
    FILTER_VALIDATE_INT
);

if (isSupportStaff()) {
    ticketFeedbackResponse(false, "Customer feedback is available to ticket owners only.", [], 403);
}

if (!$ticketId || $ticketId <= 0 || $userId <= 0) {
    ticketFeedbackResponse(false, "Invalid ticket or account.", [], 400);
}

try {
    $ticketStmt = $conn->prepare("
        SELECT id, status
        FROM tickets
        WHERE id = :ticket_id
          AND organization_id = :organization_id
          AND user_id = :user_id
        LIMIT 1
    ");
    $ticketStmt->execute([
        ":ticket_id" => $ticketId,
        ":organization_id" => $organizationId,
        ":user_id" => $userId,
    ]);
    $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        ticketFeedbackResponse(false, "Ticket not found or access denied.", [], 404);
    }

    if ($requestMethod === "GET") {
        $feedbackStmt = $conn->prepare("
            SELECT rating, comment, created_at, updated_at
            FROM ticket_feedback
            WHERE ticket_id = :ticket_id
              AND user_id = :user_id
            LIMIT 1
        ");
        $feedbackStmt->execute([
            ":ticket_id" => $ticketId,
            ":user_id" => $userId,
        ]);

        ticketFeedbackResponse(true, "Ticket feedback loaded.", [
            "eligible" => in_array((string)$ticket["status"], ["Resolved", "Closed"], true),
            "data" => $feedbackStmt->fetch(PDO::FETCH_ASSOC) ?: null,
        ]);
    }

    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    $expectedToken = (string)($_SESSION["user_notifications_csrf"] ?? "");
    if ($expectedToken === "" || !hash_equals($expectedToken, $csrfToken)) {
        ticketFeedbackResponse(false, "Your request could not be verified. Refresh the page and try again.", [], 400);
    }

    if (!in_array((string)$ticket["status"], ["Resolved", "Closed"], true)) {
        ticketFeedbackResponse(false, "You can rate a ticket after it has been resolved or closed.", [], 409);
    }

    $rating = filter_var($_POST["rating"] ?? null, FILTER_VALIDATE_INT);
    if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
        ticketFeedbackResponse(false, "Choose a rating from 1 to 5 stars.", [], 400);
    }

    $comment = trim((string)($_POST["comment"] ?? ""));
    $commentLength = preg_match_all("/./us", $comment);
    if ($commentLength === false || $commentLength > 1000) {
        ticketFeedbackResponse(false, "Feedback comments cannot exceed 1,000 characters.", [], 400);
    }

    $saveStmt = $conn->prepare("
        INSERT INTO ticket_feedback (ticket_id, user_id, rating, comment)
        VALUES (:ticket_id, :user_id, :rating, :comment)
        ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            comment = VALUES(comment),
            updated_at = CURRENT_TIMESTAMP
    ");
    $saveStmt->execute([
        ":ticket_id" => $ticketId,
        ":user_id" => $userId,
        ":rating" => $rating,
        ":comment" => $comment === "" ? null : $comment,
    ]);

    ticketFeedbackResponse(true, "Thank you for sharing your feedback.", [
        "data" => [
            "rating" => $rating,
            "comment" => $comment,
        ],
    ]);
} catch (Throwable $e) {
    error_log("HelpDesk Pro ticket feedback error for ticket " . (int)$ticketId . ": " . $e->getMessage());
    ticketFeedbackResponse(false, "Unable to save ticket feedback right now.", [], 500);
}
