<?php
// HelpDesk Pro - Export Tickets to CSV
// Save this file as: api/export_tickets.php

require_once "../db.php";
require_once "../auth.php";

requireLogin();

$sessionUserId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isStaff = isSupportStaff();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
    exit("Method not allowed.");
}

try {
    $readFilter = static function (string $key): string {
        if (!isset($_GET[$key])) {
            return "";
        }
        if (!is_string($_GET[$key])) {
            throw new InvalidArgumentException("Invalid export filter.");
        }
        return trim($_GET[$key]);
    };

    $search = $readFilter("search");
    $status = $readFilter("status");
    $statusGroup = $readFilter("status_group");
    $priority = $readFilter("priority");
    $assignedTo = $readFilter("assigned_to");
    $startDate = $readFilter("start_date");
    $endDate = $readFilter("end_date");
    $dateRange = null;

    if ($startDate !== "" || $endDate !== "") {
        $parseDate = static function (string $value): ?DateTimeImmutable {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                return null;
            }
            $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
            $errors = DateTimeImmutable::getLastErrors();
            if (
                $date === false ||
                ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0)) ||
                $date->format("Y-m-d") !== $value
            ) {
                return null;
            }
            return $date;
        };

        $start = $parseDate($startDate);
        $end = $parseDate($endDate);
        if ($start === null || $end === null || $end < $start) {
            throw new InvalidArgumentException("Choose a valid start and end date.");
        }
        $dateRange = [
            "start" => $start->format("Y-m-d"),
            "end_exclusive" => $end->modify("+1 day")->format("Y-m-d")
        ];
    }

    $allowedStatuses = ["Open", "In Progress", "Resolved", "Closed"];
    $allowedPriorities = ["Low", "Medium", "High", "Critical"];
    if ($status !== "" && !in_array($status, $allowedStatuses, true)) {
        throw new InvalidArgumentException("Invalid status filter.");
    }
    if ($statusGroup !== "" && !in_array($statusGroup, ["open", "progress", "completed"], true)) {
        throw new InvalidArgumentException("Invalid status group filter.");
    }
    if ($priority !== "" && !in_array($priority, $allowedPriorities, true)) {
        throw new InvalidArgumentException("Invalid priority filter.");
    }
    if ($assignedTo !== "" && (!$isStaff ||
        ($assignedTo !== "unassigned" && (!ctype_digit($assignedTo) || (int)$assignedTo <= 0)))) {
        throw new InvalidArgumentException("Invalid assignee filter.");
    }

    $sql = "SELECT id, user_id, name, email, phone, subject, category, priority, status, description, created_at, updated_at
            FROM tickets
            WHERE organization_id = :organization_id";
    $params = [":organization_id" => $organizationId];

    if (!$isStaff) {
        $sql .= " AND user_id = :user_id";
        $params[":user_id"] = $sessionUserId;
    }

    if ($search !== "") {
        $sql .= " AND (name LIKE :search_name OR email LIKE :search_email OR subject LIKE :search_subject OR category LIKE :search_category OR description LIKE :search_description)";
        $searchPattern = "%" . $search . "%";
        $params[":search_name"] = $searchPattern;
        $params[":search_email"] = $searchPattern;
        $params[":search_subject"] = $searchPattern;
        $params[":search_category"] = $searchPattern;
        $params[":search_description"] = $searchPattern;
    }

    if ($status !== "") {
        $sql .= " AND status = :status";
        $params[":status"] = $status;
    }

    if ($statusGroup === "open") {
        $sql .= " AND status = 'Open'";
    } elseif ($statusGroup === "progress") {
        $sql .= " AND status = 'In Progress'";
    } elseif ($statusGroup === "completed") {
        $sql .= " AND status IN ('Resolved', 'Closed')";
    }

    if ($priority !== "") {
        $sql .= " AND priority = :priority";
        $params[":priority"] = $priority;
    }

    if ($assignedTo === "unassigned") {
        $sql .= " AND assigned_to_user_id IS NULL";
    } elseif ($assignedTo !== "") {
        $sql .= " AND assigned_to_user_id = :assigned_to_user_id";
        $params[":assigned_to_user_id"] = (int)$assignedTo;
    }

    if ($dateRange !== null) {
        $sql .= " AND created_at >= :start_date AND created_at < :end_date";
        $params[":start_date"] = $dateRange["start"];
        $params[":end_date"] = $dateRange["end_exclusive"];
    }

    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $filename = $dateRange === null
        ? "helpdesk_tickets_" . date("Y-m-d_H-i-s") . ".csv"
        : "helpdesk_ticket_report_" . $dateRange["start"] . "_to_"
            . (new DateTimeImmutable($dateRange["end_exclusive"]))->modify("-1 day")->format("Y-m-d") . ".csv";
    header("Content-Type: text/csv; charset=UTF-8");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: no-store, no-cache, must-revalidate");

    $output = fopen("php://output", "w");
    if ($output === false) {
        throw new RuntimeException("Unable to open CSV output stream.");
    }

    // UTF-8 BOM helps Excel display non-ASCII text correctly.
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        "Ticket ID",
        "User ID",
        "Name",
        "Email",
        "Subject",
        "Category",
        "Priority",
        "Status",
        "Description",
        "Created At",
        "Updated At"
    ]);

    // Reduce CSV formula-injection risk when opening exports in spreadsheet software.
    $safeCsvValue = static function ($value) {
        $value = (string)($value ?? "");
        if ($value !== "" && preg_match('/^[\x00-\x20\x7f]*[=+\-@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    };

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $safeCsvValue($row["id"]),
            $safeCsvValue($row["user_id"]),
            $safeCsvValue($row["name"]),
            $safeCsvValue($row["email"]),
            $safeCsvValue($row["subject"]),
            $safeCsvValue($row["category"]),
            $safeCsvValue($row["priority"]),
            $safeCsvValue($row["status"]),
            $safeCsvValue($row["description"]),
            $safeCsvValue($row["created_at"]),
            $safeCsvValue($row["updated_at"])
        ]);
    }

    fclose($output);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header("Content-Type: text/plain; charset=UTF-8");
    exit($e->getMessage());
} catch (Throwable $e) {
    // Avoid exposing database details to the browser.
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: text/plain; charset=UTF-8");
    }
    error_log("HelpDesk Pro CSV export failed: " . $e->getMessage());
    exit("Unable to export tickets right now. Please try again later.");
}
