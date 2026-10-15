<?php

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

function requireLogin()
{
    if (!isset($_SESSION["user_id"])) {
        header(
            "Location: login.php"
        );
        exit;
    }

    try {
        global $conn;
        require_once __DIR__ . "/db.php";
        $userId = (int)$_SESSION["user_id"];
        $organizationId = getOrganizationId();
        if ($organizationId > 0) {
            $membership = $conn->prepare("
                SELECT membership.role, organizations.name AS organization_name
                FROM organization_memberships membership
                INNER JOIN organizations ON organizations.id = membership.organization_id
                WHERE membership.user_id = :user_id
                  AND membership.organization_id = :organization_id
                LIMIT 1
            ");
            $membership->execute([
                ":user_id" => $userId,
                ":organization_id" => $organizationId,
            ]);
            $workspace = $membership->fetch(PDO::FETCH_ASSOC);
        } else {
            $workspace = false;
        }

        if (!$workspace) {
            $membership = $conn->prepare("
                SELECT membership.organization_id, membership.role,
                    organizations.name AS organization_name
                FROM users
                INNER JOIN organization_memberships membership
                    ON membership.user_id = users.id
                    AND membership.organization_id = users.organization_id
                INNER JOIN organizations ON organizations.id = membership.organization_id
                WHERE users.id = :user_id
                LIMIT 1
            ");
            $membership->execute([":user_id" => $userId]);
            $workspace = $membership->fetch(PDO::FETCH_ASSOC);
            if ($workspace) {
                $_SESSION["organization_id"] = (int)$workspace["organization_id"];
            }
        }

        if ($workspace) {
            $_SESSION["organization_name"] = (string)$workspace["organization_name"];
            $_SESSION["role"] = (string)$workspace["role"];
            return;
        }
    } catch (Throwable $e) {
        error_log("HelpDesk Pro session organization lookup failed: " . $e->getMessage());
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $cookie["path"], $cookie["domain"], $cookie["secure"], $cookie["httponly"]);
    }
    session_destroy();
    header("Location: login.php");
    exit;
}

function getUserOrganizations(PDO $conn, int $userId): array
{
    $stmt = $conn->prepare("
        SELECT membership.organization_id, membership.role, organizations.name
        FROM organization_memberships membership
        INNER JOIN organizations ON organizations.id = membership.organization_id
        WHERE membership.user_id = :user_id
        ORDER BY organizations.name ASC, membership.organization_id ASC
    ");
    $stmt->execute([":user_id" => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOrganizationId(): int
{
    return max(0, (int)($_SESSION["organization_id"] ?? 0));
}

function organizationName(PDO $conn, int $organizationId): string
{
    static $name = null;
    if ($organizationId <= 0) {
        return "";
    }
    if (is_array($name) && isset($name[$organizationId])) {
        return $name[$organizationId];
    }
    $stmt = $conn->prepare("SELECT name FROM organizations WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $organizationId]);
    $organizationName = $stmt->fetchColumn();
    if (!is_string($organizationName) || $organizationName === "") {
        return "";
    }
    $name[$organizationId] = $organizationName;
    return $organizationName;
}


/*
|--------------------------------------------------------------------------
| Check Admin
|--------------------------------------------------------------------------
*/

function isAdmin()
{
    return (
        isset($_SESSION["role"]) &&
        $_SESSION["role"] === "admin"
    );
}

function isSupportMember(): bool
{
    return isset($_SESSION["role"]) && $_SESSION["role"] === "support";
}

function isSupportStaff(): bool
{
    return isAdmin() || isSupportMember();
}

function currentRoleLabel(): string
{
    if (isAdmin()) {
        return "Main Admin";
    }
    if (isSupportMember()) {
        return "Support Member";
    }
    return "Customer";
}


/*
|--------------------------------------------------------------------------
| Require Admin
|--------------------------------------------------------------------------
*/

function requireAdmin()
{

    requireLogin();


    if (!isAdmin()) {

        http_response_code(403);

        echo json_encode([

            "success" => false,

            "message" =>
                "Admin access required."

        ]);

        exit;

    }

}

?>