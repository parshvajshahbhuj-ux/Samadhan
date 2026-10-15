<?php

function logActivity(
    $conn,
    $action,
    $description,
    $ticketId = null
) {

    $organizationId = (int)($_SESSION["organization_id"] ?? 0);
    if ($organizationId <= 0) {
        $organizationId = (int)$conn->query("
            SELECT id FROM organizations WHERE slug = 'samadhan' LIMIT 1
        ")->fetchColumn();
    }
    if ($organizationId <= 0) {
        throw new RuntimeException("Unable to determine the activity organization.");
    }

    $userId =
        $_SESSION["user_id"] ?? null;

    $userName =
        $_SESSION["user_name"] ?? "Unknown User";

    $userRole =
        $_SESSION["role"] ?? "unknown";


    $stmt = $conn->prepare("

        INSERT INTO activity_logs
        (
            organization_id,
            user_id,
            user_name,
            user_role,
            ticket_id,
            action,
            description
        )

        VALUES
        (
            :organization_id,
            :user_id,
            :user_name,
            :user_role,
            :ticket_id,
            :action,
            :description
        )

    ");


    $stmt->execute([

        ":organization_id" =>
            $organizationId,

        ":user_id" =>
            $userId,

        ":user_name" =>
            $userName,

        ":user_role" =>
            $userRole,

        ":ticket_id" =>
            $ticketId,

        ":action" =>
            $action,

        ":description" =>
            $description

    ]);
}
?>