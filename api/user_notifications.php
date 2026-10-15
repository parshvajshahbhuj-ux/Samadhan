<?php
require_once "../db.php";
require_once "../auth.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
requireLogin();

if (isSupportStaff()) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "User access required."]);
    exit;
}

$userId = (int)($_SESSION["user_id"] ?? 0);

function userNotificationsResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): void
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
        $unreadStmt = $conn->prepare("
            SELECT COUNT(*)
            FROM user_notifications
            WHERE user_id = :user_id
              AND read_at IS NULL
        ");
        $unreadStmt->execute([":user_id" => $userId]);

        $listStmt = $conn->prepare("
            SELECT id, source_type, ticket_id, support_inquiry_id, title, message, read_at, created_at
            FROM user_notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT 20
        ");
        $listStmt->execute([":user_id" => $userId]);
        userNotificationsResponse(true, "Support updates loaded.", [
            "unread_count" => (int)$unreadStmt->fetchColumn(),
            "data" => $listStmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        header("Allow: GET, POST");
        userNotificationsResponse(false, "Method not allowed.", [], 405);
    }

    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    $expectedToken = (string)($_SESSION["user_notifications_csrf"] ?? "");
    if ($expectedToken === "" || !hash_equals($expectedToken, $csrfToken)) {
        userNotificationsResponse(false, "Your request could not be verified. Refresh the page and try again.", [], 400);
    }

    $action = trim((string)($_POST["action"] ?? ""));
    if ($action === "mark_all_read") {
        $stmt = $conn->prepare("
            UPDATE user_notifications
            SET read_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id
              AND read_at IS NULL
        ");
        $stmt->execute([":user_id" => $userId]);
        userNotificationsResponse(true, "Support updates marked as read.");
    }

    if ($action === "mark_read") {
        $notificationId = filter_var($_POST["notification_id"] ?? null, FILTER_VALIDATE_INT);
        if (!$notificationId || $notificationId <= 0) {
            userNotificationsResponse(false, "Invalid notification ID.", [], 400);
        }
        $stmt = $conn->prepare("
            UPDATE user_notifications
            SET read_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND user_id = :user_id
              AND read_at IS NULL
        ");
        $stmt->execute([
            ":id" => $notificationId,
            ":user_id" => $userId,
        ]);
        userNotificationsResponse(true, "Support update marked as read.");
    }

    userNotificationsResponse(false, "Invalid notification action.", [], 400);
} catch (PDOException $e) {
    error_log("Samadhan user notifications API error: " . $e->getMessage());
    userNotificationsResponse(false, "Unable to load or update support updates.", [], 500);
}
