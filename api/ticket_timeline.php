<?php

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../auth.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit;
}

$ticketId = filter_var($_GET["ticket_id"] ?? null, FILTER_VALIDATE_INT);
$userId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isAdmin = isSupportStaff();

if (!$ticketId || $ticketId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid ticket ID."]);
    exit;
}

try {
    $ticketSql = "SELECT id, status, created_at FROM tickets WHERE id = :ticket_id AND organization_id = :organization_id";
    $ticketParams = [
        ":ticket_id" => $ticketId,
        ":organization_id" => $organizationId,
    ];
    if (!$isAdmin) {
        $ticketSql .= " AND user_id = :user_id";
        $ticketParams[":user_id"] = $userId;
    }

    $ticketStmt = $conn->prepare($ticketSql . " LIMIT 1");
    $ticketStmt->execute($ticketParams);
    $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Ticket not found or access denied."]);
        exit;
    }

    $events = [[
        "type" => "created",
        "title" => "Request submitted",
        "detail" => "",
        "actor" => isSupportStaff() ? "Customer" : "You",
        "created_at" => (string)$ticket["created_at"],
        "sort_id" => 0,
    ]];

    $statusStmt = $conn->prepare("
        SELECT id, user_role, description, created_at
        FROM activity_logs
        WHERE ticket_id = :ticket_id
          AND organization_id = :organization_id
          AND action = 'STATUS_CHANGE'
        ORDER BY created_at ASC, id ASC
    ");
    $statusStmt->execute([
        ":ticket_id" => $ticketId,
        ":organization_id" => $organizationId,
    ]);
    $validStatuses = ["Open", "In Progress", "Resolved", "Closed"];
    $finalStatus = (string)$ticket["status"];
    $completionEvent = null;
    $statusPattern = '/^Ticket #' . $ticketId
        . ' status changed from (Open|In Progress|Resolved|Closed) to (Open|In Progress|Resolved|Closed)\.$/D';

    foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $activity) {
        if (!preg_match($statusPattern, (string)$activity["description"], $matches)) {
            continue;
        }

        $fromStatus = $matches[1];
        $toStatus = $matches[2];
        if (!in_array($fromStatus, $validStatuses, true) || !in_array($toStatus, $validStatuses, true)) {
            continue;
        }

        $activityActor = in_array((string)$activity["user_role"], ["admin", "support"], true)
            ? "Support team"
            : ($isAdmin ? "Customer" : "You");

        $events[] = [
            "type" => "status",
            "title" => "Status changed",
            "detail" => $fromStatus . " → " . $toStatus,
            "actor" => $activityActor,
            "created_at" => (string)$activity["created_at"],
            "sort_id" => (int)$activity["id"],
        ];

        if (
            in_array($finalStatus, ["Resolved", "Closed"], true) &&
            $toStatus === $finalStatus
        ) {
            $completionEvent = [
                "type" => "completed",
                "title" => "Ticket completed",
                "detail" => "Final status: " . $finalStatus,
                "actor" => $activityActor,
                "created_at" => (string)$activity["created_at"],
                "sort_id" => (int)$activity["id"],
            ];
        }
    }

    $messageStmt = $conn->prepare("
        SELECT id, user_role, message, created_at
        FROM ticket_messages
        WHERE ticket_id = :ticket_id
        ORDER BY created_at ASC, id ASC
    ");
    $messageStmt->execute([":ticket_id" => $ticketId]);
    foreach ($messageStmt->fetchAll(PDO::FETCH_ASSOC) as $message) {
        $isSupportReply = in_array((string)$message["user_role"], ["admin", "support"], true);
        $events[] = [
            "type" => "reply",
            "title" => $isSupportReply
                ? "Support team replied"
                : ($isAdmin ? "Customer replied" : "Your reply"),
            "detail" => (string)$message["message"],
            "actor" => $isSupportReply ? "Support team" : ($isAdmin ? "Customer" : "You"),
            "created_at" => (string)$message["created_at"],
            "sort_id" => (int)$message["id"],
        ];
    }

    if ($completionEvent !== null) {
        $events[] = $completionEvent;
    }

    usort($events, static function (array $left, array $right): int {
        $leftIsCompletion = $left["type"] === "completed";
        $rightIsCompletion = $right["type"] === "completed";
        if ($leftIsCompletion !== $rightIsCompletion) {
            return $leftIsCompletion ? 1 : -1;
        }

        $timeOrder = strcmp((string)$left["created_at"], (string)$right["created_at"]);
        return $timeOrder !== 0 ? $timeOrder : ((int)$left["sort_id"] <=> (int)$right["sort_id"]);
    });

    foreach ($events as &$event) {
        unset($event["sort_id"]);
    }
    unset($event);

    echo json_encode([
        "success" => true,
        "ticket_id" => $ticketId,
        "data" => $events,
    ]);
} catch (Throwable $e) {
    error_log("Samadhan ticket timeline error for ticket " . $ticketId . ": " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to load this ticket timeline right now."]);
}
