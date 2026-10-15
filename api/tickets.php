<?php



require_once "../db.php";

require_once "../auth.php";

require_once "../email_helper.php";

require_once "../activity_helper.php";

require_once "../user_notifications_helper.php";

require_once "../ticket_receipt_pdf.php";


header("Content-Type: application/json");
header("Cache-Control: no-store");



requireLogin();



$sessionUserId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isSupportStaff = isSupportStaff();
$isMainAdmin = isAdmin();

function addTicketSlaData(array $ticket, DateTimeImmutable $now): array
{
    $targets = [
        "Critical" => ["response" => 1, "resolution" => 4],
        "High" => ["response" => 4, "resolution" => 24],
        "Medium" => ["response" => 24, "resolution" => 72],
        "Low" => ["response" => 48, "resolution" => 120],
    ];

    $priority = $ticket["priority"] ?? "";
    if (!isset($targets[$priority])) {
        $ticket["response_sla"] = ["state" => "unavailable"];
        $ticket["resolution_sla"] = ["state" => "unavailable"];
        return $ticket;
    }

    try {
        $createdAt = new DateTimeImmutable((string)$ticket["created_at"]);
        $responseDueAt = $createdAt->modify("+" . $targets[$priority]["response"] . " hours");
        $resolutionDueAt = $createdAt->modify("+" . $targets[$priority]["resolution"] . " hours");
        $status = $ticket["status"] ?? "";
        $isTerminal = in_array($status, ["Resolved", "Closed"], true);

        $responseAt = !empty($ticket["first_response_at"])
            ? new DateTimeImmutable((string)$ticket["first_response_at"])
            : null;
        $resolvedAt = !empty($ticket["last_resolved_at"])
            ? new DateTimeImmutable((string)$ticket["last_resolved_at"])
            : null;

        if ($responseAt !== null) {
            $responseState = $responseAt <= $responseDueAt ? "met" : "breached";
        } else {
            $responseState = $now > $responseDueAt ? "overdue" : "due";
        }

        if ($isTerminal) {
            $resolutionState = $resolvedAt === null
                ? "unavailable"
                : ($resolvedAt <= $resolutionDueAt ? "met" : "breached");
        } else {
            $resolutionState = $now > $resolutionDueAt ? "overdue" : "due";
        }

        $ticket["response_sla"] = [
            "state" => $responseState,
            "due_at" => $responseDueAt->format("Y-m-d H:i:s"),
            "target_hours" => $targets[$priority]["response"],
        ];
        $ticket["resolution_sla"] = [
            "state" => $resolutionState,
            "due_at" => $resolutionDueAt->format("Y-m-d H:i:s"),
            "target_hours" => $targets[$priority]["resolution"],
        ];
    } catch (Throwable $e) {
        error_log("HelpDesk Pro SLA calculation error for ticket " . ($ticket["id"] ?? "unknown") . ": " . $e->getMessage());
        $ticket["response_sla"] = ["state" => "unavailable"];
        $ticket["resolution_sla"] = ["state" => "unavailable"];
    }

    return $ticket;
}


// =========================================================

// GET

// =========================================================



if ($_SERVER["REQUEST_METHOD"] === "GET") {



    try {



        // =====================================================

        // SINGLE TICKET

        // =====================================================



        if (

            isset($_GET["id"]) &&

            $_GET["id"] !== ""

        ) {



            $id = (int)$_GET["id"];



            if ($id <= 0) {



                echo json_encode([

                    "success" => false,

                    "message" => "Invalid ticket ID."

                ]);



                exit;

            }





            // -------------------------------------------------

            // ADMIN

            // -------------------------------------------------



            if ($isSupportStaff) {



                $stmt = $conn->prepare("



                    SELECT

                        id,

                        user_id,

                        name,

                        email,

                        phone,
                        subject,

                        category,

                        priority,

                        status,
                        description,

                        created_at,

                        updated_at,
                        CASE
                            WHEN tickets.status = 'Resolved'
                             AND EXISTS (
                                SELECT 1
                                FROM activity_logs AS resolution_log
                                WHERE resolution_log.ticket_id = tickets.id
                                  AND resolution_log.organization_id = tickets.organization_id
                                  AND resolution_log.action = 'STATUS_CHANGE'
                                  AND resolution_log.description IN (
                                      CONCAT('Ticket #', tickets.id, ' status changed from Open to Resolved.'),
                                      CONCAT('Ticket #', tickets.id, ' status changed from In Progress to Resolved.'),
                                      CONCAT('Ticket #', tickets.id, ' status changed from Closed to Resolved.')
                                  )
                                  AND resolution_log.created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)
                                  AND resolution_log.created_at <= CURRENT_TIMESTAMP
                                  AND NOT EXISTS (
                                      SELECT 1
                                      FROM activity_logs AS later_status_log
                                      WHERE later_status_log.ticket_id = tickets.id
                                        AND later_status_log.organization_id = tickets.organization_id
                                        AND later_status_log.action = 'STATUS_CHANGE'
                                        AND later_status_log.id > resolution_log.id
                                  )
                             )
                            THEN 1 ELSE 0
                        END AS can_reopen



                    FROM tickets



                    WHERE id = :id
                      AND organization_id = :organization_id



                    LIMIT 1



                ");



                $singleTicketParams = [
                    ":id" => $id,
                    ":organization_id" => $organizationId,
                ];
                $stmt->execute($singleTicketParams);



            }



            // -------------------------------------------------

            // NORMAL USER

            // -------------------------------------------------



            else {



                $stmt = $conn->prepare("



                    SELECT

                        id,

                        user_id,

                        name,

                        email,

                        phone,
                        subject,

                        category,

                        priority,

                        status,

                        description,

                        created_at,

                        updated_at,
                        CASE
                            WHEN tickets.status = 'Resolved'
                             AND EXISTS (
                                SELECT 1
                                FROM activity_logs AS resolution_log
                                WHERE resolution_log.ticket_id = tickets.id
                                  AND resolution_log.organization_id = tickets.organization_id
                                  AND resolution_log.action = 'STATUS_CHANGE'
                                  AND resolution_log.description IN (
                                      CONCAT('Ticket #', tickets.id, ' status changed from Open to Resolved.'),
                                      CONCAT('Ticket #', tickets.id, ' status changed from In Progress to Resolved.'),
                                      CONCAT('Ticket #', tickets.id, ' status changed from Closed to Resolved.')
                                  )
                                  AND resolution_log.created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)
                                  AND resolution_log.created_at <= CURRENT_TIMESTAMP
                                  AND NOT EXISTS (
                                      SELECT 1
                                      FROM activity_logs AS later_status_log
                                      WHERE later_status_log.ticket_id = tickets.id
                                        AND later_status_log.organization_id = tickets.organization_id
                                        AND later_status_log.action = 'STATUS_CHANGE'
                                        AND later_status_log.id > resolution_log.id
                                  )
                             )
                            THEN 1 ELSE 0
                        END AS can_reopen



                    FROM tickets



                    WHERE id = :id
                    AND organization_id = :organization_id
                    AND user_id = :user_id



                    LIMIT 1



                ");



                $stmt->execute([

                    ":id" => $id,
                    ":organization_id" => $organizationId,
                    ":user_id" => $sessionUserId

                ]);

            }





            $ticket =

                $stmt->fetch(PDO::FETCH_ASSOC);





            if (!$ticket) {



                echo json_encode([

                    "success" => false,

                    "message" => "Ticket not found."

                ]);



                exit;

            }





            echo json_encode([

                "success" => true,

                "data" => $ticket

            ]);



            exit;

        }





        // =====================================================

        // SEARCH / FILTER

        // =====================================================



        $search =

            trim($_GET["search"] ?? "");



        $status =

            trim($_GET["status"] ?? "");



        $priority =

            trim($_GET["priority"] ?? "");

        $rawAssigneeFilter = $_GET["assigned_to"] ?? "";
        if (!is_string($rawAssigneeFilter)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid assignee filter."]);
            exit;
        }
        $assigneeFilter = trim($rawAssigneeFilter);
        if ($isSupportStaff && $assigneeFilter !== "" && $assigneeFilter !== "unassigned"
            && (!ctype_digit($assigneeFilter) || (int)$assigneeFilter <= 0)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid assignee filter."]);
            exit;
        }

        $assignmentColumns = $isSupportStaff
            ? "tickets.assigned_to_user_id, assignee.name AS assignee_name,"
            : "NULL AS assigned_to_user_id, NULL AS assignee_name,";


        $sql = "



            SELECT

                tickets.id,

                tickets.user_id,

                tickets.name,

                tickets.email,

                tickets.phone,
                tickets.subject,

                tickets.category,

                tickets.priority,

                tickets.status,
                tickets.description,
                tickets.created_at,
                tickets.updated_at,
                {$assignmentColumns}
                reply_sla_events.first_response_at,
                status_sla_events.last_resolved_at


            FROM tickets
            LEFT JOIN organization_memberships AS assignee_membership
                ON assignee_membership.user_id = tickets.assigned_to_user_id
                AND assignee_membership.organization_id = tickets.organization_id
                AND assignee_membership.role IN ('admin', 'support')
            LEFT JOIN users AS assignee
                ON assignee.id = assignee_membership.user_id

            LEFT JOIN (
                SELECT
                    ticket_id,
                    MIN(created_at) AS first_response_at
                FROM ticket_messages
                WHERE user_role IN ('admin', 'support')
                GROUP BY ticket_id
            ) AS reply_sla_events
                ON reply_sla_events.ticket_id = tickets.id

            LEFT JOIN (
                SELECT
                    ticket_id,
                    MAX(CASE
                        WHEN description LIKE '% to Resolved.'
                            OR description LIKE '% to Closed.'
                        THEN created_at
                    END) AS last_resolved_at
                FROM activity_logs
                WHERE action = 'STATUS_CHANGE'
                GROUP BY ticket_id
            ) AS status_sla_events
                ON status_sla_events.ticket_id = tickets.id


            WHERE tickets.organization_id = :organization_id



        ";





        $params = [":organization_id" => $organizationId];

        if ($isSupportStaff && $assigneeFilter !== "") {
            if ($assigneeFilter === "unassigned") {
                $sql .= " AND tickets.assigned_to_user_id IS NULL ";
            } else {
                $sql .= " AND tickets.assigned_to_user_id = :assigned_to_user_id ";
                $params[":assigned_to_user_id"] = (int)$assigneeFilter;
            }
        }





        // -----------------------------------------------------

        // USER OWNERSHIP

        // -----------------------------------------------------



        if (!$isSupportStaff) {
            $sql .= " AND tickets.user_id = :user_id ";
            $params[":user_id"] = $sessionUserId;
        }





        // -----------------------------------------------------

        // SEARCH

        // -----------------------------------------------------



        if ($search !== "") {

            $sql .= "
                AND (
                    tickets.name LIKE :search_name
                    OR tickets.email LIKE :search_email
                    OR tickets.subject LIKE :search_subject
                    OR tickets.category LIKE :search_category
                    OR tickets.description LIKE :search_description
                )
            ";

            $searchPattern = "%" . $search . "%";
            $params[":search_name"] = $searchPattern;
            $params[":search_email"] = $searchPattern;
            $params[":search_subject"] = $searchPattern;
            $params[":search_category"] = $searchPattern;
            $params[":search_description"] = $searchPattern;

        }





        // -----------------------------------------------------

        // STATUS FILTER

        // -----------------------------------------------------



        if ($status !== "") {



            $sql .= "

                AND tickets.status = :status

            ";



            $params[":status"] =

                $status;

        }





        // -----------------------------------------------------

        // PRIORITY FILTER

        // -----------------------------------------------------



        if ($priority !== "") {



            $sql .= "

                AND tickets.priority = :priority

            ";



            $params[":priority"] =

                $priority;

        }





        $sql .= "

            ORDER BY tickets.id DESC

        ";





        $stmt =

            $conn->prepare($sql);



        $stmt->execute($params);





        $tickets =

            $stmt->fetchAll(PDO::FETCH_ASSOC);

        $assignees = [];
        $unassignedActiveTickets = 0;
        if ($isSupportStaff) {
            if ($isMainAdmin) {
                $assigneeStmt = $conn->prepare("
                    SELECT
                        users.id AS user_id,
                        users.name,
                        COUNT(CASE WHEN assigned_tickets.status NOT IN ('Resolved', 'Closed') THEN 1 END) AS active_tickets,
                        COUNT(assigned_tickets.id) AS total_tickets
                    FROM organization_memberships
                    INNER JOIN users
                        ON users.id = organization_memberships.user_id
                    LEFT JOIN tickets AS assigned_tickets
                        ON assigned_tickets.organization_id = organization_memberships.organization_id
                        AND assigned_tickets.assigned_to_user_id = organization_memberships.user_id
                    WHERE organization_memberships.organization_id = :organization_id
                        AND organization_memberships.role = 'support'
                    GROUP BY users.id, users.name
                    ORDER BY users.name ASC, users.id ASC
                ");
                $assigneeStmt->execute([":organization_id" => $organizationId]);
                $assignees = $assigneeStmt->fetchAll(PDO::FETCH_ASSOC);

                $unassignedStmt = $conn->prepare("
                    SELECT COUNT(*)
                    FROM tickets
                    WHERE organization_id = :organization_id
                        AND assigned_to_user_id IS NULL
                        AND status NOT IN ('Resolved', 'Closed')
                ");
                $unassignedStmt->execute([":organization_id" => $organizationId]);
                $unassignedActiveTickets = (int)$unassignedStmt->fetchColumn();
            } else {
                $assigneeStmt = $conn->prepare("
                    SELECT users.id AS user_id, users.name
                    FROM organization_memberships
                    INNER JOIN users
                        ON users.id = organization_memberships.user_id
                    WHERE organization_memberships.organization_id = :organization_id
                      AND organization_memberships.role = 'support'
                    ORDER BY users.name ASC, users.id ASC
                ");
                $assigneeStmt->execute([":organization_id" => $organizationId]);
                $assignees = $assigneeStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $slaNow = new DateTimeImmutable("now");
        $slaSummary = [
            "response_overdue" => 0,
            "resolution_overdue" => 0,
        ];
        foreach ($tickets as &$ticket) {
            $ticket = addTicketSlaData($ticket, $slaNow);
            if ($ticket["response_sla"]["state"] === "overdue") {
                $slaSummary["response_overdue"]++;
            }
            if ($ticket["resolution_sla"]["state"] === "overdue") {
                $slaSummary["resolution_overdue"]++;
            }
        }
        unset($ticket);



        $response = [



            "success" => true,



            "count" =>
                count($tickets),

            "sla_summary" => $slaSummary,

            "data" =>
                $tickets



        ];
        if ($isSupportStaff) {
            $response["assignees"] = $assignees;
        }
        if ($isMainAdmin) {
            $response["unassigned_active_tickets"] = $unassignedActiveTickets;
        }

        echo json_encode($response);



        exit;





    } catch (PDOException $e) {

        error_log("HelpDesk Pro ticket list query failed: " . $e->getMessage());

        http_response_code(500);



        echo json_encode([



            "success" => false,



            "message" =>

                "Unable to load tickets."



        ]);



        exit;

    }

}





// =========================================================

// POST

// =========================================================



if ($_SERVER["REQUEST_METHOD"] === "POST") {



    $action =

        $_POST["action"] ?? "";

    // =====================================================

    // CREATE TICKET

    // =====================================================



    if ($action === "create_ticket") {

        if (!$isSupportStaff) {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Support staff access is required to create tickets."
            ]);
            exit;
        }


        $name =

            trim($_POST["name"] ?? "");



        $email =

            trim($_POST["email"] ?? "");

        $phone = trim($_POST["phone"] ?? "");



        $subject =

            trim($_POST["subject"] ?? "");



        $category =

            trim($_POST["category"] ?? "");



        $priority =

            trim($_POST["priority"] ?? "");



        $description =

            trim($_POST["description"] ?? "");





        // -------------------------------------------------

        // VALIDATION

        // -------------------------------------------------



        if (

            $name === "" ||

            $email === "" ||

            $subject === "" ||

            $category === "" ||

            $priority === "" ||

            $description === ""

        ) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "All fields are required."



            ]);



            exit;

        }





        if (

            !filter_var(

                $email,

                FILTER_VALIDATE_EMAIL

            )

        ) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Invalid email address."



            ]);



            exit;

        }





        if ($phone !== "") {
            $phoneDigits = preg_replace('/\D+/', '', $phone);
            if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
                echo json_encode(["success" => false, "message" => "Enter a valid phone number with country code."]);
                exit;
            }
        }

        if (strlen($subject) < 5) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Subject must contain at least 5 characters."



            ]);



            exit;

        }





        if (strlen($description) < 10) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Description must contain at least 10 characters."



            ]);



            exit;

        }





        // =================================================
        // INSERT
        // =================================================

        try {
            $recipientLookup = $conn->prepare("
                SELECT users.id
                FROM users
                INNER JOIN organization_memberships membership
                    ON membership.user_id = users.id
                WHERE users.email = :email
                    AND membership.organization_id = :organization_id
                    AND membership.role = 'user'
                LIMIT 1
            ");
            $recipientLookup->execute([
                ":email" => $email,
                ":organization_id" => $organizationId,
            ]);
            $recipientUserId = $recipientLookup->fetchColumn();
            $recipientUserId = $recipientUserId === false ? null : (int)$recipientUserId;
            $assigneeValue = $_POST["assigned_to_user_id"] ?? "";
            if (!is_string($assigneeValue)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Choose a valid Support Member."]);
                exit;
            }
            $assigneeValue = trim($assigneeValue);
            if ($assigneeValue !== "" && (!ctype_digit($assigneeValue) || (int)$assigneeValue <= 0)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Choose a valid Support Member."]);
                exit;
            }
            $assigneeId = $assigneeValue === "" ? null : (int)$assigneeValue;
            $assigneeName = null;
            if ($assigneeId !== null) {
                $assigneeLookup = $conn->prepare("
                    SELECT users.name
                    FROM organization_memberships
                    INNER JOIN users
                        ON users.id = organization_memberships.user_id
                    WHERE organization_memberships.organization_id = :organization_id
                        AND organization_memberships.user_id = :user_id
                        AND organization_memberships.role IN ('admin', 'support')
                    LIMIT 1
                ");
                $assigneeLookup->execute([
                    ":organization_id" => $organizationId,
                    ":user_id" => $assigneeId,
                ]);
                $assigneeName = $assigneeLookup->fetchColumn();
                if ($assigneeName === false) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "The selected account is not a Support Member in this organization."]);
                    exit;
                }
            }
            $supportInquiryIdValue = trim((string)($_POST["support_inquiry_id"] ?? ""));
            $supportInquiryId = $supportInquiryIdValue === ""
                ? null
                : filter_var($supportInquiryIdValue, FILTER_VALIDATE_INT);
            if ($supportInquiryIdValue !== "" && (!$supportInquiryId || $supportInquiryId <= 0)) {
                echo json_encode(["success" => false, "message" => "Invalid support request reference."]);
                exit;
            }

            if ($supportInquiryId !== null) {
                $inquiryStmt = $conn->prepare("
                    SELECT user_id, email, ticket_id
                    FROM support_inquiries
                    WHERE id = :id
                      AND organization_id = :organization_id
                    LIMIT 1
                ");
                $inquiryStmt->execute([
                    ":id" => $supportInquiryId,
                    ":organization_id" => $organizationId,
                ]);
                $supportInquiry = $inquiryStmt->fetch(PDO::FETCH_ASSOC);
                if (
                    !$supportInquiry ||
                    $supportInquiry["ticket_id"] !== null ||
                    strtolower((string)$supportInquiry["email"]) !== strtolower($email) ||
                    $recipientUserId !== (int)$supportInquiry["user_id"]
                ) {
                    echo json_encode(["success" => false, "message" => "This support request cannot be linked to the ticket details."]);
                    exit;
                }
            }

            $conn->beginTransaction();

            $stmt = $conn->prepare("
                INSERT INTO tickets (
                    organization_id,
                    user_id,
                    assigned_to_user_id,
                    name,
                    email,
                    phone,
                    subject,
                    category,
                    priority,
                    status,
                    description
                ) VALUES (
                    :organization_id,
                    :user_id,
                    :assigned_to_user_id,
                    :name,
                    :email,
                    :phone,
                    :subject,
                    :category,
                    :priority,
                    'Open',
                    :description
                )
            ");
            $stmt->bindValue(":organization_id", $organizationId, PDO::PARAM_INT);
            $stmt->bindValue(
                ":user_id",
                $recipientUserId,
                $recipientUserId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
            );
            $stmt->bindValue(
                ":assigned_to_user_id",
                $assigneeId,
                $assigneeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
            );
            $stmt->bindValue(":name", $name);
            $stmt->bindValue(":email", $email);
            $stmt->bindValue(":phone", $phone);
            $stmt->bindValue(":subject", $subject);
            $stmt->bindValue(":category", $category);
            $stmt->bindValue(":priority", $priority);
            $stmt->bindValue(":description", $description);
            $stmt->execute();





            // -------------------------------------------------

            // GET TICKET ID

            // -------------------------------------------------



            $ticketId =

                $conn->lastInsertId();

            if ($recipientUserId === null) {
                $recipientLookup->execute([
                    ":email" => $email,
                    ":organization_id" => $organizationId,
                ]);
                $recipientUserId = $recipientLookup->fetchColumn();
                if ($recipientUserId !== false) {
                    $linkTicket = $conn->prepare("
                        UPDATE tickets
                        SET user_id = :user_id
                        WHERE id = :ticket_id
                            AND organization_id = :organization_id
                            AND user_id IS NULL
                    ");
                    $linkTicket->execute([
                        ":user_id" => (int)$recipientUserId,
                        ":ticket_id" => $ticketId,
                        ":organization_id" => $organizationId,
                    ]);
                }
            }
            if ($supportInquiryId !== null) {
                $linkInquiry = $conn->prepare("
                    UPDATE support_inquiries
                    SET ticket_id = :ticket_id
                    WHERE id = :inquiry_id
                        AND organization_id = :organization_id
                        AND ticket_id IS NULL
                ");
                $linkInquiry->execute([
                    ":ticket_id" => $ticketId,
                    ":inquiry_id" => $supportInquiryId,
                    ":organization_id" => $organizationId,
                ]);
                if ($linkInquiry->rowCount() !== 1) {
                    throw new RuntimeException("Support request was already linked to another ticket.");
                }
            }
            // =================================================

            // ACTIVITY LOG

            // =================================================



            logActivity(



                $conn,



                "CREATE",



                "Ticket #{$ticketId} was created.",



                $ticketId
            );

            if ($assigneeId !== null) {
                logActivity(
                    $conn,
                    "ASSIGNMENT",
                    "Ticket #{$ticketId} was assigned to {$assigneeName} during ticket creation.",
                    $ticketId
                );
            }

            $conn->commit();

            // =================================================

            // EMAIL

            // =================================================



            $safeName =

                htmlspecialchars(

                    $name,

                    ENT_QUOTES,

                    "UTF-8"

                );



            $safeSubject =

                htmlspecialchars(

                    $subject,

                    ENT_QUOTES,

                    "UTF-8"

                );



            $safeCategory =

                htmlspecialchars(

                    $category,

                    ENT_QUOTES,

                    "UTF-8"

                );



            $safePriority =

                htmlspecialchars(

                    $priority,

                    ENT_QUOTES,

                    "UTF-8"

                );



            $safeDescription =

                nl2br(

                    htmlspecialchars(

                        $description,

                        ENT_QUOTES,

                        "UTF-8"

                    )

                );





            $emailBody = "
                <!doctype html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <meta name='color-scheme' content='light'>
                    <meta name='supported-color-schemes' content='light'>
                    <title>Ticket #{$ticketId} received</title>
                </head>
                <body style='margin:0;padding:0;background-color:#eaf0f5;font-family:Arial,Helvetica,sans-serif;color:#183247;'>
                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border-collapse:collapse;background-color:#eaf0f5;'>
                        <tr>
                            <td align='center' class='ticket-email-outer' style='padding:32px 14px;'>
                                <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='width:100%;max-width:640px;border-collapse:separate;border-spacing:0;background-color:#ffffff;border:1px solid #dce6ed;border-radius:16px;overflow:hidden;'>
                                    <tr>
                                        <td class='ticket-email-header' style='padding:24px 30px;background-color:#0b3047;background-image:linear-gradient(120deg,#0b3047,#10506a);'>
                                            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border-collapse:collapse;'>
                                                <tr>
                                                    <td style='font-size:22px;line-height:28px;font-weight:bold;letter-spacing:.2px;color:#ffffff;'>Samadhan</td>
                                                    <td align='right' style='font-size:12px;line-height:18px;font-weight:bold;color:#bfeafa;'>SUPPORT &amp; RESOLUTION DESK</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class='ticket-email-content' style='padding:32px 30px 18px;'>
                                            <p style='margin:0 0 9px;font-size:12px;line-height:18px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase;color:#1689ac;'>Request received</p>
                                            <h1 class='ticket-email-title' style='margin:0 0 14px;font-size:25px;line-height:32px;color:#12344a;'>We’re on it, {$safeName}.</h1>
                                            <p style='margin:0;font-size:15px;line-height:24px;color:#536b7b;'>Your support request has been received and added to our queue. Keep this email for your reference.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class='ticket-email-section' style='padding:8px 30px 22px;'>
                                            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' class='ticket-email-details-table' style='border-collapse:separate;border-spacing:0;background-color:#f5f9fb;border:1px solid #e3edf2;border-radius:12px;'>
                                                <tr>
                                                    <td class='ticket-email-ref' style='padding:17px 18px;border-bottom:1px solid #e3edf2;'>
                                                        <span style='font-size:11px;line-height:16px;font-weight:bold;letter-spacing:.8px;text-transform:uppercase;color:#7a909e;'>Ticket reference</span><br>
                                                        <strong style='display:inline-block;margin-top:4px;font-size:19px;line-height:25px;color:#117f9f;'>#{$ticketId}</strong>
                                                    </td>
                                                    <td align='right' class='ticket-email-status' style='padding:17px 18px;border-bottom:1px solid #e3edf2;'>
                                                        <span style='display:inline-block;padding:6px 10px;border-radius:20px;background-color:#e7f7ef;color:#168353;font-size:12px;line-height:16px;font-weight:bold;'>Open</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan='2' style='padding:16px 18px 4px;'>
                                                        <span style='font-size:11px;line-height:16px;font-weight:bold;letter-spacing:.8px;text-transform:uppercase;color:#7a909e;'>Subject</span>
                                                        <p style='margin:5px 0 0;font-size:15px;line-height:22px;font-weight:bold;color:#183247;'>{$safeSubject}</p>
                                                    </td>
                                                </tr>
                                                <tr class='ticket-email-details-row'>
                                                    <td class='ticket-email-details-col' style='padding:13px 18px 16px;'>
                                                        <span style='font-size:11px;line-height:16px;font-weight:bold;letter-spacing:.8px;text-transform:uppercase;color:#7a909e;'>Category</span>
                                                        <p style='margin:5px 0 0;font-size:14px;line-height:20px;color:#385367;'>{$safeCategory}</p>
                                                    </td>
                                                    <td class='ticket-email-details-col' style='padding:13px 18px 16px;'>
                                                        <span style='font-size:11px;line-height:16px;font-weight:bold;letter-spacing:.8px;text-transform:uppercase;color:#7a909e;'>Priority</span>
                                                        <p style='margin:5px 0 0;font-size:14px;line-height:20px;font-weight:bold;color:#385367;'>{$safePriority}</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class='ticket-email-section' style='padding:0 30px 22px;'>
                                            <h2 style='margin:0 0 9px;font-size:15px;line-height:21px;color:#183247;'>What you told us</h2>
                                            <div style='padding:15px 16px;border-left:3px solid #36a8c5;border-radius:0 8px 8px 0;background-color:#f8fafc;font-size:14px;line-height:22px;color:#536b7b;'>{$safeDescription}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class='ticket-email-section' style='padding:0 30px 28px;'>
                                            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border-collapse:collapse;'>
                                                <tr>
                                                    <td style='padding:17px 18px;border-radius:10px;background-color:#eef8fb;'>
                                                        <p style='margin:0 0 5px;font-size:14px;line-height:21px;font-weight:bold;color:#16465e;'>What happens next?</p>
                                                        <p style='margin:0;font-size:13px;line-height:21px;color:#536b7b;'>Our support team will review your request and follow up. You can sign in to Samadhan to check your ticket or print your receipt.</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding:19px 30px;border-top:1px solid #e7eef2;background-color:#fbfdfe;'>
                                            <p style='margin:0 0 5px;font-size:13px;line-height:19px;color:#536b7b;'>Thank you for contacting us.</p>
                                            <p style='margin:0;font-size:13px;line-height:19px;font-weight:bold;color:#183247;'>Samadhan Support Team</p>
                                            <p style='margin:12px 0 0;font-size:11px;line-height:17px;color:#8a9ba6;'>This is an automated confirmation for ticket #{$ticketId}. Please keep it for your records.</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";





            $emailSent =

                sendHelpDeskEmail(



                    $email,



                    "Samadhan - Ticket #{$ticketId} Created",



                    $emailBody



                );





            echo json_encode([



                "success" => true,



                "message" =>

                    "Ticket created successfully!",



                "ticket_id" =>

                    $ticketId,



                "email_sent" =>

                    $emailSent



            ]);



            exit;





        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("HelpDesk Pro ticket creation failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Unable to create the ticket. Please try again.",
            ]);
            exit;
        }

    }





    // =====================================================

    // SUPPORT STAFF ONLY

    // =====================================================



    if (
        ($action === "update_status" && !$isSupportStaff) ||
        (!in_array($action, ["update_status", "delete_ticket"], true) && !$isSupportStaff)
    ) {



        http_response_code(403);



        echo json_encode([



            "success" => false,



            "message" =>
                "You do not have permission to perform this action."



        ]);



        exit;

    }





    // =====================================================

    // UPDATE STATUS

    // =====================================================



    if ($action === "update_status") {



        $id =

            (int)($_POST["id"] ?? 0);



        $newStatus =

            trim($_POST["status"] ?? "");





        $allowedStatuses = [



            "Open",

            "In Progress",

            "Resolved",

            "Closed"



        ];





        if ($id <= 0) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Invalid ticket ID."



            ]);



            exit;

        }





        if (

            !in_array(

                $newStatus,

                $allowedStatuses,

                true

            )

        ) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Invalid status."



            ]);



            exit;

        }





        try {



            // -------------------------------------------------

            // GET CURRENT TICKET

            // -------------------------------------------------



            $ticketSql = "
                SELECT
                    id,
                    user_id,
                    name,
                    email,
                    phone,
                    subject,
                    category,
                    priority,
                    status,
                    description,
                    created_at
                FROM tickets
                WHERE id = :id
                  AND organization_id = :organization_id
            ";
            $ticketParams = [
                ":id" => $id,
                ":organization_id" => $organizationId,
            ];
            $ticketStmt = $conn->prepare($ticketSql . " LIMIT 1");
            $ticketStmt->execute($ticketParams);





            $ticket =

                $ticketStmt->fetch(

                    PDO::FETCH_ASSOC

                );





            if (!$ticket) {



                echo json_encode([



                    "success" => false,



                    "message" =>

                        "Ticket not found."



                ]);



                exit;

            }





            $oldStatus =

                $ticket["status"];





            // -------------------------------------------------

            // NO CHANGE

            // -------------------------------------------------



            if ($oldStatus === $newStatus) {



                echo json_encode([



                    "success" => true,



                    "message" =>

                        "Ticket status is already " .

                        $newStatus . ".",



                    "ticket_id" =>

                        $id,



                    "status" =>

                        $newStatus,



                    "email_sent" =>

                        false



                ]);



                exit;

            }





            // =================================================

            // UPDATE DATABASE

            // =================================================



            $statusUpdateSql = "
                UPDATE tickets
                SET status = :status
                WHERE id = :id
                  AND organization_id = :organization_id
            ";
            $statusUpdateParams = [
                ":status" => $newStatus,
                ":id" => $id,
                ":organization_id" => $organizationId,
            ];
            $stmt =

                $conn->prepare($statusUpdateSql);

            $stmt->execute($statusUpdateParams);

            $completionStmt = $conn->prepare("
                UPDATE support_inquiries
                SET completed_at = CASE
                    WHEN :is_complete = 1 THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END
                WHERE ticket_id = :ticket_id
                  AND organization_id = :organization_id
            ");
            $completionStmt->execute([
                ":is_complete" => in_array($newStatus, ["Resolved", "Closed"], true) ? 1 : 0,
                ":ticket_id" => $id,
                ":organization_id" => $organizationId,
            ]);




            // =================================================

            // ACTIVITY LOG

            // =================================================



            logActivity(



                $conn,



                "STATUS_CHANGE",



                "Ticket #{$id} status changed from "

                . "{$oldStatus} to {$newStatus}.",



                $id

            );

            $activityId = (int)$conn->lastInsertId();
            $activityTimeStmt = $conn->prepare("
                SELECT created_at
                FROM activity_logs
                WHERE id = :id
                  AND organization_id = :organization_id
                LIMIT 1
            ");
            $activityTimeStmt->execute([
                ":id" => $activityId,
                ":organization_id" => $organizationId,
            ]);
            $completedAt = (string)$activityTimeStmt->fetchColumn();
            if ($isSupportStaff && !empty($ticket["user_id"])) {
                createUserNotification(
                    $conn,
                    (int)$ticket["user_id"],
                    "ticket_status",
                    $activityId,
                    $id,
                    null,
                    "Ticket #" . $id . " status updated",
                    "Status changed from " . $oldStatus . " to " . $newStatus . "."
                );
            }





            // =================================================

            // STATUS EMAIL

            // =================================================



            $safeName =

                htmlspecialchars(

                    $ticket["name"],

                    ENT_QUOTES,

                    "UTF-8"

                );



            $safeSubject =

                htmlspecialchars(

                    $ticket["subject"],

                    ENT_QUOTES,

                    "UTF-8"

                );



            $safeOldStatus =

                htmlspecialchars(

                    $oldStatus,

                    ENT_QUOTES,

                    "UTF-8"

                );



            $safeNewStatus =

                htmlspecialchars(

                    $newStatus,

                    ENT_QUOTES,

                    "UTF-8"

                );





            $statusEmailBody = "



                <div style='

                    font-family:Arial,sans-serif;

                    max-width:650px;

                    margin:auto;

                    padding:20px;

                    border:1px solid #e5e7eb;

                    border-radius:10px;

                '>



                    <h2 style='color:#2563eb;'>

                        Samadhan

                    </h2>



                    <p>

                        Hello

                        <strong>

                            {$safeName}

                        </strong>,

                    </p>



                    <p>

                        The status of your support

                        ticket has been updated.

                    </p>



                    <div style='

                        background:#f8fafc;

                        padding:15px;

                        border-radius:8px;

                        margin:20px 0;

                    '>



                        <p>

                            <strong>

                                Ticket ID:

                            </strong>

                            #{$id}

                        </p>



                        <p>

                            <strong>

                                Subject:

                            </strong>

                            {$safeSubject}

                        </p>



                        <p>

                            <strong>

                                Previous Status:

                            </strong>

                            {$safeOldStatus}

                        </p>



                        <p>

                            <strong>

                                New Status:

                            </strong>



                            <strong>

                                {$safeNewStatus}

                            </strong>

                        </p>



                    </div>



                    <p>

                        You can log in to Samadhan

                        to view your ticket details.

                    </p>



                    <hr>



                    <p>

                        Regards,<br>

                        <strong>

                            Samadhan Support Team

                        </strong>

                    </p>



                </div>



            ";





            $isCompleted = in_array($newStatus, ["Resolved", "Closed"], true);
            $receiptAttached = false;
            $receiptPath = null;
            $emailSent = false;
            try {
                $attachments = [];
                if ($isCompleted && !empty($ticket["user_id"])) {
                    if ($completedAt === "") {
                        throw new RuntimeException("Unable to determine ticket completion time.");
                    }
                    $ticket["id"] = $id;
                    $ticket["status"] = $newStatus;
                    $receiptPath = generateTicketReceiptPdf($ticket, $completedAt);
                    $attachments[] = [
                        "path" => $receiptPath,
                        "name" => "Samadhan_Ticket_{$id}_Resolution_Receipt.pdf",
                    ];
                    $receiptAttached = true;
                }

                $emailSent = sendHelpDeskEmail(
                    $ticket["email"],
                    "Samadhan - Ticket #{$id} Status Updated",
                    $statusEmailBody,
                    $attachments
                );
            } catch (Throwable $e) {
                error_log("Samadhan ticket status email or receipt generation failed for ticket #{$id}: " . $e->getMessage());
                $emailSent = false;
            } finally {
                if ($receiptPath !== null && is_file($receiptPath) && !unlink($receiptPath)) {
                    error_log("Samadhan temporary receipt PDF cleanup failed for ticket #{$id}.");
                }
            }





            echo json_encode([



                "success" => true,

                "message" =>
                    $emailSent
                        ? ($receiptAttached
                            ? "Ticket status updated and the resolution receipt was emailed."
                            : "Ticket status updated successfully.")
                        : "Ticket status updated, but the customer email could not be sent.",



                "ticket_id" =>

                    $id,



                "status" =>

                    $newStatus,

                "email_sent" =>
                    $emailSent,

                "receipt_attached" =>
                    $receiptAttached

            ]);



            exit;





        } catch (PDOException $e) {

            error_log("HelpDesk Pro ticket status update failed: " . $e->getMessage());

            http_response_code(500);



            echo json_encode([



                "success" => false,



                "message" =>

                    "Unable to update the ticket status."



            ]);



            exit;

        }

    }





    // =====================================================

    // UPDATE TICKET

    // =====================================================



    if ($action === "update_ticket") {



        $id =

            (int)($_POST["id"] ?? 0);



        $name =

            trim($_POST["name"] ?? "");



        $email =

            trim($_POST["email"] ?? "");

        $phone = trim($_POST["phone"] ?? "");



        $subject =

            trim($_POST["subject"] ?? "");



        $category =

            trim($_POST["category"] ?? "");



        $priority =

            trim($_POST["priority"] ?? "");



        $description =

            trim($_POST["description"] ?? "");





        // -------------------------------------------------

        // VALIDATION

        // -------------------------------------------------



        if ($id <= 0) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Invalid ticket ID."



            ]);



            exit;

        }





        if (

            $name === "" ||

            $email === "" ||

            $subject === "" ||

            $category === "" ||

            $priority === "" ||

            $description === ""

        ) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "All fields are required."



            ]);



            exit;

        }





        if (

            !filter_var(

                $email,

                FILTER_VALIDATE_EMAIL

            )

        ) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Invalid email address."



            ]);



            exit;

        }





        if ($phone !== "") {
            $phoneDigits = preg_replace('/\D+/', '', $phone);
            if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
                echo json_encode(["success" => false, "message" => "Enter a valid phone number with country code."]);
                exit;
            }
        }

        if (strlen($subject) < 5) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Subject must contain at least 5 characters."



            ]);



            exit;

        }





        if (strlen($description) < 10) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Description must contain at least 10 characters."



            ]);



            exit;

        }





        try {



            // -------------------------------------------------

            // CHECK TICKET EXISTS

            // -------------------------------------------------



            $checkStmt =

                $conn->prepare("



                    SELECT id



                    FROM tickets



                    WHERE id = :id

                      AND organization_id = :organization_id


                    LIMIT 1



                ");





            $checkStmt->execute([



                ":id" =>

                    $id,

                ":organization_id" =>

                    $organizationId



            ]);





            if (

                !$checkStmt->fetch(

                    PDO::FETCH_ASSOC

                )

            ) {



                echo json_encode([



                    "success" => false,



                    "message" =>

                        "Ticket not found."



                ]);



                exit;

            }





            // =================================================

            // UPDATE

            // =================================================



            $stmt =

                $conn->prepare("



                    UPDATE tickets



                    SET

                        name = :name,

                        email = :email,

                        phone = :phone,

                        subject = :subject,

                        category = :category,

                        priority = :priority,

                        description = :description



                    WHERE id = :id

                      AND organization_id = :organization_id

                ");





            $stmt->execute([



                ":name" =>

                    $name,



                ":email" =>

                    $email,

                ":phone" =>

                    $phone,

                ":subject" =>

                    $subject,



                ":category" =>

                    $category,



                ":priority" =>

                    $priority,



                ":description" =>

                    $description,



                ":id" =>

                    $id,

                ":organization_id" =>

                    $organizationId

            ]);





            // =================================================

            // ACTIVITY LOG

            // =================================================



            logActivity(



                $conn,



                "UPDATE",



                "Ticket #{$id} was edited by "

                . ($_SESSION["user_name"] ?? "Admin")

                . ".",



                $id



            );





            echo json_encode([



                "success" => true,



                "message" =>

                    "Ticket updated successfully.",



                "ticket_id" =>

                    $id



            ]);



            exit;





        } catch (PDOException $e) {

            error_log("HelpDesk Pro ticket update failed: " . $e->getMessage());

            http_response_code(500);



            echo json_encode([



                "success" => false,



                "message" =>

                    "Unable to update the ticket."



            ]);



            exit;

        }

    }





    // =====================================================

    // DELETE TICKET

    // =====================================================



    if ($action === "delete_ticket") {



        $id =

            (int)($_POST["id"] ?? 0);





        if ($id <= 0) {



            echo json_encode([



                "success" => false,



                "message" =>

                    "Invalid ticket ID."



            ]);



            exit;

        }





        try {



            // -------------------------------------------------

            // CHECK TICKET

            // -------------------------------------------------



            $checkSql = "
                SELECT id, subject
                FROM tickets
                WHERE id = :id
                  AND organization_id = :organization_id
            ";
            $checkParams = [
                ":id" => $id,
                ":organization_id" => $organizationId,
            ];
            if (!$isSupportStaff) {
                $checkSql .= " AND user_id = :user_id";
                $checkParams[":user_id"] = $sessionUserId;
            }
            $checkSql .= " LIMIT 1";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute($checkParams);





            $ticket =

                $checkStmt->fetch(

                    PDO::FETCH_ASSOC

                );





            if (!$ticket) {



                echo json_encode([



                    "success" => false,



                    "message" =>

                        "Ticket not found."



                ]);



                exit;

            }





            // =================================================

            // LOG BEFORE DELETE

            // =================================================



            logActivity(



                $conn,



                "DELETE",



                "Ticket #{$id} "

                . "({$ticket["subject"]}) "
                . "was deleted by "
                . ($_SESSION["user_name"] ?? "Admin")
                . " (" . ($_SESSION["role"] ?? "user") . ")"
                . ".",



                $id



            );





            // =================================================

            // DELETE

            // =================================================



            $deleteSql = "DELETE FROM tickets WHERE id = :id AND organization_id = :organization_id";
            $deleteParams = [
                ":id" => $id,
                ":organization_id" => $organizationId,
            ];
            if (!$isSupportStaff) {
                $deleteSql .= " AND user_id = :user_id";
                $deleteParams[":user_id"] = $sessionUserId;
            }
            $stmt = $conn->prepare($deleteSql);
            $stmt->execute($deleteParams);

            if ($stmt->rowCount() !== 1) {
                echo json_encode([
                    "success" => false,
                    "message" => "Ticket not found."
                ]);
                exit;
            }





            echo json_encode([



                "success" => true,



                "message" =>

                    "Ticket deleted successfully.",



                "ticket_id" =>

                    $id



            ]);



            exit;





        } catch (PDOException $e) {

            error_log("HelpDesk Pro ticket deletion failed: " . $e->getMessage());

            http_response_code(500);



            echo json_encode([



                "success" => false,



                "message" =>

                    "Unable to delete the ticket."



            ]);



            exit;

        }

    }





    // =====================================================

    // INVALID ACTION

    // =====================================================



    echo json_encode([



        "success" => false,



        "message" =>

            "Invalid action."



    ]);



    exit;

}





// =========================================================

// INVALID METHOD

// =========================================================



http_response_code(405);



echo json_encode([



    "success" => false,



    "message" =>

        "Method not allowed."



]);



?>