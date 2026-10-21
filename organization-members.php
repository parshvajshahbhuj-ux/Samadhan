<?php
require_once "auth.php";
require_once "db.php";
requireLogin();
if (!isAdmin()) {
    http_response_code(403);
    exit("Admin access required.");
}
if (empty($_SESSION["organization_invitation_csrf"])) {
    $_SESSION["organization_invitation_csrf"] = bin2hex(random_bytes(32));
}
$organizationName = organizationName($conn, getOrganizationId());
$safeOrganizationName = htmlspecialchars($organizationName, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$safeUserName = htmlspecialchars((string)($_SESSION["user_name"] ?? "Administrator"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace access | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/navigation.css?v=26">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=17">
    <link rel="stylesheet" href="assets/css/organization-access.css?v=8">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="access-page app-page-body">
    <?php
    $activePage = "members";
    require "partials/navigation.php";
    ?>
    <main class="members-main page-transition-surface" data-csrf-token="<?= htmlspecialchars($_SESSION["organization_invitation_csrf"], ENT_QUOTES, "UTF-8") ?>">
        <section class="members-hero">
            <div>
                <span class="access-kicker">WORKSPACE ACCESS</span>
                <h1>Invite your people, <?= $safeUserName ?>.</h1>
                <p>Manage who can work in <strong><?= $safeOrganizationName ?></strong>. Invitations expire after seven days and can be revoked anytime.</p>
            </div>
            <span class="members-hero-mark" aria-hidden="true">↗</span>
        </section>

        <div class="members-layout">
            <section class="access-panel invite-panel" aria-labelledby="inviteHeading">
                <div class="access-panel-heading">
                    <div><span class="access-kicker">ADD A MEMBER</span><h2 id="inviteHeading">Send an invitation</h2></div>
                </div>
                <p class="panel-intro">Invite a customer or teammate by email. They can activate access and sign in with the personal code sent to them.</p>
                <form id="organizationInviteForm" class="access-form">
                    <label for="inviteEmail">Email address</label>
                    <input id="inviteEmail" type="email" name="email" maxlength="150" autocomplete="email" placeholder="name@company.com" required>
                    <label for="inviteRole">Workspace role</label>
                    <select id="inviteRole" name="role">
                        <option value="user">Customer — view and reply to own requests</option>
                        <option value="support">Support Member — manage organization tickets</option>
                    </select>
                    <button class="access-submit" type="submit">Send invitation <span aria-hidden="true">→</span></button>
                    <p class="access-status" id="inviteStatus" role="status" aria-live="polite"></p>
                </form>
                <aside class="invite-guide" aria-label="What happens after you send an invitation">
                    <div class="invite-guide-heading">
                        <span class="invite-guide-icon" aria-hidden="true">✓</span>
                        <div>
                            <strong>What happens next</strong>
                            <span>Simple, secure workspace access</span>
                        </div>
                    </div>
                    <ol class="invite-guide-steps">
                        <li><span>1</span><p>We email them a personal sign-in code.</p></li>
                        <li><span>2</span><p>They use it to join your workspace.</p></li>
                        <li><span>3</span><p>The invitation stays valid for seven days.</p></li>
                    </ol>
                    <p class="invite-guide-footnote">You can resend or revoke an invitation at any time.</p>
                </aside>
            </section>

            <section class="access-panel" aria-labelledby="membersHeading">
                <div class="access-panel-heading">
                    <div><span class="access-kicker">CURRENT ACCESS</span><h2 id="membersHeading">Workspace members</h2></div>
                    <span class="access-count" id="memberCount">—</span>
                </div>
                <div class="access-list" id="memberList"><p class="access-loading">Loading workspace access…</p></div>
                <p class="access-status" id="memberActionStatus" role="status" aria-live="polite"></p>
            </section>
        </div>

        <section class="access-panel pending-panel" aria-labelledby="pendingHeading">
            <div class="access-panel-heading">
                <div><span class="access-kicker">INVITATIONS</span><h2 id="pendingHeading">Pending invitations</h2></div>
            </div>
            <div class="access-list" id="invitationList"><p class="access-loading">Loading invitations…</p></div>
            <p class="access-status" id="invitationActionStatus" role="status" aria-live="polite"></p>
        </section>
    </main>
    <?php require "partials/footer.php"; ?>
    <script src="assets/js/organization-members.js?v=4" defer></script>
</body>
</html>
