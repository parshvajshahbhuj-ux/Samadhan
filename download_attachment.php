
<?php

require_once "db.php";
require_once "auth.php";

requireLogin();

$userId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isSupportStaff = isSupportStaff();
$attachmentId = (int)($_GET["id"] ?? 0);

if ($attachmentId <= 0) {
    http_response_code(400);
    exit("Invalid attachment ID.");
}

try {
    /*
    |--------------------------------------------------------------------------
    | FETCH ATTACHMENT AND TICKET
    |--------------------------------------------------------------------------
    */

    if ($isSupportStaff) {
        $stmt = $conn->prepare("
            SELECT
                a.id,
                a.ticket_id,
                a.original_name,
                a.stored_name,
                a.mime_type,
                a.file_size,
                t.user_id AS ticket_owner_id
            FROM ticket_attachments a
            INNER JOIN tickets t
                ON t.id = a.ticket_id
            WHERE a.id = :id
              AND t.organization_id = :organization_id
            LIMIT 1
        ");

        $stmt->execute([
            ":id" => $attachmentId,
            ":organization_id" => $organizationId,
        ]);
    } else {
        $stmt = $conn->prepare("
            SELECT
                a.id,
                a.ticket_id,
                a.original_name,
                a.stored_name,
                a.mime_type,
                a.file_size,
                t.user_id AS ticket_owner_id
            FROM ticket_attachments a
            INNER JOIN tickets t
                ON t.id = a.ticket_id
            WHERE a.id = :id
              AND t.organization_id = :organization_id
              AND t.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ":id" => $attachmentId,
            ":organization_id" => $organizationId,
            ":user_id" => $userId
        ]);
    }

    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        http_response_code(404);
        exit("Attachment not found or access denied.");
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE STORED FILENAME
    |--------------------------------------------------------------------------
    */

    $storedName = $attachment["stored_name"];

    if (
        !preg_match(
            '/\A[a-f0-9]{48}\.(pdf|png|jpg|jpeg|txt|csv|webp)\z/i',
            $storedName
        )
    ) {
        error_log("Invalid stored attachment filename.");
        http_response_code(500);
        exit("Unable to retrieve attachment.");
    }

    /*
    |--------------------------------------------------------------------------
    | LOCATE FILE
    |--------------------------------------------------------------------------
    */

    $uploadDirectory =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        "uploads" .
        DIRECTORY_SEPARATOR .
        "ticket_attachments";

    $filePath =
        $uploadDirectory .
        DIRECTORY_SEPARATOR .
        $storedName;

    if (!is_file($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        exit("Attachment file not found.");
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY FILE SIZE
    |--------------------------------------------------------------------------
    */

    $actualSize = filesize($filePath);

    if (
        $actualSize === false ||
        $actualSize !== (int)$attachment["file_size"]
    ) {
        error_log(
            "Attachment size mismatch for ID " .
            $attachmentId
        );

        http_response_code(500);
        exit("Unable to verify attachment.");
    }

    /*
    |--------------------------------------------------------------------------
    | SEND FILE AS DOWNLOAD
    |--------------------------------------------------------------------------
    */

    $originalName = basename(
        str_replace("\\", "/", $attachment["original_name"])
    );

    $safeAsciiName = preg_replace(
        '/[^A-Za-z0-9._-]/',
        "_",
        $originalName
    );

    if ($safeAsciiName === "" || $safeAsciiName === null) {
        $safeAsciiName = "attachment";
    }

    $encodedName = rawurlencode($originalName);

    session_write_close();

    header("Content-Type: application/octet-stream");

    header(
        'Content-Disposition: attachment; filename="' .
        $safeAsciiName .
        '"; filename*=UTF-8\'\'' .
        $encodedName
    );

    header("Content-Length: " . $actualSize);
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, no-store, max-age=0");
    header("Pragma: no-cache");

    readfile($filePath);
    exit;

} catch (PDOException $e) {
    error_log("Attachment download error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to download attachment.");
}
?>
