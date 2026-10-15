<?php
$navigationName = htmlspecialchars($_SESSION["user_name"] ?? "User", ENT_QUOTES, "UTF-8");
$navigationRole = htmlspecialchars(currentRoleLabel(), ENT_QUOTES, "UTF-8");
$navigationInitial = htmlspecialchars(
    strtoupper(substr((string)($_SESSION["user_name"] ?? "U"), 0, 1)),
    ENT_QUOTES,
    "UTF-8"
);
$activePage = $activePage ?? "";
$showNewTicketButton = $showNewTicketButton ?? false;
$userWorkspaces = getUserOrganizations($conn, (int)($_SESSION["user_id"] ?? 0));
if (count($userWorkspaces) > 1 && empty($_SESSION["workspace_switch_csrf"])) {
    $_SESSION["workspace_switch_csrf"] = bin2hex(random_bytes(32));
}
if (!isSupportStaff() && empty($_SESSION["user_notifications_csrf"])) {
    $_SESSION["user_notifications_csrf"] = bin2hex(random_bytes(32));
}
?>
<header class="site-navbar">
    <div class="site-navbar-inner">
        <a class="site-brand" href="welcome.php" aria-label="Samadhan home">
            <img class="site-brand-logo" src="assets/images/samadhan-logo.svg" alt="" width="42" height="42">
            <span class="site-brand-copy">
                <strong>Samadhan</strong>
                <small>Support &amp; Resolution Desk</small>
            </span>
        </a>

        <nav class="site-nav-links" aria-label="Main navigation">
            <a href="welcome.php"<?= $activePage === "welcome" ? ' aria-current="page"' : "" ?>>Welcome</a>
            <?php if (isAdmin()): ?>
                <a href="organization-members.php"<?= $activePage === "members" ? ' aria-current="page"' : "" ?>>Workspace Access</a>
            <?php endif; ?>
            <a href="index.php"<?= $activePage === "dashboard" ? ' aria-current="page"' : "" ?>>Dashboard</a>
            <a href="analytics.php"<?= $activePage === "analytics" ? ' aria-current="page"' : "" ?>>Analytics &amp; Reports</a>
            <?php if (isSupportStaff()): ?>
                <a href="support-inquiries.php"<?= $activePage === "requests" ? ' aria-current="page"' : "" ?>>Customer Requests</a>
                <a href="activity-log.php"<?= $activePage === "activity" ? ' aria-current="page"' : "" ?>>Activity Log</a>
            <?php endif; ?>
            <a href="account-security.php" aria-label="Set or change your account password"<?= $activePage === "account-security" ? ' aria-current="page"' : "" ?>>Settings</a>
        </nav>

        <div class="site-navbar-actions">
            <?php if ($showNewTicketButton): ?>
                <button id="addTicketBtn" class="site-new-ticket" type="button">
                    <span aria-hidden="true">+</span> New Ticket
                </button>
            <?php endif; ?>
            <?php if (isSupportStaff()): ?>
                <div id="adminNotifications" class="admin-notifications">
                    <button class="notification-toggle" type="button"
                            aria-label="Notifications" aria-expanded="false"
                            aria-controls="adminNotificationPanel">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path>
                        </svg>
                        <span class="notification-count" hidden></span>
                    </button>
                    <section id="adminNotificationPanel" class="notification-panel" hidden
                             aria-label="Support notifications">
                        <div class="notification-panel-header">
                            <div>
                                <strong>Support notifications</strong>
                                <span>Customer replies, requests, and overdue alerts</span>
                            </div>
                            <button type="button" class="notification-mark-all">Mark all read</button>
                        </div>
                        <div class="notification-list" aria-live="polite">
                            <p class="notification-empty">Loading notifications...</p>
                        </div>
                        <p class="notification-status" role="status" aria-live="polite"></p>
                    </section>
                </div>
            <?php else: ?>
                <div id="userNotifications" class="admin-notifications"
                     data-csrf-token="<?= htmlspecialchars($_SESSION["user_notifications_csrf"], ENT_QUOTES, "UTF-8") ?>">
                    <button class="notification-toggle" type="button"
                            aria-label="Support updates" aria-expanded="false"
                            aria-controls="userNotificationPanel">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path>
                        </svg>
                        <span class="notification-count" hidden></span>
                    </button>
                    <section id="userNotificationPanel" class="notification-panel" hidden
                             aria-label="Support team updates">
                        <div class="notification-panel-header">
                            <div>
                                <strong>Support team updates</strong>
                                <span>Replies and ticket status changes</span>
                            </div>
                            <button type="button" class="notification-mark-all">Mark all read</button>
                        </div>
                        <div class="notification-list" aria-live="polite">
                            <p class="notification-empty">Loading updates...</p>
                        </div>
                        <p class="notification-status" role="status" aria-live="polite"></p>
                    </section>
                </div>
            <?php endif; ?>
            <?php if (count($userWorkspaces) > 1): ?>
                <form class="workspace-switcher" action="switch_workspace.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["workspace_switch_csrf"], ENT_QUOTES, "UTF-8") ?>">
                    <label class="visually-hidden" for="workspaceSelect">Active workspace</label>
                    <select id="workspaceSelect" name="organization_id">
                        <?php foreach ($userWorkspaces as $workspace): ?>
                            <option value="<?= (int)$workspace["organization_id"] ?>"<?= (int)$workspace["organization_id"] === getOrganizationId() ? " selected" : "" ?>>
                                <?= htmlspecialchars((string)$workspace["name"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Switch</button>
                </form>
            <?php endif; ?>
            <a class="site-user" href="account-profile.php" aria-label="View your profile"<?= $activePage === "profile" ? ' aria-current="page"' : "" ?>>
                <span class="site-user-avatar" aria-hidden="true"><?= $navigationInitial ?></span>
                <span class="site-user-copy">
                    <strong><?= $navigationName ?></strong>
                    <small><?= ucfirst($navigationRole) ?></small>
                </span>
            </a>
            <a class="site-logout" href="logout.php">Logout</a>
        </div>
    </div>
</header>
<?php if (isSupportStaff()): ?>
    <script src="assets/js/admin-notifications.js?v=5" defer></script>
<?php else: ?>
    <script src="assets/js/user-notifications.js?v=1" defer></script>
<?php endif; ?>
