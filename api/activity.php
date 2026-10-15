<?php

require_once "../db.php";
require_once "../auth.php";

header("Content-Type: application/json");

requireLogin();

/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if (!isSupportStaff()) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Support staff access required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET ACTIVITY LOGS
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


try {

    /*
    |--------------------------------------------------------------------------
    | INPUTS
    |--------------------------------------------------------------------------
    */

    $search = trim($_GET["search"] ?? "");
    $action = trim($_GET["action"] ?? "");

    $page = max(
        1,
        (int)($_GET["page"] ?? 1)
    );

    $limit = 10;

    $offset = ($page - 1) * $limit;


    /*
    |--------------------------------------------------------------------------
    | BASE QUERY
    |--------------------------------------------------------------------------
    */

    $where = ["activity.organization_id = :organization_id"];
    $params = [":organization_id" => getOrganizationId()];


    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    if ($search !== "") {

        $where[] = "
            (
                activity.description LIKE :search
                OR users.name LIKE :name_search
                OR users.email LIKE :email_search
                OR CAST(activity.ticket_id AS CHAR) LIKE :ticket_search
            )
        ";
        $searchPattern = "%" . $search . "%";
        $params[":search"] = $searchPattern;
        $params[":name_search"] = $searchPattern;
        $params[":email_search"] = $searchPattern;
        $params[":ticket_search"] = $searchPattern;
    }


    /*
    |--------------------------------------------------------------------------
    | ACTION FILTER
    |--------------------------------------------------------------------------
    */

    if ($action !== "") {

        $allowedActions = [
            "CREATE",
            "UPDATE",
            "STATUS_CHANGE",
            "ASSIGNMENT",
            "DELETE",
            "LOGIN",
            "REGISTER"
        ];

        if (!in_array($action, $allowedActions, true)) {

            echo json_encode([
                "success" => false,
                "message" => "Invalid action filter."
            ]);

            exit;
        }

        $where[] = "activity.action = :action";

        $params[":action"] = $action;
    }


    /*
    |--------------------------------------------------------------------------
    | WHERE CLAUSE
    |--------------------------------------------------------------------------
    */

    $whereSQL = "";

    if (!empty($where)) {
        $whereSQL = "WHERE " . implode(" AND ", $where);
    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL RECORDS
    |--------------------------------------------------------------------------
    */

    $countSQL = "
        SELECT COUNT(*)
        FROM activity_logs activity
        LEFT JOIN users
            ON users.id = activity.user_id
        LEFT JOIN organization_memberships membership
            ON membership.user_id = users.id
            AND membership.organization_id = activity.organization_id
        $whereSQL
    ";

    $countStmt = $conn->prepare($countSQL);

    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }

    $countStmt->execute();

    $totalRecords = (int)$countStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL PAGES
    |--------------------------------------------------------------------------
    */

    $totalPages = (int)ceil(
        $totalRecords / $limit
    );


    /*
    |--------------------------------------------------------------------------
    | ACTIVITY DATA
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            activity.id,
            activity.user_id,
            activity.action,
            activity.description,
            activity.ticket_id,
            activity.created_at,

            users.name AS user_name,
            users.email AS user_email

        FROM activity_logs activity

        LEFT JOIN users
            ON users.id = activity.user_id
        LEFT JOIN organization_memberships membership
            ON membership.user_id = users.id
            AND membership.organization_id = activity.organization_id

        $whereSQL

        ORDER BY activity.created_at DESC

        LIMIT :limit
        OFFSET :offset
    ";


    $stmt = $conn->prepare($sql);


    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }


    $stmt->bindValue(
        ":limit",
        $limit,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ":offset",
        $offset,
        PDO::PARAM_INT
    );


    $stmt->execute();


    $activities = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "data" => $activities,

        "pagination" => [

            "current_page" => $page,

            "per_page" => $limit,

            "total_records" => $totalRecords,

            "total_pages" => $totalPages
        ]

    ]);

    exit;


} catch (PDOException $e) {
    error_log("HelpDesk Pro activity API database error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" => "Unable to load activity logs."

    ]);

    exit;
}
?>