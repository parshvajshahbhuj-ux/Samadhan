<?php
$footerAuthenticated = isset($_SESSION["user_id"]);
$footerIsUser = $footerAuthenticated && ($_SESSION["role"] ?? "") === "user";
?>
<footer class="site-footer page-transition-surface">
    <div class="site-footer-inner">
        <a class="site-footer-brand" href="<?= $footerAuthenticated ? "welcome.php" : "login.php" ?>">
            <img src="assets/images/samadhan-logo.svg" alt="" width="28" height="28">
            <span>Samadhan</span>
        </a>
        <?php if ($footerAuthenticated): ?>
            <nav class="site-footer-links" aria-label="Footer navigation">
                <a href="welcome.php">Welcome</a>
                <a href="index.php">Dashboard</a>
                <a href="analytics.php">Analytics</a>
                <?php if ($footerIsUser): ?>
                    <a href="support.php">Support</a>
                    <a href="support-inquiries.php">My requests</a>
                <?php endif; ?>
                <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
                    <a href="support-inquiries.php">Customer Requests</a>
                    <a href="activity-log.php">Activity Log</a>
                <?php endif; ?>
            </nav>
        <?php else: ?>
            <span class="site-footer-tagline">Support Ticket Management System</span>
        <?php endif; ?>
        <span class="site-footer-copyright">&copy; <?= date("Y") ?> Samadhan</span>
    </div>
</footer>
