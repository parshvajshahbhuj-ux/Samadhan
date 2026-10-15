<?php
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../email_helper.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
requireLogin();

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Admin access required."]);
    exit;
}

$organizationId = getOrganizationId();
$userId = (int)($_SESSION["user_id"] ?? 0);

function organizationInvitationResponse(bool $success, string $message, array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(["success" => $success, "message" => $message] + $data);
    exit;
}

function createOrganizationInvitationCode(): string
{
    $alphabet = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ";
    $characters = "";
    for ($index = 0; $index < 20; $index++) {
        $characters .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return implode("-", str_split($characters, 4));
}

function sendOrganizationInvitationEmail(string $email, string $organizationName, string $accessCode): bool
{
    $safeOrganizationName = htmlspecialchars($organizationName, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $safeAccessCode = htmlspecialchars($accessCode, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    return sendHelpDeskEmail(
        $email,
        "Invitation to join " . $organizationName . " on Samadhan",
        "<h2>You are invited to a support workspace</h2>"
            . "<p>{$safeOrganizationName} invited you to join its Samadhan support workspace.</p>"
            . "<p>Open the Samadhan sign-in page and choose <strong>Sign in with an invitation code</strong>.</p>"
            . "<p>Your personal workspace code is: <strong style=\"font-size:20px;letter-spacing:2px\">{$safeAccessCode}</strong></p>"
            . "<p>This code signs you in to this workspace. Keep it private. The invitation expires in 7 days; after joining, the code remains active until it is replaced or your workspace access is removed.</p>"
            . "<p>If you were not expecting this invitation, you can ignore this email.</p>"
    );
}

function sendOrganizationAccessCodeEmail(string $email, string $organizationName, string $accessCode): bool
{
    $safeOrganizationName = htmlspecialchars($organizationName, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $safeAccessCode = htmlspecialchars($accessCode, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    return sendHelpDeskEmail(
        $email,
        "Your " . $organizationName . " sign-in code was updated",
        "<h2>Your workspace sign-in code was updated</h2>"
            . "<p>Your access code for {$safeOrganizationName} has been reset by a workspace administrator.</p>"
            . "<p>Use this code on the Samadhan sign-in page: <strong style=\"font-size:20px;letter-spacing:2px\">{$safeAccessCode}</strong></p>"
            . "<p>Your previous code no longer works. Keep this new code private. If you did not request this, contact your workspace administrator.</p>"
    );
}

try {
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") {
        $members = $conn->prepare("
            SELECT users.id, users.name, users.email, membership.role, membership.created_at
            FROM organization_memberships membership
            INNER JOIN users ON users.id = membership.user_id
            WHERE membership.organization_id = :organization_id
            ORDER BY membership.role DESC, users.name ASC, users.id ASC
        ");
        $members->execute([":organization_id" => $organizationId]);

        $invitations = $conn->prepare("
            SELECT id, email, role, expires_at, created_at
            FROM organization_invitations
            WHERE organization_id = :organization_id
              AND accepted_at IS NULL
              AND expires_at > CURRENT_TIMESTAMP
            ORDER BY created_at DESC, id DESC
        ");
        $invitations->execute([":organization_id" => $organizationId]);

        organizationInvitationResponse(true, "Workspace access loaded.", [
            "members" => $members->fetchAll(PDO::FETCH_ASSOC),
            "invitations" => $invitations->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
        header("Allow: GET, POST");
        organizationInvitationResponse(false, "Method not allowed.", [], 405);
    }

    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    $expectedToken = (string)($_SESSION["organization_invitation_csrf"] ?? "");
    if ($expectedToken === "" || !hash_equals($expectedToken, $csrfToken)) {
        organizationInvitationResponse(false, "Your request could not be verified. Refresh the page and try again.", [], 400);
    }

    $action = trim((string)($_POST["action"] ?? ""));
    if ($action === "revoke") {
        $invitationId = filter_var($_POST["invitation_id"] ?? null, FILTER_VALIDATE_INT);
        if (!$invitationId || $invitationId <= 0) {
            organizationInvitationResponse(false, "Invalid invitation.", [], 400);
        }
        $revoke = $conn->prepare("
            UPDATE organization_invitations
            SET expires_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND organization_id = :organization_id
              AND accepted_at IS NULL
        ");
        $revoke->execute([
            ":id" => $invitationId,
            ":organization_id" => $organizationId,
        ]);
        organizationInvitationResponse(true, $revoke->rowCount() ? "Invitation revoked." : "Invitation is no longer active.");
    }

    if ($action === "resend") {
        $invitationId = filter_var($_POST["invitation_id"] ?? null, FILTER_VALIDATE_INT);
        if (!$invitationId || $invitationId <= 0) {
            organizationInvitationResponse(false, "Invalid invitation.", [], 400);
        }

        $organizationName = organizationName($conn, $organizationId);
        if ($organizationName === "") {
            throw new RuntimeException("The current workspace could not be loaded.");
        }
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash("sha256", $token);
        $accessCode = createOrganizationInvitationCode();
        $accessCodeHash = hash("sha256", str_replace("-", "", $accessCode));
        $expiresAt = (new DateTimeImmutable("+7 days"))->format("Y-m-d H:i:s");

        $conn->beginTransaction();
        try {
            $lock = $conn->prepare("
                SELECT email
                FROM organization_invitations
                WHERE id = :id
                  AND organization_id = :organization_id
                  AND accepted_at IS NULL
                  AND expires_at > CURRENT_TIMESTAMP
                LIMIT 1
                FOR UPDATE
            ");
            $lock->execute([
                ":id" => $invitationId,
                ":organization_id" => $organizationId,
            ]);
            $invitation = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$invitation) {
                $conn->rollBack();
                organizationInvitationResponse(false, "This invitation is no longer active. Refresh the page and try again.", [], 409);
            }

            $update = $conn->prepare("
                UPDATE organization_invitations
                SET token_hash = :token_hash,
                    access_code_hash = :access_code_hash,
                    expires_at = :expires_at
                WHERE id = :id
                  AND organization_id = :organization_id
                  AND accepted_at IS NULL
            ");
            $update->execute([
                ":token_hash" => $tokenHash,
                ":access_code_hash" => $accessCodeHash,
                ":expires_at" => $expiresAt,
                ":id" => $invitationId,
                ":organization_id" => $organizationId,
            ]);
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }

        if (!sendOrganizationInvitationEmail((string)$invitation["email"], $organizationName, $accessCode)) {
            $expire = $conn->prepare("
                UPDATE organization_invitations
                SET expires_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND organization_id = :organization_id
                  AND token_hash = :token_hash
                  AND accepted_at IS NULL
            ");
            $expire->execute([
                ":id" => $invitationId,
                ":organization_id" => $organizationId,
                ":token_hash" => $tokenHash,
            ]);
            organizationInvitationResponse(false, "The invitation email could not be sent. Check mail service configuration and try again.", [], 502);
        }

        organizationInvitationResponse(true, "Invitation resent with a new sign-in code. The previous code was replaced, and the invitation is valid for 7 more days.", [
            "invitation" => [
                "id" => $invitationId,
                "email" => (string)$invitation["email"],
                "expires_at" => $expiresAt,
            ],
        ]);
    }

    if ($action === "reset_access_code") {
        $memberUserId = filter_var($_POST["user_id"] ?? null, FILTER_VALIDATE_INT);
        if (!$memberUserId || $memberUserId <= 0) {
            organizationInvitationResponse(false, "Invalid workspace member.", [], 400);
        }

        $organizationName = organizationName($conn, $organizationId);
        if ($organizationName === "") {
            throw new RuntimeException("The current workspace could not be loaded.");
        }

        $accessCode = createOrganizationInvitationCode();
        $accessCodeHash = hash("sha256", str_replace("-", "", $accessCode));
        $previousAccessCodeHash = null;
        $member = null;
        $conn->beginTransaction();
        try {
            $lock = $conn->prepare("
                SELECT users.email, membership.access_code_hash
                FROM organization_memberships membership
                INNER JOIN users ON users.id = membership.user_id
                WHERE membership.organization_id = :organization_id
                  AND membership.user_id = :user_id
                LIMIT 1
                FOR UPDATE
            ");
            $lock->execute([
                ":organization_id" => $organizationId,
                ":user_id" => $memberUserId,
            ]);
            $member = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$member) {
                $conn->rollBack();
                organizationInvitationResponse(false, "This member is not part of the current workspace.", [], 404);
            }
            $previousAccessCodeHash = $member["access_code_hash"];

            $update = $conn->prepare("
                UPDATE organization_memberships
                SET access_code_hash = :access_code_hash
                WHERE organization_id = :organization_id
                  AND user_id = :user_id
            ");
            $update->execute([
                ":access_code_hash" => $accessCodeHash,
                ":organization_id" => $organizationId,
                ":user_id" => $memberUserId,
            ]);
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }

        if (!sendOrganizationAccessCodeEmail((string)$member["email"], $organizationName, $accessCode)) {
            $restore = $conn->prepare("
                UPDATE organization_memberships
                SET access_code_hash = :previous_access_code_hash
                WHERE organization_id = :organization_id
                  AND user_id = :user_id
                  AND access_code_hash = :current_access_code_hash
            ");
            $restore->execute([
                ":previous_access_code_hash" => $previousAccessCodeHash,
                ":organization_id" => $organizationId,
                ":user_id" => $memberUserId,
                ":current_access_code_hash" => $accessCodeHash,
            ]);
            if ($restore->rowCount() !== 1) {
                error_log("HelpDesk Pro workspace access code could not be restored after email delivery failed.");
                organizationInvitationResponse(false, "The reset email could not be sent and the previous code could not be restored automatically. Contact your system administrator.", [], 500);
            }
            organizationInvitationResponse(false, "The new sign-in code could not be emailed. The existing code remains active; check mail service configuration and try again.", [], 502);
        }

        organizationInvitationResponse(true, "A new sign-in code was emailed. The member's previous code is now invalid.");
    }

    if ($action !== "invite") {
        organizationInvitationResponse(false, "Invalid invitation action.", [], 400);
    }

    $email = strtolower(trim((string)($_POST["email"] ?? "")));
    $role = trim((string)($_POST["role"] ?? "user"));
    if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        organizationInvitationResponse(false, "Enter a valid email address.", [], 400);
    }
    if (!in_array($role, ["user", "support"], true)) {
        organizationInvitationResponse(false, "Choose a valid workspace role.", [], 400);
    }

    $existingUser = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $existingUser->execute([":email" => $email]);
    $existingUserId = $existingUser->fetchColumn();
    if ($existingUserId !== false) {
        $existingMembership = $conn->prepare("
            SELECT role
            FROM organization_memberships
            WHERE user_id = :user_id
              AND organization_id = :organization_id
            LIMIT 1
        ");
        $existingMembership->execute([
            ":user_id" => (int)$existingUserId,
            ":organization_id" => $organizationId,
        ]);
        if ($existingMembership->fetchColumn() !== false) {
            organizationInvitationResponse(
                false,
                "This person is already a member of your workspace.",
                [],
                409
            );
        }
    }

    $organizationName = organizationName($conn, $organizationId);
    if ($organizationName === "") {
        throw new RuntimeException("The current workspace could not be loaded.");
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash("sha256", $token);
    $accessCode = createOrganizationInvitationCode();
    $accessCodeHash = hash("sha256", str_replace("-", "", $accessCode));
    $expiresAt = (new DateTimeImmutable("+7 days"))->format("Y-m-d H:i:s");
    $conn->beginTransaction();
    try {
        $expirePrevious = $conn->prepare("
            UPDATE organization_invitations
            SET expires_at = CURRENT_TIMESTAMP
            WHERE organization_id = :organization_id
              AND email = :email
              AND accepted_at IS NULL
              AND expires_at > CURRENT_TIMESTAMP
        ");
        $expirePrevious->execute([
            ":organization_id" => $organizationId,
            ":email" => $email,
        ]);

        $insert = $conn->prepare("
            INSERT INTO organization_invitations
                (organization_id, invited_by, email, role, token_hash, access_code_hash, expires_at)
            VALUES
                (:organization_id, :invited_by, :email, :role, :token_hash, :access_code_hash, :expires_at)
        ");
        $insert->execute([
            ":organization_id" => $organizationId,
            ":invited_by" => $userId,
            ":email" => $email,
            ":role" => $role,
            ":token_hash" => $tokenHash,
            ":access_code_hash" => $accessCodeHash,
            ":expires_at" => $expiresAt,
        ]);
        $invitationId = (int)$conn->lastInsertId();
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    $sent = sendOrganizationInvitationEmail($email, $organizationName, $accessCode);

    if (!$sent) {
        $expire = $conn->prepare("
            UPDATE organization_invitations
            SET expires_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND organization_id = :organization_id
        ");
        $expire->execute([
            ":id" => $invitationId,
            ":organization_id" => $organizationId,
        ]);
        organizationInvitationResponse(false, "The invitation email could not be sent. Check mail service configuration and try again.", [], 502);
    }

    organizationInvitationResponse(true, "Invitation sent with a personal sign-in code.", [
        "invitation" => [
            "id" => $invitationId,
            "email" => $email,
            "role" => $role,
            "expires_at" => $expiresAt,
        ],
        "csrf_token" => $_SESSION["organization_invitation_csrf"],
    ]);
} catch (Throwable $e) {
    error_log("HelpDesk Pro organization invitation error: " . $e->getMessage());
    organizationInvitationResponse(false, "Unable to manage workspace invitations right now.", [], 500);
}
