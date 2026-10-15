<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
requireLogin();

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    exit("Method not allowed.");
}

$csrfToken = (string)($_POST["csrf_token"] ?? "");
$expectedToken = (string)($_SESSION["workspace_switch_csrf"] ?? "");
if ($expectedToken === "" || !hash_equals($expectedToken, $csrfToken)) {
    http_response_code(400);
    exit("Your request could not be verified. Refresh the page and try again.");
}

$organizationId = filter_var($_POST["organization_id"] ?? null, FILTER_VALIDATE_INT);
if (!$organizationId || $organizationId <= 0) {
    http_response_code(400);
    exit("Choose a valid workspace.");
}

try {
    $membership = $conn->prepare("
        SELECT membership.role, organizations.name AS organization_name
        FROM organization_memberships membership
        INNER JOIN organizations ON organizations.id = membership.organization_id
        WHERE membership.user_id = :user_id
          AND membership.organization_id = :organization_id
        LIMIT 1
    ");
    $membership->execute([
        ":user_id" => (int)$_SESSION["user_id"],
        ":organization_id" => $organizationId,
    ]);
    $workspace = $membership->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("HelpDesk Pro workspace switch lookup failed: " . $e->getMessage());
    http_response_code(503);
    exit("Workspace selection is temporarily unavailable.");
}
if (!$workspace) {
    http_response_code(403);
    exit("You do not have access to that workspace.");
}

session_regenerate_id(true);
$_SESSION["organization_id"] = (int)$organizationId;
$_SESSION["organization_name"] = (string)$workspace["organization_name"];
$_SESSION["role"] = (string)$workspace["role"];
header("Location: welcome.php");
exit;
