<?php
// HelpDesk Pro dashboard reporting API.
require_once "../db.php";
require_once "../auth.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
requireLogin();

$userId = (int)($_SESSION["user_id"] ?? 0);
$isStaff = isSupportStaff();
$organizationId = getOrganizationId();
$scopeColumn = "user_id";
$where = " WHERE organization_id = :organization_id" . ($isStaff ? "" : " AND {$scopeColumn} = :user_id");
$params = [":organization_id" => $organizationId];
if (!$isStaff) {
    $params[":user_id"] = $userId;
}

try {
    $summarySql = "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS progress_count,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) AS closed_count,
        SUM(CASE WHEN priority = 'Critical' THEN 1 ELSE 0 END) AS critical_count
        FROM tickets" . $where;
    $stmt = $conn->prepare($summarySql);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $groupQuery = static function (PDO $conn, string $column, string $where, array $params): array {
        $allowed = ["status", "priority", "category"];
        if (!in_array($column, $allowed, true)) return [];
        $sql = "SELECT COALESCE(NULLIF(" . $column . ", ''), 'Uncategorized') AS label, COUNT(*) AS total FROM tickets" . $where . " GROUP BY " . $column . " ORDER BY total DESC, label ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $status = $groupQuery($conn, "status", $where, $params);
    $priority = $groupQuery($conn, "priority", $where, $params);
    $category = array_slice($groupQuery($conn, "category", $where, $params), 0, 5);

    $trend = [];
    $trendStart = (new DateTimeImmutable("today"))->modify("-6 days");
    $trendEnd = (new DateTimeImmutable("tomorrow"));
    $trendSql = "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM tickets WHERE created_at >= :start_date AND created_at < :end_date";
    $trendSql .= " AND organization_id = :organization_id";
    $trendParams = [
        ":start_date" => $trendStart->format("Y-m-d"),
        ":end_date" => $trendEnd->format("Y-m-d"),
        ":organization_id" => $organizationId,
    ];
    if (!$isStaff) {
        $trendSql .= " AND {$scopeColumn} = :user_id";
        $trendParams[":user_id"] = $userId;
    }
    $trendSql .= " GROUP BY DATE(created_at) ORDER BY day ASC";
    $trendStmt = $conn->prepare($trendSql);
    $trendStmt->execute($trendParams);
    $trendRows = [];
    foreach ($trendStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $trendRows[$row["day"]] = (int)$row["total"];
    for ($i = 0; $i < 7; $i++) {
        $day = $trendStart->modify("+" . $i . " days");
        $key = $day->format("Y-m-d");
        $trend[] = ["date" => $key, "label" => $day->format("D"), "total" => $trendRows[$key] ?? 0];
    }

    $total = (int)($summary["total"] ?? 0);
    $resolved = (int)($summary["resolved_count"] ?? 0) + (int)($summary["closed_count"] ?? 0);
    echo json_encode([
        "success" => true,
        "data" => [
            "summary" => [
                "total" => $total,
                "open" => (int)($summary["open_count"] ?? 0),
                "progress" => (int)($summary["progress_count"] ?? 0),
                "resolved" => (int)($summary["resolved_count"] ?? 0),
                "closed" => (int)($summary["closed_count"] ?? 0),
                "critical" => (int)($summary["critical_count"] ?? 0),
                "completion_rate" => $total ? (int)round(($resolved / $total) * 100) : 0
            ],
            "status" => $status,
            "priority" => $priority,
            "category" => $category,
            "trend" => $trend,
            "generated_at" => date(DATE_ATOM)
        ]
    ]);
} catch (Throwable $e) {
    error_log("HelpDesk Pro report API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to load reports right now."]);
}
