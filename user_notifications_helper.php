<?php

function createUserNotification(
    PDO $conn,
    int $userId,
    string $sourceType,
    int $sourceId,
    ?int $ticketId,
    ?int $supportInquiryId,
    string $title,
    string $message
): void {
    if ($userId <= 0 || $sourceId <= 0 || !in_array($sourceType, ["ticket_reply", "inquiry_reply", "ticket_status"], true)) {
        throw new InvalidArgumentException("Invalid user notification details.");
    }

    $message = preg_replace('/\s+/u', ' ', trim($message)) ?? trim($message);
    if (mb_strlen($message, "UTF-8") > 255) {
        $message = mb_substr($message, 0, 252, "UTF-8") . "...";
    }

    $stmt = $conn->prepare("
        INSERT INTO user_notifications
            (user_id, source_type, source_id, ticket_id, support_inquiry_id, title, message)
        VALUES
            (:user_id, :source_type, :source_id, :ticket_id, :support_inquiry_id, :title, :message)
    ");
    $stmt->execute([
        ":user_id" => $userId,
        ":source_type" => $sourceType,
        ":source_id" => $sourceId,
        ":ticket_id" => $ticketId,
        ":support_inquiry_id" => $supportInquiryId,
        ":title" => mb_substr($title, 0, 120, "UTF-8"),
        ":message" => $message,
    ]);
}
