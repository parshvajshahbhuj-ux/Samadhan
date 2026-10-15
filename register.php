<?php

session_start();
require_once "db.php";
require_once "email_helper.php";

if (isset($_SESSION["user_id"])) {
    header("Location: welcome.php");
    exit;
}

if (!isset($_SESSION["registration_csrf"])) {
    $_SESSION["registration_csrf"] = bin2hex(random_bytes(32));
}

$storedRegistration = $_SESSION["pending_registration"] ?? null;
if (
    is_array($storedRegistration) &&
    (
        ($storedRegistration["delivery_confirmed"] ?? false) !== true ||
        (int)($storedRegistration["expires_at"] ?? 0) < time()
    )
) {
    unset($_SESSION["pending_registration"]);
    unset($_SESSION["registration_last_sent"]);
    $storedRegistration = null;
}

$error = "";
$notice = "";
$registration = $storedRegistration;
$organizationValue = is_array($registration) ? (string)($registration["organization_name"] ?? "") : "";
$nameValue = is_array($registration) ? (string)($registration["name"] ?? "") : "";
$emailValue = is_array($registration) ? (string)($registration["email"] ?? "") : "";
$action = (string)($_POST["action"] ?? "");

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    if (!hash_equals($_SESSION["registration_csrf"], $csrfToken)) {
        http_response_code(400);
        $error = "Your request could not be verified. Refresh the page and try again.";
    } elseif ($action === "start") {
        $organizationValue = trim((string)($_POST["organization_name"] ?? ""));
        $nameValue = trim((string)($_POST["name"] ?? ""));
        $emailValue = strtolower(trim((string)($_POST["email"] ?? "")));
        $password = (string)($_POST["password"] ?? "");
        $confirmPassword = (string)($_POST["confirm_password"] ?? "");

        if ($organizationValue === "" || strlen($organizationValue) > 150) {
            $error = "Enter your company, organization, or shop name (up to 150 characters).";
        } elseif ($nameValue === "" || strlen($nameValue) > 100) {
            $error = "Enter your name (up to 100 characters).";
        } elseif (strlen($emailValue) > 150 || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $error = "Enter a valid email address.";
        } elseif (strlen($password) < 12) {
            $error = "Choose a password with at least 12 characters.";
        } elseif (!hash_equals($password, $confirmPassword)) {
            $error = "The passwords do not match.";
        } elseif (time() - (int)($_SESSION["registration_last_sent"] ?? 0) < 60) {
            $error = "Please wait a minute before requesting another verification code.";
        } else {
            $existingUser = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $existingUser->execute([":email" => $emailValue]);

            if ($existingUser->fetchColumn()) {
                $error = "An account with that email already exists. Please sign in instead.";
            } else {
                $code = str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);
                $pendingRegistration = [
                    "organization_name" => $organizationValue,
                    "name" => $nameValue,
                    "email" => $emailValue,
                    "password_hash" => password_hash($password, PASSWORD_DEFAULT),
                    "code_hash" => password_hash($code, PASSWORD_DEFAULT),
                    "expires_at" => time() + 600,
                    "sent_at" => time(),
                    "attempts" => 0,
                ];
                unset($password, $confirmPassword);

                $escapedName = htmlspecialchars($nameValue, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
                $emailSent = sendHelpDeskEmail(
                    $emailValue,
                    "Samadhan email verification code",
                    "<p>Hello {$escapedName},</p><p>Your Samadhan verification code is:</p>"
                        . "<p style=\"font-size:28px;font-weight:bold;letter-spacing:6px\">{$code}</p>"
                        . "<p>This code expires in 10 minutes. If you did not request an account, you can ignore this email.</p>"
                );

                if ($emailSent) {
                    $pendingRegistration["delivery_confirmed"] = true;
                    $pendingRegistration["sent_at"] = time();
                    $_SESSION["pending_registration"] = $pendingRegistration;
                    $_SESSION["registration_last_sent"] = time();
                    $registration = $pendingRegistration;
                    $notice = "We sent a 6-digit verification code to " . $emailValue . ".";
                } else {
                    unset($_SESSION["pending_registration"]);
                    unset($_SESSION["registration_last_sent"]);
                    $registration = null;
                    $error = "We couldn't send the verification email. Please check the email service configuration and try again.";
                }
            }
        }
    } elseif ($action === "verify") {
        $registration = $_SESSION["pending_registration"] ?? null;
        $code = trim((string)($_POST["verification_code"] ?? ""));

        if (!is_array($registration)) {
            $error = "Start account registration again to request a new verification code.";
        } elseif ((int)($registration["expires_at"] ?? 0) < time()) {
            unset($_SESSION["pending_registration"]);
            $registration = null;
            $error = "That verification code has expired. Enter your details to request a new one.";
        } elseif ((int)($registration["attempts"] ?? 0) >= 5) {
            unset($_SESSION["pending_registration"]);
            $registration = null;
            $error = "Too many incorrect attempts. Enter your details to request a new verification code.";
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            $error = "Enter the 6-digit code from your email.";
        } elseif (!password_verify($code, (string)$registration["code_hash"])) {
            $_SESSION["pending_registration"]["attempts"] = (int)$registration["attempts"] + 1;
            $registration = $_SESSION["pending_registration"];
            $error = "That verification code is incorrect.";
        } else {
            try {
                $conn->beginTransaction();
                $organizationName = trim((string)$registration["organization_name"]);
                $slugBase = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $organizationName);
                $slugBase = strtolower((string)$slugBase);
                $slugBase = trim(preg_replace('/[^a-z0-9]+/', '-', $slugBase) ?? "", "-");
                $slugBase = substr($slugBase !== "" ? $slugBase : "organization", 0, 60);
                $organizationSlug = $slugBase . "-" . bin2hex(random_bytes(5));
                $createOrganization = $conn->prepare("
                    INSERT INTO organizations (name, slug)
                    VALUES (:name, :slug)
                ");
                $createOrganization->execute([
                    ":name" => $organizationName,
                    ":slug" => $organizationSlug,
                ]);
                $organizationId = (int)$conn->lastInsertId();
                $createUser = $conn->prepare("
                    INSERT INTO users (organization_id, name, email, password, role)
                    VALUES (:organization_id, :name, :email, :password, 'admin')
                ");
                $createUser->execute([
                    ":organization_id" => $organizationId,
                    ":name" => $registration["name"],
                    ":email" => $registration["email"],
                    ":password" => $registration["password_hash"],
                ]);
                $userId = (int)$conn->lastInsertId();
                $createMembership = $conn->prepare("
                    INSERT INTO organization_memberships (organization_id, user_id, role)
                    VALUES (:organization_id, :user_id, 'admin')
                ");
                $createMembership->execute([
                    ":organization_id" => $organizationId,
                    ":user_id" => $userId,
                ]);
                $logRegistration = $conn->prepare("
                    INSERT INTO activity_logs
                        (organization_id, user_id, user_name, user_role, ticket_id, action, description)
                    VALUES
                        (:organization_id, :user_id, :user_name, 'admin', NULL, 'REGISTER', :description)
                ");
                $logRegistration->execute([
                    ":organization_id" => $organizationId,
                    ":user_id" => $userId,
                    ":user_name" => (string)$registration["name"],
                    ":description" => "Company workspace \"" . $organizationName . "\" was registered.",
                ]);
                $conn->commit();
                unset($_SESSION["pending_registration"]);
                $_SESSION["registration_csrf"] = bin2hex(random_bytes(32));
                $registration = null;
                $nameValue = "";
                $emailValue = "";
                $notice = "Your email is verified and your organization workspace is ready. You can now sign in as its administrator.";
            } catch (PDOException $exception) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("HelpDesk Pro account registration failed: " . $exception->getMessage());
                if ($exception->getCode() === "23000") {
                    unset($_SESSION["pending_registration"]);
                    $registration = null;
                    $error = "An account with that email already exists. Please sign in instead.";
                } else {
                    $error = "We couldn't create your account right now. Please try again later.";
                }
            }
        }
    } elseif ($action === "resend") {
        $registration = $_SESSION["pending_registration"] ?? null;
        if (!is_array($registration)) {
            $error = "Start account registration again to request a new verification code.";
        } elseif ((int)($registration["expires_at"] ?? 0) < time()) {
            unset($_SESSION["pending_registration"]);
            $registration = null;
            $error = "That registration has expired. Enter your details to request a new code.";
        } elseif (time() - (int)($registration["sent_at"] ?? 0) < 60) {
            $error = "Please wait a minute before requesting another code.";
        } else {
            $code = str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);
            $pendingRegistration = $registration;
            $pendingRegistration["code_hash"] = password_hash($code, PASSWORD_DEFAULT);
            $pendingRegistration["sent_at"] = time();
            $pendingRegistration["expires_at"] = time() + 600;
            $pendingRegistration["attempts"] = 0;
            $escapedName = htmlspecialchars((string)$registration["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $emailSent = sendHelpDeskEmail(
                (string)$registration["email"],
                "Samadhan email verification code",
                "<p>Hello {$escapedName},</p><p>Your new Samadhan verification code is:</p>"
                    . "<p style=\"font-size:28px;font-weight:bold;letter-spacing:6px\">{$code}</p>"
                    . "<p>This code expires in 10 minutes. If you did not request an account, you can ignore this email.</p>"
            );
            if ($emailSent) {
                $pendingRegistration["delivery_confirmed"] = true;
                $_SESSION["pending_registration"] = $pendingRegistration;
                $_SESSION["registration_last_sent"] = time();
                $registration = $pendingRegistration;
                $notice = "A new verification code was sent to " . $registration["email"] . ".";
            } else {
                unset($_SESSION["pending_registration"]);
                unset($_SESSION["registration_last_sent"]);
                $registration = null;
                $error = "We couldn't send the verification email. Please check the email service configuration and try again.";
            }
        }
    } elseif ($action === "cancel") {
        unset($_SESSION["pending_registration"]);
        $registration = null;
        $organizationValue = "";
        $nameValue = "";
        $emailValue = "";
        $notice = "Registration cancelled.";
    } else {
        http_response_code(400);
        $error = "Choose a valid registration action.";
    }
}

$hasPendingRegistration = is_array($registration);
$safeOrganization = htmlspecialchars($organizationValue, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$safeName = htmlspecialchars($nameValue, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$safeEmail = htmlspecialchars($emailValue, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register your organization | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/navigation.css?v=18">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=3">
    <link rel="stylesheet" href="assets/css/login.css?v=3">
    <link rel="stylesheet" href="assets/css/register.css?v=2">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="app-page-body">
<main class="login-main page-transition-surface">
    <section class="login-shell register-shell" aria-labelledby="registerHeading">
        <aside class="login-aside">
            <a class="login-brand" href="home.php" aria-label="Samadhan home">
                <img src="assets/images/samadhan-logo.svg" alt="" width="46" height="46">
                <span class="login-brand-copy">
                    <strong>Samadhan</strong>
                    <small>Support &amp; Resolution Desk</small>
                </span>
            </a>
            <div class="login-aside-copy">
                <span class="login-eyebrow"><span></span> YOUR WORKSPACE STARTS HERE</span>
                <h2>Better support<br>starts together.</h2>
                <p>Create a private space for your organization to manage requests and keep every conversation moving.</p>
            </div>
            <div class="login-aside-note">
                <span aria-hidden="true">✓</span>
                <p><strong>Private by design</strong><br>Your workspace belongs to your organization</p>
            </div>
            <div class="login-aside-orbit login-aside-orbit-one" aria-hidden="true"></div>
            <div class="login-aside-orbit login-aside-orbit-two" aria-hidden="true"></div>
        </aside>

        <div class="login-content register-content">
            <header class="login-heading">
                <div class="login-heading-top">
                    <span class="login-content-kicker"><?= $hasPendingRegistration ? "EMAIL VERIFICATION" : "GET STARTED" ?></span>
                    <a class="login-home-link" href="home.php"><span aria-hidden="true">←</span> Back to home</a>
                </div>
                <h1 id="registerHeading"><?= $hasPendingRegistration ? "Verify your email" : "Create your workspace" ?></h1>
            <p>
                <?= $hasPendingRegistration
                    ? "Enter the 6-digit code sent to " . $safeEmail . "."
                    : "Set up a private support workspace for your company, organization, or shop." ?>
            </p>
            </header>

            <?php if ($error !== ""): ?>
                <div class="register-message error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></div>
            <?php endif; ?>
            <?php if ($notice !== ""): ?>
                <div class="register-message" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></div>
            <?php endif; ?>

            <?php if ($hasPendingRegistration): ?>
                <section class="register-form-panel" aria-label="Verify your email address">
                    <div class="register-panel-heading">
                        <span class="register-step">01</span>
                        <div>
                            <h2>Confirm your email</h2>
                            <p>Enter the secure 6-digit code we sent you.</p>
                        </div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["registration_csrf"], ENT_QUOTES, "UTF-8") ?>">
                        <input type="hidden" name="action" value="verify">
                        <div class="register-field">
                            <label for="verificationCode">Verification code</label>
                            <input id="verificationCode" type="text" name="verification_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit code" required autofocus>
                        </div>
                        <button class="login-submit register-submit" type="submit">Verify email and activate workspace <span aria-hidden="true">→</span></button>
                    </form>
                    <div class="register-pending-actions">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["registration_csrf"], ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="action" value="resend">
                            <button class="register-secondary" type="submit">Resend verification code</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["registration_csrf"], ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="action" value="cancel">
                            <button class="register-secondary" type="submit">Use a different email</button>
                        </form>
                    </div>
                </section>
            <?php else: ?>
                <section class="register-form-panel" aria-label="Workspace registration">
                    <div class="register-panel-heading">
                        <span class="register-step">01</span>
                        <div>
                            <h2>Workspace details</h2>
                            <p>Set up your organization and administrator account.</p>
                        </div>
                    </div>
                    <form class="register-fields" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["registration_csrf"], ENT_QUOTES, "UTF-8") ?>">
                        <input type="hidden" name="action" value="start">
                        <div class="register-field">
                            <label for="organizationName">Company or organization name</label>
                            <input id="organizationName" type="text" name="organization_name" maxlength="150" autocomplete="organization" value="<?= $safeOrganization ?>" required>
                        </div>
                        <div class="register-field">
                            <label for="registerName">Workspace administrator name</label>
                            <input id="registerName" type="text" name="name" maxlength="100" autocomplete="name" value="<?= $safeName ?>" required>
                        </div>
                        <div class="register-field register-field-wide">
                            <label for="registerEmail">Email address</label>
                            <input id="registerEmail" type="email" name="email" maxlength="150" autocomplete="email" value="<?= $safeEmail ?>" required>
                        </div>
                        <div class="register-field">
                            <label for="registerPassword">Password</label>
                            <input id="registerPassword" type="password" name="password" minlength="12" autocomplete="new-password" required>
                        </div>
                        <div class="register-field">
                            <label for="confirmPassword">Confirm password</label>
                            <input id="confirmPassword" type="password" name="confirm_password" minlength="12" autocomplete="new-password" required>
                        </div>
                        <button class="login-submit register-submit register-field-wide" type="submit">Create workspace &amp; verify email <span aria-hidden="true">→</span></button>
                    </form>
                </section>
            <?php endif; ?>

            <p class="login-signup register-footer">
                Already have an account? <a href="login.php">Back to sign in</a>
            </p>
        </div>
    </section>
</main>
</body>
</html>
