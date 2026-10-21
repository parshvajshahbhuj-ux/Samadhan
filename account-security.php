<?php
require_once "auth.php";
require_once "db.php";
requireLogin();

if (empty($_SESSION["account_password_csrf"])) {
    $_SESSION["account_password_csrf"] = bin2hex(random_bytes(32));
}

$error = "";
$notice = (string)($_SESSION["account_password_notice"] ?? "");
unset($_SESSION["account_password_notice"]);
$userId = (int)($_SESSION["user_id"] ?? 0);
$userEmail = htmlspecialchars((string)($_SESSION["user_email"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    $csrfToken = (string)($_POST["csrf_token"] ?? "");
    if (!hash_equals((string)$_SESSION["account_password_csrf"], $csrfToken)) {
        http_response_code(400);
        $error = "Your request could not be verified. Refresh the page and try again.";
    } else {
        $password = (string)($_POST["password"] ?? "");
        $confirmPassword = (string)($_POST["confirm_password"] ?? "");
        $passwordLength = preg_match_all('/./us', $password);

        if ($passwordLength === false || $passwordLength < 12) {
            $error = "Choose a password with at least 12 characters.";
        } elseif (strlen($password) > 72) {
            $error = "Choose a password no longer than 72 bytes.";
        } elseif (!hash_equals($password, $confirmPassword)) {
            $error = "The passwords do not match.";
        } else {
            try {
                $updatePassword = $conn->prepare("
                    UPDATE users
                    SET password = :password
                    WHERE id = :user_id
                ");
                $updatePassword->execute([
                    ":password" => password_hash($password, PASSWORD_DEFAULT),
                    ":user_id" => $userId,
                ]);
                if ($updatePassword->rowCount() !== 1) {
                    throw new RuntimeException("The password update did not affect the signed-in account.");
                }

                session_regenerate_id(true);
                $_SESSION["account_password_notice"] = "Your password is set. You can now sign in with your email address and password.";
                header("Location: account-security.php", true, 303);
                exit;
            } catch (Throwable $exception) {
                error_log("HelpDesk Pro account password update failed: " . $exception->getMessage());
                $error = "Your password could not be updated. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account password | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=17">
    <link rel="stylesheet" href="assets/css/navigation.css?v=26">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=4">
    <link rel="stylesheet" href="assets/css/account-security.css?v=3">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="app-page-body account-security-page">
    <?php
    $activePage = "account-security";
    require "partials/navigation.php";
    ?>
    <main class="account-security-container page-transition-surface">
        <section class="account-security-heading" aria-labelledby="accountPasswordHeading">
            <div class="account-security-heading-copy">
                <p class="account-security-eyebrow"><span aria-hidden="true"></span> ACCOUNT SETTINGS</p>
                <h1 id="accountPasswordHeading">Password &amp; sign-in</h1>
                <p>Manage how you securely access your Samadhan workspace.</p>
            </div>
            <a class="account-security-back" href="welcome.php"><span aria-hidden="true">←</span> Back to workspace</a>
        </section>

        <?php if ($error !== ""): ?>
            <div class="account-password-message is-error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>
            </div>
        <?php endif; ?>
        <?php if ($notice !== ""): ?>
            <div class="account-password-message" role="status">
                <?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <div class="account-security-layout">
            <section class="account-password-card" aria-labelledby="passwordCardHeading">
                <div class="account-password-card-heading">
                    <div class="account-password-card-title">
                        <span class="account-password-icon" aria-hidden="true">Aa</span>
                        <div>
                            <span class="account-password-kicker">PASSWORD SIGN-IN</span>
                            <h2 id="passwordCardHeading">Set your account password</h2>
                        </div>
                    </div>
                    <span class="account-password-badge"><span aria-hidden="true"></span> Private to your account</span>
                </div>
                <p class="account-password-account">Account email <strong><?= $userEmail ?></strong></p>
                <form class="account-password-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["account_password_csrf"], ENT_QUOTES, "UTF-8") ?>">
                    <div class="account-password-field">
                        <label for="accountPassword">New password</label>
                        <input id="accountPassword" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>
                    </div>
                    <div class="account-password-field">
                        <label for="confirmAccountPassword">Confirm new password</label>
                        <input id="confirmAccountPassword" type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required>
                    </div>
                    <div class="account-password-form-footer">
                        <p><span aria-hidden="true">i</span> Use at least 12 characters. Your invitation code will remain available as another sign-in option.</p>
                        <button class="account-password-submit" type="submit">Save password <span aria-hidden="true">→</span></button>
                    </div>
                </form>
            </section>

            <aside class="account-security-guide" aria-labelledby="signInOptionsHeading">
                <span class="account-security-guide-icon" aria-hidden="true">↗</span>
                <p class="account-password-kicker">YOUR ACCESS</p>
                <h2 id="signInOptionsHeading">Two ways to sign in</h2>
                <p class="account-security-guide-copy">Choose whichever is easier whenever you return to Samadhan.</p>
                <div class="account-security-option">
                    <span class="account-security-option-mark" aria-hidden="true">Aa</span>
                    <span><strong>Email &amp; password</strong><small>A familiar sign-in for everyday access.</small></span>
                </div>
                <div class="account-security-option">
                    <span class="account-security-option-mark is-code" aria-hidden="true">#</span>
                    <span><strong>Invitation code</strong><small>Your workspace code remains active.</small></span>
                </div>
                <div class="account-security-guide-note"><span aria-hidden="true">✓</span> Your account and workspace access stay the same.</div>
            </aside>
        </div>

        <section class="account-security-benefits" aria-label="Password security tips">
            <article class="account-security-benefit">
                <span class="account-security-benefit-icon" aria-hidden="true">12+</span>
                <div><h2>Make it strong</h2><p>Choose a password with at least 12 characters.</p></div>
            </article>
            <article class="account-security-benefit">
                <span class="account-security-benefit-icon is-blue" aria-hidden="true">✦</span>
                <div><h2>Keep it unique</h2><p>A password you do not use for other accounts is safer.</p></div>
            </article>
            <article class="account-security-benefit">
                <span class="account-security-benefit-icon is-green" aria-hidden="true">✓</span>
                <div><h2>Stay in control</h2><p>You can update your password here whenever you need.</p></div>
            </article>
        </section>
    </main>
    <?php require "partials/footer.php"; ?>
</body>
</html>
