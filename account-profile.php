<?php
require_once "auth.php";
require_once "db.php";
requireLogin();

$userId = (int)($_SESSION["user_id"] ?? 0);
$profile = false;
$workspaces = [];
$profileError = "";

try {
    $profileQuery = $conn->prepare("
        SELECT name, email, created_at
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");
    $profileQuery->execute([":user_id" => $userId]);
    $profile = $profileQuery->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        throw new RuntimeException("The signed-in user profile could not be found.");
    }

    $workspaces = getUserOrganizations($conn, $userId);
} catch (Throwable $exception) {
    error_log("HelpDesk Pro profile lookup failed: " . $exception->getMessage());
    $profileError = "Your profile could not be loaded right now. Please refresh the page or try again later.";
}

$profileName = htmlspecialchars((string)($profile["name"] ?? $_SESSION["user_name"] ?? "User"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$profileEmail = htmlspecialchars((string)($profile["email"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$profileInitial = htmlspecialchars(strtoupper(substr((string)($profile["name"] ?? $_SESSION["user_name"] ?? "U"), 0, 1)), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$currentOrganizationId = getOrganizationId();
$roleLabels = [
    "owner" => "Owner",
    "admin" => "Main Admin",
    "support" => "Support Member",
    "user" => "Customer",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My profile | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=17">
    <link rel="stylesheet" href="assets/css/navigation.css?v=26">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=21">
    <link rel="stylesheet" href="assets/css/account-profile.css?v=1">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="app-page-body account-profile-page">
    <?php
    $activePage = "profile";
    require "partials/navigation.php";
    ?>
    <main class="account-profile-container page-transition-surface">
        <section class="account-profile-heading" aria-labelledby="profileHeading">
            <div>
                <p class="account-profile-eyebrow"><span aria-hidden="true"></span> ACCOUNT</p>
                <h1 id="profileHeading">Your profile</h1>
                <p>View the account details associated with your Samadhan sign-in.</p>
            </div>
            <a class="account-profile-back" href="welcome.php"><span aria-hidden="true">←</span> Back to workspace</a>
        </section>

        <?php if ($profileError !== ""): ?>
            <div class="account-profile-error" role="alert"><?= htmlspecialchars($profileError, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></div>
        <?php else: ?>
            <div class="account-profile-grid">
                <section class="account-profile-card" aria-labelledby="profileDetailsHeading">
                    <div class="account-profile-identity">
                        <span class="account-profile-avatar" aria-hidden="true"><?= $profileInitial ?></span>
                        <div class="account-profile-identity-copy">
                            <span class="account-profile-label">SIGNED-IN ACCOUNT</span>
                            <h2 id="profileDetailsHeading"><?= $profileName ?></h2>
                            <span class="account-profile-role"><?= htmlspecialchars(currentRoleLabel(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></span>
                        </div>
                    </div>
                    <dl class="account-profile-details">
                        <div>
                            <dt>Email address</dt>
                            <dd><?= $profileEmail ?></dd>
                        </div>
                        <?php if (!empty($profile["created_at"])): ?>
                            <div>
                                <dt>Account created</dt>
                                <dd><time datetime="<?= htmlspecialchars((string)$profile["created_at"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>"><?= htmlspecialchars(date("F j, Y", strtotime((string)$profile["created_at"])), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></time></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <a class="account-profile-security-link" href="account-security.php">Manage password &amp; sign-in <span aria-hidden="true">→</span></a>
                </section>

                <section class="account-profile-card account-profile-workspaces" aria-labelledby="profileWorkspacesHeading">
                    <div class="account-profile-section-heading">
                        <span class="account-profile-section-icon" aria-hidden="true">⌂</span>
                        <div>
                            <span class="account-profile-label">ACCESS</span>
                            <h2 id="profileWorkspacesHeading">Your workspaces</h2>
                        </div>
                    </div>
                    <?php if ($workspaces): ?>
                        <ul>
                            <?php foreach ($workspaces as $workspace): ?>
                                <?php
                                $workspaceId = (int)$workspace["organization_id"];
                                $workspaceRole = (string)$workspace["role"];
                                $workspaceLabel = $roleLabels[$workspaceRole] ?? "Member";
                                ?>
                                <li<?= $workspaceId === $currentOrganizationId ? ' class="is-current"' : "" ?>>
                                    <span class="account-profile-workspace-mark" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr((string)$workspace["name"], 0, 1)), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></span>
                                    <span class="account-profile-workspace-copy">
                                        <strong><?= htmlspecialchars((string)$workspace["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></strong>
                                        <small><?= htmlspecialchars($workspaceLabel, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?><?= $workspaceId === $currentOrganizationId ? " · Active workspace" : "" ?></small>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="account-profile-empty">No workspace access is currently associated with this account.</p>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </main>
    <?php require "partials/footer.php"; ?>
</body>
</html>
