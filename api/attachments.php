

<?php



require_once "../db.php";

require_once "../auth.php";

require_once "../activity_helper.php";
require_once "../email_helper.php";



header("Content-Type: application/json; charset=UTF-8");



requireLogin();



$sessionUserId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
$isSupportStaff = isSupportStaff();



const MAX_ATTACHMENT_SIZE = 5 * 1024 * 1024;



$allowedMimeTypes = [

    "pdf"  => ["application/pdf"],

    "png"  => ["image/png"],

    "jpg"  => ["image/jpeg"],

    "jpeg" => ["image/jpeg"],

    "txt"  => ["text/plain"],

    "csv"  => ["text/csv", "text/plain",

               "application/csv",

               "application/vnd.ms-excel"],

    "webp" => ["image/webp"]

];



function attachmentResponse(

    bool $success,

    string $message,

    array $extra = [],

    int $statusCode = 200

): void {

    http_response_code($statusCode);



    echo json_encode(array_merge([

        "success" => $success,

        "message" => $message

    ], $extra));



    exit;

}



function getAccessibleTicket(

    PDO $conn,

    int $ticketId,
    int $userId,
    int $organizationId,
    bool $isSupportStaff

): ?array {

    if ($isSupportStaff) {

        $stmt = $conn->prepare("

            SELECT id, user_id, subject, email, name

            FROM tickets
            WHERE id = :id
              AND organization_id = :organization_id
            LIMIT 1

        ");



        $stmt->execute([
            ":id" => $ticketId,
            ":organization_id" => $organizationId,
        ]);

    } else {

        $stmt = $conn->prepare("

            SELECT id, user_id, subject, email, name

            FROM tickets

            WHERE id = :id
              AND organization_id = :organization_id
              AND user_id = :user_id

            LIMIT 1

        ");



        $stmt->execute([

            ":id" => $ticketId,
            ":organization_id" => $organizationId,
            ":user_id" => $userId

        ]);

    }



    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);



    return $ticket ?: null;

}



$method = $_SERVER["REQUEST_METHOD"] ?? "GET";



/*

|--------------------------------------------------------------------------

| GET: LIST ATTACHMENTS

|--------------------------------------------------------------------------

*/



if ($method === "GET") {



    $ticketId = (int)($_GET["ticket_id"] ?? 0);



    if ($ticketId <= 0) {

        attachmentResponse(

            false,

            "Invalid ticket ID.",

            [],

            400

        );

    }



    try {

        $ticket = getAccessibleTicket(

            $conn,

            $ticketId,
            $sessionUserId,
            $organizationId,
            $isSupportStaff

        );



        if (!$ticket) {

            attachmentResponse(

                false,

                "Ticket not found or access denied.",

                [],

                404

            );

        }



        $stmt = $conn->prepare("

            SELECT

                a.id,

                a.ticket_id,

                a.uploaded_by,

                a.original_name,

                a.mime_type,

                a.file_size,

                a.created_at,

                u.name AS uploader_name

            FROM ticket_attachments a

            LEFT JOIN organization_memberships membership
                ON membership.user_id = a.uploaded_by
                AND membership.organization_id = :organization_id
            LEFT JOIN users u
                ON u.id = membership.user_id
            WHERE a.ticket_id = :ticket_id

            ORDER BY a.created_at DESC, a.id DESC

        ");



        $stmt->execute([
            ":organization_id" => $organizationId,
            ":ticket_id" => $ticketId
        ]);



        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);



        foreach ($attachments as &$attachment) {

            $attachment["file_size"] =

                (int)$attachment["file_size"];



            $attachment["download_url"] =

                "download_attachment.php?id=" .

                (int)$attachment["id"];

        }



        unset($attachment);



        attachmentResponse(

            true,

            "Attachments loaded successfully.",

            [

                "ticket_id" => $ticketId,

                "data" => $attachments,

                "count" => count($attachments)

            ]

        );



    } catch (PDOException $e) {

        error_log("Attachment list error: " . $e->getMessage());



        attachmentResponse(

            false,

            "Unable to load attachments.",

            [],

            500

        );

    }

}



/*

|--------------------------------------------------------------------------

| POST: UPLOAD ATTACHMENT

|--------------------------------------------------------------------------

*/



if ($method === "POST") {



    $ticketId = (int)($_POST["ticket_id"] ?? 0);



    if ($ticketId <= 0) {

        attachmentResponse(

            false,

            "Invalid ticket ID.",

            [],

            400

        );

    }



    if (

        !isset($_FILES["attachment"]) ||

        !is_array($_FILES["attachment"])

    ) {

        attachmentResponse(

            false,

            "Please select a file to upload.",

            [],

            400

        );

    }



    $file = $_FILES["attachment"];



    if ($file["error"] !== UPLOAD_ERR_OK) {

        $uploadMessages = [

            UPLOAD_ERR_INI_SIZE =>

                "File exceeds the server upload limit.",

            UPLOAD_ERR_FORM_SIZE =>

                "File exceeds the permitted size.",

            UPLOAD_ERR_PARTIAL =>

                "File upload was incomplete.",

            UPLOAD_ERR_NO_FILE =>

                "Please select a file.",

            UPLOAD_ERR_NO_TMP_DIR =>

                "Server temporary directory is unavailable.",

            UPLOAD_ERR_CANT_WRITE =>

                "Server could not save the uploaded file.",

            UPLOAD_ERR_EXTENSION =>

                "A server extension blocked the upload."

        ];



        attachmentResponse(

            false,

            $uploadMessages[$file["error"]]

                ?? "File upload failed.",

            [],

            400

        );

    }



    $fileSize = (int)$file["size"];



    if ($fileSize <= 0 || $fileSize > MAX_ATTACHMENT_SIZE) {

        attachmentResponse(

            false,

            "File must be larger than 0 bytes and no bigger than 5 MB.",

            [],

            400

        );

    }



    $originalName = basename(

        str_replace("\\\\", "/", $file["name"])

    );



    $extension = strtolower(

        pathinfo($originalName, PATHINFO_EXTENSION)

    );



    global $allowedMimeTypes;



    if (!isset($allowedMimeTypes[$extension])) {

        attachmentResponse(

            false,

            "File type not allowed. Allowed: PDF, PNG, JPG, JPEG, WEBP, TXT and CSV.",

            [],

            400

        );

    }



    if (

        !is_uploaded_file($file["tmp_name"]) ||

        !is_readable($file["tmp_name"])

    ) {

        attachmentResponse(

            false,

            "Invalid uploaded file.",

            [],

            400

        );

    }



    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file($file["tmp_name"]);



    if (

        !$mimeType ||

        !in_array(

            $mimeType,

            $allowedMimeTypes[$extension],

            true

        )

    ) {

        attachmentResponse(

            false,

            "File content does not match its extension.",

            [],

            400

        );

    }



    try {

        $ticket = getAccessibleTicket(

            $conn,

            $ticketId,
            $sessionUserId,
            $organizationId,
            $isSupportStaff

        );



        if (!$ticket) {

            attachmentResponse(

                false,

                "Ticket not found or access denied.",

                [],

                404

            );

        }



        $uploadDirectory =

            dirname(__DIR__) .

            DIRECTORY_SEPARATOR .

            "uploads" .

            DIRECTORY_SEPARATOR .

            "ticket_attachments";



        if (

            !is_dir($uploadDirectory) ||

            !is_writable($uploadDirectory)

        ) {

            error_log(

                "Attachment directory missing or not writable: " .

                $uploadDirectory

            );



            attachmentResponse(

                false,

                "Upload directory is unavailable. Check folder permissions.",

                [],

                500

            );

        }



        $storedName =

            bin2hex(random_bytes(24)) . "." . $extension;



        $destination =

            $uploadDirectory .

            DIRECTORY_SEPARATOR .

            $storedName;



        if (!move_uploaded_file(

            $file["tmp_name"],

            $destination

        )) {

            attachmentResponse(

                false,

                "Unable to save uploaded file.",

                [],

                500

            );

        }



        try {

            $stmt = $conn->prepare("

                INSERT INTO ticket_attachments (

                    ticket_id,

                    uploaded_by,

                    original_name,

                    stored_name,

                    mime_type,

                    file_size

                ) VALUES (

                    :ticket_id,

                    :uploaded_by,

                    :original_name,

                    :stored_name,

                    :mime_type,

                    :file_size

                )

            ");



            $stmt->execute([

                ":ticket_id" => $ticketId,

                ":uploaded_by" => $sessionUserId,

                ":original_name" => $originalName,

                ":stored_name" => $storedName,

                ":mime_type" => $mimeType,

                ":file_size" => $fileSize

            ]);



            $attachmentId = (int)$conn->lastInsertId();



        } catch (Throwable $e) {

            if (is_file($destination)) {

                unlink($destination);

            }



            throw $e;

        }



        try {

            logActivity(

                $conn,

                "UPDATE",

                "An attachment was uploaded to ticket #" .

                    $ticketId . ".",

                $ticketId

            );

        } catch (Throwable $e) {

            // Keep the successful upload even if audit logging fails.

            error_log(

                "Attachment activity log error: " .

                $e->getMessage()

            );

        }



        
        // ---------------------------------------------------------
        // EMAIL THE TICKET OWNER AFTER A SUCCESSFUL UPLOAD
        // ---------------------------------------------------------
        $emailSent = false;
        $recipientEmail = trim((string)($ticket["email"] ?? ""));
        $ticketSubject = (string)($ticket["subject"] ?? "");
        $ticketOwnerName = (string)($ticket["name"] ?? "there");

        if (filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $safeName = htmlspecialchars($ticketOwnerName, ENT_QUOTES, "UTF-8");
            $safeSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, "UTF-8");
            $safeFileName = htmlspecialchars($originalName, ENT_QUOTES, "UTF-8");
            $uploadedAt = date("d M Y, h:i A");

            $emailBody = "
            <div style='font-family:Arial,sans-serif;max-width:650px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:10px;background:#ffffff;color:#1f2937;'>
                <h2 style='color:#087fb1;margin-top:0;'>Samadhan</h2>
                <p>Hello <strong>{$safeName}</strong>,</p>
                <p>A document has been uploaded to your support ticket.</p>
                <div style='background:#f8fafc;padding:16px;border-radius:8px;margin:20px 0;'>
                    <p><strong>Ticket ID:</strong> #{$ticketId}</p>
                    <p><strong>Subject:</strong> {$safeSubject}</p>
                    <p><strong>Document:</strong> {$safeFileName}</p>
                    <p><strong>Uploaded at:</strong> {$uploadedAt}</p>
                </div>
                <p>Log in to Samadhan to view your ticket and download the document.</p>
                <hr style='border:none;border-top:1px solid #e5e7eb;'>
                <p>Regards,<br><strong>Samadhan Support Team</strong></p>
            </div>
            ";

            try {
                $emailSent = (bool) sendHelpDeskEmail(
                    $recipientEmail,
                    "Samadhan - Document Uploaded to Ticket #{$ticketId}",
                    $emailBody,
                    [
                        [
                            "path" => $destination,
                            "name" => $originalName
                        ]
                    ]
                );
            } catch (Throwable $mailError) {
                // Keep the upload successful even if email delivery fails.
                error_log("Attachment notification email error: " . $mailError->getMessage());
                $emailSent = false;
            }
        } else {
            error_log("Attachment notification skipped: invalid ticket email for ticket #" . $ticketId);
        }

        attachmentResponse(

            true,

            "Attachment uploaded successfully.",

            [

                "email_sent" => $emailSent,
                "data" => [

                    "id" => $attachmentId,

                    "ticket_id" => $ticketId,

                    "original_name" => $originalName,

                    "mime_type" => $mimeType,

                    "file_size" => $fileSize,

                    "download_url" =>

                        "download_attachment.php?id=" .

                        $attachmentId

                ]

            ],

            201

        );



    } catch (PDOException $e) {

        error_log("Attachment database error: " . $e->getMessage());



        attachmentResponse(

            false,

            "Database error while saving attachment.",

            [],

            500

        );



    } catch (Throwable $e) {

        error_log("Attachment upload error: " . $e->getMessage());



        attachmentResponse(

            false,

            "An unexpected upload error occurred.",

            [],

            500

        );

    }

}



/*

|--------------------------------------------------------------------------

| UNSUPPORTED METHODS

|--------------------------------------------------------------------------

*/



header("Allow: GET, POST");



attachmentResponse(

    false,

    "Method not allowed.",

    [],

    405

);
