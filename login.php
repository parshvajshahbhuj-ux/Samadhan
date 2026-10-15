<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once "db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: welcome.php");
    exit;
}

$error = "";
$nameValue = trim((string)($_POST["name"] ?? ""));
if (empty($_SESSION["login_csrf"])) {
    $_SESSION["login_csrf"] = bin2hex(random_bytes(32));
}

function setLoginSession(array $user): void
{
    session_regenerate_id(true);
    $_SESSION["user_id"] = (int)$user["id"];
    $_SESSION["organization_id"] = (int)$user["organization_id"];
    $_SESSION["organization_name"] = (string)$user["organization_name"];
    $_SESSION["user_name"] = (string)$user["name"];
    $_SESSION["user_email"] = (string)$user["email"];
    $_SESSION["role"] = (string)$user["role"];
}

function recordLoginActivity(PDO $conn, array $user): void
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs
                (organization_id, user_id, user_name, user_role, ticket_id, action, description)
            VALUES
                (:organization_id, :user_id, :user_name, :user_role, NULL, 'LOGIN', :description)
        ");
        $stmt->execute([
            ":organization_id" => (int)$user["organization_id"],
            ":user_id" => (int)$user["id"],
            ":user_name" => (string)$user["name"],
            ":user_role" => (string)$user["role"],
            ":description" => "User signed in to the company workspace.",
        ]);
    } catch (Throwable $e) {
        error_log("HelpDesk Pro sign-in activity logging failed: " . $e->getMessage());
    }
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    if (!hash_equals((string)$_SESSION["login_csrf"], $csrfToken)) {
        http_response_code(400);
        $error = "Your request could not be verified. Refresh the page and try again.";
    } else {
        $action = (string)($_POST["action"] ?? "password");
        if ($action === "invitation_code") {
            $rawCode = strtoupper((string)($_POST["invitation_code"] ?? ""));
            $code = preg_replace('/[\s-]+/', '', $rawCode);
            if (!is_string($code) || !preg_match('/^[2-9A-HJ-NP-Z]{20}$/D', $code)) {
                $error = "Enter the 20-character code from your invitation email.";
            } elseif (strlen($nameValue) > 100) {
                $error = "Your name must be 100 characters or fewer.";
            } else {
                $codeHash = hash("sha256", $code);
                try {
                    $membershipLogin = $conn->prepare("
                        SELECT users.id, users.name, users.email,
                            membership.organization_id, membership.role,
                            organizations.name AS organization_name
                        FROM organization_memberships membership
                        INNER JOIN users ON users.id = membership.user_id
                        INNER JOIN organizations ON organizations.id = membership.organization_id
                        WHERE membership.access_code_hash = :access_code_hash
                        LIMIT 1
                    ");
                    $membershipLogin->execute([":access_code_hash" => $codeHash]);
                    $user = $membershipLogin->fetch(PDO::FETCH_ASSOC);

                    if (!$user) {
                        $conn->beginTransaction();
                        $lock = $conn->prepare("
                            SELECT invitation.id, invitation.organization_id, invitation.email,
                                invitation.role, invitation.access_code_hash,
                                organizations.name AS organization_name
                            FROM organization_invitations invitation
                            INNER JOIN organizations ON organizations.id = invitation.organization_id
                            WHERE invitation.access_code_hash = :access_code_hash
                              AND invitation.accepted_at IS NULL
                              AND invitation.expires_at > CURRENT_TIMESTAMP
                            LIMIT 1
                            FOR UPDATE
                        ");
                        $lock->execute([":access_code_hash" => $codeHash]);
                        $invitation = $lock->fetch(PDO::FETCH_ASSOC);
                        if (!$invitation) {
                            $conn->rollBack();
                            $error = "This invitation code is invalid, expired, or has been replaced.";
                        } else {
                            $accountLookup = $conn->prepare("
                                SELECT id, name, email
                                FROM users
                                WHERE email = :email
                                LIMIT 1
                            ");
                            $accountLookup->execute([":email" => $invitation["email"]]);
                            $account = $accountLookup->fetch(PDO::FETCH_ASSOC);

                            if (!$account) {
                                $displayName = $nameValue;
                                if ($displayName === "") {
                                    $emailName = strstr((string)$invitation["email"], "@", true);
                                    $displayName = is_string($emailName) && $emailName !== ""
                                        ? ucwords(str_replace([".", "_", "-"], " ", $emailName))
                                        : "New member";
                                }
                                $createAccount = $conn->prepare("
                                    INSERT INTO users (organization_id, name, email, password, role)
                                    VALUES (:organization_id, :name, :email, :password, :role)
                                ");
                                $createAccount->execute([
                                    ":organization_id" => (int)$invitation["organization_id"],
                                    ":name" => $displayName,
                                    ":email" => (string)$invitation["email"],
                                    ":password" => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                                    ":role" => (string)$invitation["role"],
                                ]);
                                $account = [
                                    "id" => (int)$conn->lastInsertId(),
                                    "name" => $displayName,
                                    "email" => (string)$invitation["email"],
                                ];
                            }

                            $membership = $conn->prepare("
                                INSERT INTO organization_memberships
                                    (organization_id, user_id, role, access_code_hash)
                                VALUES
                                    (:organization_id, :user_id, :role, :access_code_hash)
                                ON DUPLICATE KEY UPDATE
                                    role = VALUES(role),
                                    access_code_hash = VALUES(access_code_hash)
                            ");
                            $membership->execute([
                                ":organization_id" => (int)$invitation["organization_id"],
                                ":user_id" => (int)$account["id"],
                                ":role" => (string)$invitation["role"],
                                ":access_code_hash" => $codeHash,
                            ]);

                            $accept = $conn->prepare("
                                UPDATE organization_invitations
                                SET accepted_at = CURRENT_TIMESTAMP,
                                    access_code_hash = NULL
                                WHERE id = :id
                                  AND accepted_at IS NULL
                                  AND expires_at > CURRENT_TIMESTAMP
                            ");
                            $accept->execute([":id" => (int)$invitation["id"]]);
                            if ($accept->rowCount() !== 1) {
                                throw new RuntimeException("Invitation was no longer active during acceptance.");
                            }
                            $conn->commit();
                            $user = [
                                "id" => (int)$account["id"],
                                "name" => (string)$account["name"],
                                "email" => (string)$account["email"],
                                "organization_id" => (int)$invitation["organization_id"],
                                "role" => (string)$invitation["role"],
                                "organization_name" => (string)$invitation["organization_name"],
                            ];
                        }
                    }

                    if ($user) {
                        setLoginSession($user);
                        recordLoginActivity($conn, $user);
                        header("Location: welcome.php");
                        exit;
                    }
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    error_log("HelpDesk Pro invitation-code sign-in failed: " . $e->getMessage());
                    $error = "We couldn't sign you in with that invitation code. Check it and try again, or ask your workspace administrator for a new code.";
                }
            }
        } elseif ($action === "password") {
            $email = trim((string)($_POST["email"] ?? ""));
            $password = (string)($_POST["password"] ?? "");
            if ($email === "" || $password === "") {
                $error = "Email and password are required.";
            } else {
                $stmt = $conn->prepare("
                    SELECT
                        users.id,
                        users.organization_id,
                        users.name,
                        users.email,
                        users.password,
                        membership.role,
                        organizations.name AS organization_name
                    FROM users
                    INNER JOIN organization_memberships membership
                        ON membership.user_id = users.id
                        AND membership.organization_id = users.organization_id
                    INNER JOIN organizations
                        ON organizations.id = membership.organization_id
                    WHERE users.email = :email
                    LIMIT 1
                ");
                $stmt->execute([":email" => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user["password"])) {
                    setLoginSession($user);
                    recordLoginActivity($conn, $user);
                    header("Location: welcome.php");
                    exit;
                }
                $error = "Invalid email or password.";
            }
        } else {
            http_response_code(400);
            $error = "Choose a valid sign-in method.";
        }

    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sign in | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/navigation.css?v=20">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
    <style>

        * {
            box-sizing: border-box;
        }

        body.app-page-body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(ellipse at 15% 18%, rgba(191, 219, 254, .55), transparent 34%),
                radial-gradient(ellipse at 85% 82%, rgba(219, 234, 254, .7), transparent 32%),
                #f4f7fb;
            color: #0f172a;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        }

        .login-main {
            display: grid;
            flex: 1;
            place-items: center;
            padding: 36px 20px;
        }

        .login-box {
            width: min(100%, 440px);
            padding: clamp(28px, 5vw, 42px);
            border: 1px solid rgba(226, 232, 240, .9);
            border-radius: 18px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 24px 65px rgba(15, 23, 42, .12);
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-bottom: 28px;
        }

        .login-brand img {
            width: 52px;
            height: 52px;
            filter: drop-shadow(0 6px 12px rgba(37, 99, 235, .2));
        }

        .login-brand-copy h1 {
            margin: 0;
            color: #0f172a;
            font-size: 25px;
            letter-spacing: -.035em;
        }

        .login-brand-copy p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .login-heading {
            margin-bottom: 23px;
        }

        .login-heading h2 {
            margin: 0 0 6px;
            color: #172033;
            font-size: 21px;
        }

        .login-heading p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .login-error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #fecaca;
            border-radius: 9px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.45;
        }

        .login-field {
            margin-top: 17px;
        }

        .login-field label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
            font-size: 13px;
            font-weight: 650;
        }

        .login-field label span {
            color: #64748b;
            font-size: 11px;
            font-weight: 450;
        }

        .login-field input {
            width: 100%;
            min-height: 47px;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            outline: none;
            background: #fff;
            color: #0f172a;
            font: inherit;
            font-size: 14px;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .login-field input::placeholder {
            color: #94a3b8;
        }

        .login-field input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .13);
        }

        .login-submit {
            width: 100%;
            min-height: 47px;
            margin-top: 24px;
            border: 1px solid #2563eb;
            border-radius: 9px;
            background: linear-gradient(135deg, #3478f6, #2158dc);
            box-shadow: 0 6px 14px rgba(37, 99, 235, .2);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .login-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 9px 18px rgba(37, 99, 235, .26);
        }

        .login-code-divider {
            display: flex;
            align-items: center;
            gap: 13px;
            margin: 22px 0 5px;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 700;
        }

        .login-code-divider::before,
        .login-code-divider::after {
            height: 1px;
            flex: 1;
            background: #e2e8f0;
            content: "";
        }

        .login-code-hint {
            margin: 11px 0 0;
            color: #64748b;
            font-size: 11px;
            line-height: 1.5;
        }

        .login-code-submit {
            margin-top: 14px;
            border-color: #087da5;
            background: linear-gradient(135deg, #118ba8, #08739a);
            box-shadow: 0 6px 14px rgba(8, 127, 165, .16);
        }

        .login-code-submit:hover {
            box-shadow: 0 9px 18px rgba(8, 127, 165, .22);
        }

        .login-signup {
            margin: 20px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
            text-align: center;
        }

        .login-signup a {
            color: #1d4ed8;
            font-weight: 700;
            text-decoration: none;
        }

        .login-signup a:hover {
            text-decoration: underline;
        }

        @media (max-width: 520px) {
            .login-main {
                padding: 24px 15px;
            }

            .login-box {
                padding: 27px 22px;
                border-radius: 15px;
            }
        }

    </style>
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=3">
    <link rel="stylesheet" href="assets/css/login.css?v=3">

</head>


<body class="app-page-body">

<main class="login-main page-transition-surface">
<section class="login-shell" aria-labelledby="loginHeading">
    <aside class="login-aside">
        <a class="login-brand" href="home.php" aria-label="Samadhan home">
            <img src="assets/images/samadhan-logo.svg" alt="" width="48" height="48">
            <span class="login-brand-copy">
                <strong>Samadhan</strong>
                <small>Support &amp; Resolution Desk</small>
            </span>
        </a>
        <div class="login-aside-copy">
            <span class="login-eyebrow"><span></span> YOUR SUPPORT WORKSPACE</span>
            <h2>Every request.<br>Moving forward.</h2>
            <p>A better way to stay connected, get help, and keep every conversation on track.</p>
        </div>
        <div class="login-aside-note">
            <span aria-hidden="true">✓</span>
            <p><strong>One secure sign-in</strong><br>for your organization workspace</p>
        </div>
        <div class="login-aside-orbit login-aside-orbit-one" aria-hidden="true"></div>
        <div class="login-aside-orbit login-aside-orbit-two" aria-hidden="true"></div>
    </aside>

    <div class="login-content">
        <header class="login-heading">
            <div class="login-heading-top">
                <span class="login-content-kicker">MEMBER ACCESS</span>
                <a class="login-home-link" href="home.php"><span aria-hidden="true">←</span> Back to home</a>
            </div>
            <h1 id="loginHeading">Welcome back</h1>
            <p>Sign in to continue to your support workspace.</p>
        </header>

        <?php if ($error): ?>
            <div class="login-error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <div class="login-methods">
            <form id="loginForm" class="login-method" method="POST">
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["login_csrf"], ENT_QUOTES, "UTF-8") ?>">
                <div class="login-method-heading">
                    <span class="login-method-number">01</span>
                    <div>
                        <h2>Use your password</h2>
                        <p>For existing accounts</p>
                    </div>
                </div>
                <div class="login-field">
                    <label for="loginEmail">Email address</label>
                    <input
                        id="loginEmail"
                        type="email"
                        name="email"
                        placeholder="you@company.com"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="login-field">
                    <label for="loginPassword">Password</label>
                    <input
                        id="loginPassword"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button class="login-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
            </form>

            <div class="login-method-divider" aria-hidden="true"><span>OR</span></div>

            <form id="invitationCodeForm" class="login-method login-code-method" method="POST">
                <input type="hidden" name="action" value="invitation_code">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["login_csrf"], ENT_QUOTES, "UTF-8") ?>">
                <div class="login-method-heading">
                    <span class="login-method-number login-method-number-code">02</span>
                    <div>
                        <h2>Use an invitation code</h2>
                        <p>For invited workspace members</p>
                    </div>
                </div>
                <div class="login-field">
                    <label for="invitationCode">Your personal code</label>
                    <input
                        id="invitationCode"
                        type="text"
                        name="invitation_code"
                        value="<?= htmlspecialchars((string)($_POST["invitation_code"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>"
                        placeholder="XXXX-XXXX-XXXX-XXXX-XXXX"
                        autocomplete="one-time-code"
                        autocapitalize="characters"
                        maxlength="24"
                        pattern="[2-9A-HJ-NP-Z2-9a-hj-np-z\s-]{20,24}"
                        required
                    >
                </div>
                <div class="login-field">
                    <label for="invitationName">Your name <span>New accounts only</span></label>
                    <input
                        id="invitationName"
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($nameValue, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>"
                        maxlength="100"
                        autocomplete="name"
                        placeholder="Your name"
                    >
                </div>
                <p class="login-code-hint"><span aria-hidden="true">●</span> Keep your code private. It gives access to your invited workspace.</p>
                <button class="login-submit login-code-submit" type="submit">Continue with code <span aria-hidden="true">→</span></button>
            </form>
        </div>

        <p class="login-signup">
            Need an invitation? Contact your workspace administrator.
            <span>New to Samadhan?</span> <a href="register.php">Register your organization</a>
        </p>
    </div>
</section>
</main>

</body>

</html>