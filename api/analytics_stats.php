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

$userId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isStaff = isSupportStaff();
$scopeColumn = "user_id";

$parseDate = static function ($value): ?DateTimeImmutable {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
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

$range = $_GET["range"] ?? "last30";
$today = new DateTimeImmutable("today");

try {
    if (!is_string($range)) {
        throw new InvalidArgumentException("Invalid date range.");
    }

    switch ($range) {
        case "last7":
            $startDate = $today->modify("-6 days");
            $endDate = $today;
            break;
        case "last30":
            $startDate = $today->modify("-29 days");
            $endDate = $today;
            break;
        case "thisMonth":
            $startDate = $today->modify("first day of this month");
            $endDate = $today;
            break;
        case "previousMonth":
            $startDate = $today->modify("first day of previous month");
            $endDate = $today->modify("last day of previous month");
            break;
        case "custom":
            $startDate = $parseDate($_GET["start_date"] ?? null);
            $endDate = $parseDate($_GET["end_date"] ?? null);
            if ($startDate === null || $endDate === null || $endDate < $startDate) {
                throw new InvalidArgumentException("Choose a valid start and end date.");
            }
            break;
        default:
            throw new InvalidArgumentException("Choose a valid date range.");
    }

    $start = $startDate->format("Y-m-d");
    $end = $endDate->format("Y-m-d");
    $endExclusive = $endDate->modify("+1 day")->format("Y-m-d");
    $scopeSql = " AND t.organization_id = :organization_id" . ($isStaff ? "" : " AND t.{$scopeColumn} = :user_id");
    $scopeParams = [":organization_id" => $organizationId];
    if (!$isStaff) {
        $scopeParams[":user_id"] = $userId;
    }
    $dateParams = [
        ":start_date" => $start,
        ":end_date" => $endExclusive
    ];

    $summarySql = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN t.status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) AS progress_count,
        SUM(CASE WHEN t.status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN t.status = 'Closed' THEN 1 ELSE 0 END) AS closed_count,
        SUM(CASE WHEN t.priority = 'Critical' THEN 1 ELSE 0 END) AS critical_count
        FROM tickets t
        WHERE t.created_at >= :start_date
            AND t.created_at < :end_date" . $scopeSql;
    $summaryStmt = $conn->prepare($summarySql);
    $summaryStmt->execute($dateParams + $scopeParams);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $groupByColumn = static function (string $column) use ($conn, $dateParams, $scopeParams, $scopeSql): array {
        $allowedColumns = ["status", "priority", "category"];
        if (!in_array($column, $allowedColumns, true)) {
            throw new LogicException("Unsupported analytics grouping.");
        }

        $sql = "SELECT COALESCE(NULLIF(t." . $column . ", ''), 'Uncategorized') AS label,
                COUNT(*) AS total
            FROM tickets t
            WHERE t.created_at >= :start_date
                AND t.created_at < :end_date" . $scopeSql . "
            GROUP BY t." . $column . "
            ORDER BY total DESC, label ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($dateParams + $scopeParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $monthlyStart = $startDate->modify("first day of this month");
    $monthlyEnd = $endDate->modify("first day of this month");
    $months = [];
    for ($month = $monthlyStart; $month <= $monthlyEnd; $month = $month->modify("+1 month")) {
        $key = $month->format("Y-m");
        $months[$key] = ["month" => $key, "created" => 0, "resolved" => 0];
    }

    $monthlyCreationSql = "SELECT DATE_FORMAT(t.created_at, '%Y-%m') AS period, COUNT(*) AS total
        FROM tickets t
        WHERE t.created_at >= :start_date
            AND t.created_at < :end_date" . $scopeSql . "
        GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')";
    $monthlyCreationStmt = $conn->prepare($monthlyCreationSql);
    $monthlyCreationStmt->execute($dateParams + $scopeParams);
    foreach ($monthlyCreationStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($months[$row["period"]])) {
            $months[$row["period"]]["created"] = (int)$row["total"];
        }
    }

    $resolutionParams = [
        ":trend_start" => $start,
        ":trend_end" => $endExclusive,
        ":resolved_pattern" => "% to Resolved.",
        ":closed_pattern" => "% to Closed.",
        ":activity_organization_id" => $organizationId,
    ] + $scopeParams;
    $monthlyResolutionSql = "SELECT DATE_FORMAT(a.created_at, '%Y-%m') AS period,
            COUNT(DISTINCT t.id) AS total
        FROM activity_logs a
        INNER JOIN tickets t ON t.id = a.ticket_id
        WHERE a.action = 'STATUS_CHANGE'
            AND a.organization_id = :activity_organization_id
            AND (a.description LIKE :resolved_pattern OR a.description LIKE :closed_pattern)
            AND a.created_at >= :trend_start
            AND a.created_at < :trend_end" . $scopeSql . "
        GROUP BY DATE_FORMAT(a.created_at, '%Y-%m')";
    $monthlyResolutionStmt = $conn->prepare($monthlyResolutionSql);
    $monthlyResolutionStmt->execute($resolutionParams);
    foreach ($monthlyResolutionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($months[$row["period"]])) {
            $months[$row["period"]]["resolved"] = (int)$row["total"];
        }
    }

    $dailyRows = [];
    $dailySql = "SELECT DATE(t.created_at) AS day, COUNT(*) AS total
        FROM tickets t
        WHERE t.created_at >= :start_date
            AND t.created_at < :end_date" . $scopeSql . "
        GROUP BY DATE(t.created_at)
        ORDER BY day ASC";
    $dailyStmt = $conn->prepare($dailySql);
    $dailyStmt->execute($dateParams + $scopeParams);
    foreach ($dailyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dailyRows[$row["day"]] = (int)$row["total"];
    }

    $weekdayNames = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
    $weekdayTotals = array_fill(0, 7, 0);
    $weekdaySql = "SELECT WEEKDAY(t.created_at) AS weekday_index, COUNT(*) AS total
        FROM tickets t
        WHERE t.created_at >= :start_date
            AND t.created_at < :end_date" . $scopeSql . "
        GROUP BY WEEKDAY(t.created_at)
        ORDER BY weekday_index ASC";
    $weekdayStmt = $conn->prepare($weekdaySql);
    $weekdayStmt->execute($dateParams + $scopeParams);
    foreach ($weekdayStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $weekdayIndex = (int)$row["weekday_index"];
        if (array_key_exists($weekdayIndex, $weekdayTotals)) {
            $weekdayTotals[$weekdayIndex] = (int)$row["total"];
        }
    }
    $weekday = [];
    foreach ($weekdayNames as $index => $name) {
        $weekday[] = ["label" => $name, "total" => $weekdayTotals[$index]];
    }

    $daily = [];
    for ($day = $startDate; $day <= $endDate; $day = $day->modify("+1 day")) {
        $key = $day->format("Y-m-d");
        $daily[] = ["date" => $key, "total" => $dailyRows[$key] ?? 0];
    }

    $averageSql = "SELECT AVG(TIMESTAMPDIFF(SECOND, t.created_at, first_resolution.resolved_at)) AS average_seconds
        FROM tickets t
        INNER JOIN (
            SELECT ticket_id, MIN(created_at) AS resolved_at
            FROM activity_logs
            WHERE action = 'STATUS_CHANGE'
                AND organization_id = :activity_organization_id
                AND (description LIKE :resolved_pattern OR description LIKE :closed_pattern)
            GROUP BY ticket_id
        ) first_resolution ON first_resolution.ticket_id = t.id
            AND first_resolution.resolved_at >= t.created_at
        WHERE t.created_at >= :start_date
            AND t.created_at < :end_date" . $scopeSql;
    $averageStmt = $conn->prepare($averageSql);
    $averageStmt->execute($dateParams + [
        ":resolved_pattern" => "% to Resolved.",
        ":closed_pattern" => "% to Closed.",
        ":activity_organization_id" => $organizationId,
    ] + $scopeParams);
    $averageSeconds = $averageStmt->fetchColumn();

    $feedbackSql = "SELECT AVG(f.rating) AS average_rating, COUNT(*) AS rating_count
        FROM ticket_feedback f
        INNER JOIN tickets t ON t.id = f.ticket_id
        WHERE f.created_at >= :feedback_start
            AND f.created_at < :feedback_end" . $scopeSql;
    $feedbackParams = [
        ":feedback_start" => $start,
        ":feedback_end" => $endExclusive,
    ] + $scopeParams;
    $feedbackStmt = $conn->prepare($feedbackSql);
    $feedbackStmt->execute($feedbackParams);
    $feedbackSummary = $feedbackStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $feedbackDistributionStmt = $conn->prepare("
        SELECT f.rating, COUNT(*) AS total
        FROM ticket_feedback f
        INNER JOIN tickets t ON t.id = f.ticket_id
        WHERE f.created_at >= :feedback_start
            AND f.created_at < :feedback_end" . $scopeSql . "
        GROUP BY f.rating
        ORDER BY f.rating ASC
    ");
    $feedbackDistributionStmt->execute($feedbackParams);
    $feedbackCounts = array_fill(1, 5, 0);
    foreach ($feedbackDistributionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rating = (int)$row["rating"];
        if ($rating >= 1 && $rating <= 5) {
            $feedbackCounts[$rating] = (int)$row["total"];
        }
    }
    $feedbackDistribution = [];
    foreach ($feedbackCounts as $rating => $count) {
        $feedbackDistribution[] = [
            "rating" => (int)$rating,
            "total" => $count,
        ];
    }

    $total = (int)($summary["total"] ?? 0);
    $resolvedCount = (int)($summary["resolved_count"] ?? 0);
    $closedCount = (int)($summary["closed_count"] ?? 0);

    echo json_encode([
        "success" => true,
        "data" => [
            "range" => [
                "preset" => $range,
                "start_date" => $start,
                "end_date" => $end
            ],
            "summary" => [
                "total" => $total,
                "open" => (int)($summary["open_count"] ?? 0),
                "progress" => (int)($summary["progress_count"] ?? 0),
                "resolved" => $resolvedCount,
                "closed" => $closedCount,
                "critical" => (int)($summary["critical_count"] ?? 0),
                "resolution_rate" => $total > 0
                    ? (int)round((($resolvedCount + $closedCount) / $total) * 100)
                    : 0,
                "average_resolution_seconds" => $averageSeconds === false || $averageSeconds === null
                    ? null
                    : (int)round((float)$averageSeconds)
            ],
            "satisfaction" => [
                "average_rating" => $feedbackSummary["average_rating"] === null
                    ? null
                    : round((float)$feedbackSummary["average_rating"], 1),
                "rating_count" => (int)($feedbackSummary["rating_count"] ?? 0),
                "distribution" => $feedbackDistribution,
            ],
            "monthly" => array_values($months),
            "daily" => $daily,
            "weekday" => $weekday,
            "status" => $groupByColumn("status"),
            "priority" => $groupByColumn("priority"),
            "category" => $groupByColumn("category"),
            "generated_at" => date(DATE_ATOM)
        ]
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} catch (Throwable $e) {
    error_log("HelpDesk Pro analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to load analytics right now."]);
}
