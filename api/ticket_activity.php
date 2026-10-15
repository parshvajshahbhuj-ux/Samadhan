<?php

require_once "../db.php";
require_once "../auth.php";

header("Content-Type: application/json");

requireLogin();

$sessionUserId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isAdmin = isSupportStaff();


/*
|--------------------------------------------------------------------------
| GET REQUEST ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET TICKET ID
|--------------------------------------------------------------------------
*/

$ticketId = (int)($_GET["ticket_id"] ?? 0);


if ($ticketId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid ticket ID."
    ]);

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | CHECK TICKET EXISTS + OWNERSHIP
    |--------------------------------------------------------------------------
    */

    if ($isAdmin) {

        $ticketStmt = $conn->prepare("
            SELECT
                id,
                user_id
            FROM tickets
            WHERE id = :id
              AND organization_id = :organization_id
            LIMIT 1
        ");

        $ticketStmt->execute([
            ":id" => $ticketId,
            ":organization_id" => $organizationId
        ]);

    } else {

        $ticketStmt = $conn->prepare("
            SELECT
                id,
                user_id
            FROM tickets
            WHERE id = :id
            AND organization_id = :organization_id
            AND user_id = :user_id
            LIMIT 1
        ");

        $ticketStmt->execute([
            ":id" => $ticketId,
            ":organization_id" => $organizationId,
            ":user_id" => $sessionUserId
        ]);
    }


    $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | TICKET NOT FOUND / ACCESS DENIED
    |--------------------------------------------------------------------------
    */

    if (!$ticket) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Ticket not found or access denied."
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | GET ACTIVITY
    |--------------------------------------------------------------------------
    */

    $activityStmt = $conn->prepare("
        SELECT
            id,
            user_id,
            user_name,
            user_role,
            ticket_id,
            action,
            description,
            created_at

        FROM activity_logs

        WHERE ticket_id = :ticket_id
          AND organization_id = :organization_id

        ORDER BY created_at ASC, id ASC
    ");


    $activityStmt->execute([
        ":ticket_id" => $ticketId,
        ":organization_id" => $organizationId
    ]);


    $activities = $activityStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "ticket_id" => $ticketId,

        "data" => $activities,

        "count" => count($activities)

    ]);

    exit;


} catch (PDOException $e) {
    error_log("HelpDesk Pro ticket activity API error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" => "Database error."

    ]);

    exit;
}
?>