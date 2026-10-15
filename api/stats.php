<?php

require_once "../db.php";
require_once "../auth.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
requireLogin();

$userId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isSupportStaff = isSupportStaff();
$scopeColumn = "user_id";
$scope = " AND organization_id = :organization_id" . ($isSupportStaff ? "" : " AND {$scopeColumn} = :user_id");
$params = [":organization_id" => $organizationId];
if (!$isSupportStaff) {
    $params[":user_id"] = $userId;
}

try {
    // Total tickets
    $totalSql = "SELECT COUNT(*) FROM tickets WHERE organization_id = :organization_id"
        . ($isSupportStaff ? "" : " AND {$scopeColumn} = :user_id");
    $totalQuery = $conn->prepare($totalSql);
    $totalQuery->execute($params);
    $total = $totalQuery->fetchColumn();

    // Open tickets
    $openQuery = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'Open'" . $scope);
    $openQuery->execute($params);
    $open = $openQuery->fetchColumn();

    // In Progress tickets
    $progressQuery = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'In Progress'" . $scope);
    $progressQuery->execute($params);
    $progress = $progressQuery->fetchColumn();

    // Resolved tickets
    $resolvedQuery = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'Resolved'" . $scope);
    $resolvedQuery->execute($params);
    $resolved = $resolvedQuery->fetchColumn();

    echo json_encode([
        "success" => true,
        "data" => [
            "total" => (int)$total,
            "open" => (int)$open,
            "progress" => (int)$progress,
            "resolved" => (int)$resolved        ]
    ]);
} catch (Throwable $e) {
    error_log("HelpDesk Pro statistics API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to load statistics."
    ]);
}
?>
