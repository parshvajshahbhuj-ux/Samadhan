<?php
session_start();
require_once "db.php";

if (empty($_SESSION["invitation_accept_csrf"])) {
    $_SESSION["invitation_accept_csrf"] = bin2hex(random_bytes(32));
}

$token = trim((string)($_GET["token"] ?? $_POST["token"] ?? ""));
$error = "";
$notice = "";
$invitation = null;
$nameValue = "";

if ($token === "" || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
    $error = "This invitation link is invalid. Ask your workspace administrator to send a new invitation.";
} else {
    $lookup = $conn->prepare("
        SELECT invitation.id, invitation.organization_id, invitation.email, invitation.role,
            invitation.expires_at, organizations.name AS organization_name
        FROM organization_invitations invitation
        INNER JOIN organizations ON organizations.id = invitation.organization_id
        WHERE invitation.token_hash = :token_hash
          AND invitation.accepted_at IS NULL
          AND invitation.expires_at > CURRENT_TIMESTAMP
        LIMIT 1
    ");
    $lookup->execute([":token_hash" => hash("sha256", $token)]);
    $invitation = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$invitation) {
        $error = "This invitation has expired or has already been used. Ask your workspace administrator for a new one.";
    } else {
        $existingUserStmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $existingUserStmt->execute([":email" => $invitation["email"]]);
        $invitation["existing_account"] = $existingUserStmt->fetchColumn() !== false;
    }
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && $invitation) {
    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    if (!hash_equals((string)$_SESSION["invitation_accept_csrf"], $csrfToken)) {
        http_response_code(400);
        $error = "Your request could not be verified. Refresh the page and try again.";
    } else {
        $password = (string)($_POST["password"] ?? "");
        $confirmPassword = (string)($_POST["confirm_password"] ?? "");
        $nameValue = trim((string)($_POST["name"] ?? ""));
        $isExistingAccount = !empty($invitation["existing_account"]);
        if ($password === "") {
            $error = $isExistingAccount
                ? "Enter your current account password to join this workspace."
                : "Choose a password for your account.";
        } elseif (!$isExistingAccount && ($nameValue === "" || strlen($nameValue) > 100)) {
            $error = "Enter your name (up to 100 characters).";
        } elseif (!$isExistingAccount && strlen($password) < 12) {
            $error = "Choose a password with at least 12 characters.";
        } elseif (!$isExistingAccount && !hash_equals($password, $confirmPassword)) {
            $error = "The passwords do not match.";
        } else {
            try {
                $conn->beginTransaction();
                $lock = $conn->prepare("
                    SELECT id, organization_id, email, role, access_code_hash
                    FROM organization_invitations
                    WHERE token_hash = :token_hash
                      AND accepted_at IS NULL
                      AND expires_at > CURRENT_TIMESTAMP
                    LIMIT 1
                    FOR UPDATE
                ");
                $lock->execute([":token_hash" => hash("sha256", $token)]);
                $currentInvite = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$currentInvite) {
                    throw new DomainException("This invitation is no longer active. Ask your workspace administrator for a new one.");
                }

                $existingAccountStmt = $conn->prepare("
                    SELECT id, name, password
                    FROM users
                    WHERE email = :email
                    LIMIT 1
                ");
                $existingAccountStmt->execute([":email" => $currentInvite["email"]]);
                $account = $existingAccountStmt->fetch(PDO::FETCH_ASSOC);
                if ($account) {
                    if (!password_verify($password, (string)$account["password"])) {
                        throw new DomainException("That password does not match this existing account.");
                    }
                    $membership = $conn->prepare("
                        INSERT INTO organization_memberships (organization_id, user_id, role, access_code_hash)
                        VALUES (:organization_id, :user_id, :role, :access_code_hash)
                    ");
                    $membership->execute([
                        ":organization_id" => (int)$currentInvite["organization_id"],
                        ":user_id" => (int)$account["id"],
                        ":role" => $currentInvite["role"],
                        ":access_code_hash" => $currentInvite["access_code_hash"],
                    ]);
                } else {
                    $insertUser = $conn->prepare("
                        INSERT INTO users (organization_id, name, email, password, role)
                        VALUES (:organization_id, :name, :email, :password, :role)
                    ");
                    $insertUser->execute([
                        ":organization_id" => (int)$currentInvite["organization_id"],
                        ":name" => $nameValue,
                        ":email" => $currentInvite["email"],
                        ":password" => password_hash($password, PASSWORD_DEFAULT),
                        ":role" => $currentInvite["role"],
                    ]);
                    $account = [
                        "id" => (int)$conn->lastInsertId(),
                        "name" => $nameValue,
                    ];
                    $membership = $conn->prepare("
                        INSERT INTO organization_memberships (organization_id, user_id, role, access_code_hash)
                        VALUES (:organization_id, :user_id, :role, :access_code_hash)
                    ");
                    $membership->execute([
                        ":organization_id" => (int)$currentInvite["organization_id"],
                        ":user_id" => (int)$account["id"],
                        ":role" => $currentInvite["role"],
                        ":access_code_hash" => $currentInvite["access_code_hash"],
                    ]);
                }
                $accept = $conn->prepare("
                    UPDATE organization_invitations
                    SET accepted_at = CURRENT_TIMESTAMP,
                        access_code_hash = NULL
                    WHERE id = :id
                      AND organization_id = :organization_id
                      AND accepted_at IS NULL
                ");
                $accept->execute([
                    ":id" => (int)$currentInvite["id"],
                    ":organization_id" => (int)$currentInvite["organization_id"],
                ]);
                if ($accept->rowCount() !== 1) {
                    throw new DomainException("This invitation has already been used.");
                }
                $conn->commit();
                session_regenerate_id(true);
                $_SESSION["user_id"] = (int)$account["id"];
                $_SESSION["organization_id"] = (int)$currentInvite["organization_id"];
                $_SESSION["organization_name"] = (string)$invitation["organization_name"];
                $_SESSION["user_name"] = (string)$account["name"];
                $_SESSION["user_email"] = (string)$currentInvite["email"];
                $_SESSION["role"] = (string)$currentInvite["role"];
                $_SESSION["invitation_accept_csrf"] = bin2hex(random_bytes(32));
                $notice = "Your account is ready in " . (string)$invitation["organization_name"] . ".";
                $invitation = null;
                $token = "";
            } catch (DomainException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $error = $e->getMessage();
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("HelpDesk Pro invitation acceptance failed: " . $e->getMessage());
                $error = $e instanceof PDOException && $e->getCode() === "23000"
                    ? "This account already belongs to that workspace. Sign in and choose the workspace from the navigation."
                    : "We couldn't activate your account right now. Please try again.";
            }
        }
    }
}

$safeName = htmlspecialchars($nameValue, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$safeOrganization = htmlspecialchars((string)($invitation["organization_name"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$safeEmail = htmlspecialchars((string)($invitation["email"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept invitation | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/organization-access.css?v=1">
</head>
<body class="access-page">
    <main class="access-shell">
        <a class="access-brand" href="home.php">
            <img src="assets/images/samadhan-logo.svg" alt="" width="42" height="42">
            <span><strong>Samadhan</strong><small>Support &amp; Resolution Desk</small></span>
        </a>
        <section class="access-card">
            <span class="access-kicker">WORKSPACE INVITATION</span>
            <?php if ($notice !== ""): ?>
                <h1>You're in.</h1>
                <p class="access-intro"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></p>
                <a class="access-submit access-link-button" href="welcome.php">Open your workspace</a>
            <?php elseif (!$invitation): ?>
                <h1>Invitation unavailable</h1>
                <p class="access-intro"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></p>
                <a class="access-text-link" href="login.php">Back to sign in</a>
            <?php else: ?>
                <h1>Join <?= $safeOrganization ?></h1>
                <p class="access-intro"><?= !empty($invitation["existing_account"])
                    ? "Use your existing Samadhan account to securely join this additional workspace."
                    : "Create your account to securely join this support workspace." ?></p>
                <div class="access-invitee"><span>Invitation sent to</span><strong><?= $safeEmail ?></strong></div>
                <?php if ($error !== ""): ?>
                    <p class="access-message is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></p>
                <?php endif; ?>
                <form method="post" class="access-form">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["invitation_accept_csrf"], ENT_QUOTES, "UTF-8") ?>">
                    <?php if (empty($invitation["existing_account"])): ?>
                        <label for="inviteName">Your name</label>
                        <input id="inviteName" name="name" value="<?= $safeName ?>" maxlength="100" autocomplete="name" required>
                    <?php endif; ?>
                    <label for="invitePassword"><?= !empty($invitation["existing_account"]) ? "Current account password" : "Create a password" ?></label>
                    <input id="invitePassword" name="password" type="password" <?= empty($invitation["existing_account"]) ? 'minlength="12" autocomplete="new-password"' : 'autocomplete="current-password"' ?> required>
                    <?php if (empty($invitation["existing_account"])): ?>
                        <label for="inviteConfirmPassword">Confirm password</label>
                        <input id="inviteConfirmPassword" name="confirm_password" type="password" minlength="12" autocomplete="new-password" required>
                        <small class="access-hint">Use at least 12 characters. Your email address is set by the invitation.</small>
                    <?php else: ?>
                        <small class="access-hint">Your account and password stay the same; only the workspace access is added.</small>
                    <?php endif; ?>
                    <button class="access-submit" type="submit">Join workspace</button>
                </form>
            <?php endif; ?>
        </section>
        <footer class="access-footer"><a href="home.php">Samadhan</a><span>Private support workspaces for your organization.</span></footer>
    </main>
</body>
</html>
