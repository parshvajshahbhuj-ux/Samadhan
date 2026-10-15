<?php
require_once "auth.php";
requireLogin();

$userName = htmlspecialchars($_SESSION["user_name"] ?? "User", ENT_QUOTES, "UTF-8");
$isAdmin = isAdmin();
$isSupportMember = isSupportMember();
$isCustomer = !$isAdmin && !$isSupportMember;
if ($isCustomer && empty($_SESSION["support_contact_csrf"])) {
    $_SESSION["support_contact_csrf"] = bin2hex(random_bytes(32));
}
if ($isCustomer && empty($_SESSION["support_inquiry_csrf"])) {
    $_SESSION["support_inquiry_csrf"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=16">
    <link rel="stylesheet" href="assets/css/navigation.css?v=20">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=3">
    <link rel="stylesheet" href="assets/css/welcome.css?v=8">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="welcome-page app-page-body">
    <?php
    $activePage = "welcome";
    require "partials/navigation.php";
    ?>

    <main class="welcome-container page-transition-surface">
        <section class="welcome-hero">
            <div class="welcome-hero-copy">
                <span class="welcome-eyebrow">SAMADHAN · <?= $isAdmin ? "MAIN ADMIN GUIDELINES" : ($isSupportMember ? "SUPPORT MEMBER GUIDELINES" : "SERVICE GUIDELINES") ?></span>
                <h2>Welcome, <?= $userName ?></h2>
                <p><?= $isAdmin
                    ? "Review your administrator tools and responsibilities for managing support tickets and organization-wide reports."
                    : ($isSupportMember
                        ? "Manage tickets across your organization, keep customers informed, and use the dashboard to coordinate support work."
                        : "Please review these guidelines before contacting support or checking your tickets. They help us handle requests clearly and safely.") ?></p>
                <a class="welcome-primary-link" href="index.php">Continue to dashboard</a>
            </div>
            <img class="welcome-hero-mark" src="assets/images/samadhan-logo.svg" alt="" width="112" height="112">
        </section>

        <section class="welcome-rules" aria-labelledby="rulesHeading">
            <div class="welcome-section-heading">
                <div>
                    <span class="welcome-eyebrow"><?= $isAdmin ? "MAIN ADMIN ACCESS & RESPONSIBILITIES" : ($isSupportMember ? "SUPPORT MEMBER RESPONSIBILITIES" : "A FEW SIMPLE RULES") ?></span>
                    <h2 id="rulesHeading"><?= $isAdmin ? "Managing the support workspace" : ($isSupportMember ? "Managing organization-wide tickets" : "Using the support desk") ?></h2>
                </div>
                <p><?= $isAdmin
                    ? "Use administrator access carefully to keep ticket handling accurate and accountable."
                    : ($isSupportMember
                        ? "Support Members can view and manage tickets across their organization. Workspace Access remains available to Main Admins only."
                        : "Good ticket details help the right person respond efficiently.") ?></p>
            </div>

            <div class="welcome-rules-grid">
                <?php if ($isAdmin): ?>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">01</span>
                    <h3>Create tickets for users</h3>
                    <p>Confirm the user’s email address and ticket details before creating a request so it is associated with the correct account.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">02</span>
                    <h3>Edit and manage tickets</h3>
                    <p>Review ticket information carefully before changing its details, priority, or status. Keep updates clear and relevant to the request.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">03</span>
                    <h3>Use analytics and reports</h3>
                    <p>Use organization-wide reports to monitor workload and outcomes. Export ticket data only for authorized support or business purposes.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">04</span>
                    <h3>Review the activity log</h3>
                    <p>Use the activity log to audit ticket-related changes and support operations. Treat the information it contains as confidential.</p>
                </article>
                <?php elseif ($isSupportMember): ?>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">01</span>
                    <h3>Work across the organization</h3>
                    <p>Use the dashboard to view, filter, and assign any ticket in your organization.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">02</span>
                    <h3>Keep customers informed</h3>
                    <p>Use the ticket conversation to ask questions and share clear, relevant progress updates.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">03</span>
                    <h3>Manage ticket details</h3>
                    <p>Create, edit, delete, assign, and update tickets while keeping customers informed of progress.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">04</span>
                    <h3>Protect customer information</h3>
                    <p>Use customer details only for support work. Do not share passwords, verification codes, or other secrets. Workspace Access is restricted to Main Admins.</p>
                </article>
                <?php else: ?>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">01</span>
                    <h3>Use a clear subject</h3>
                    <p>When contacting support, summarize the issue in a few words so the team can route your request correctly.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">02</span>
                    <h3>Describe what happened</h3>
                    <p>Include relevant details, steps to reproduce the issue, and what you expected to happen. Avoid creating duplicate tickets for the same issue.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">03</span>
                    <h3>Explain the impact</h3>
                    <p>Tell the support team who is affected and whether your work is blocked. The team will assess and assign ticket priority.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">04</span>
                    <h3>Protect sensitive information</h3>
                    <p>Do not include passwords, verification codes, payment details, or other secrets in ticket descriptions or attachments.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">05</span>
                    <h3>Share only appropriate files</h3>
                    <p>Attach only files relevant to the support request that you are authorized to share. Follow your organization’s data-handling policies.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">06</span>
                    <h3>Keep contact details current</h3>
                    <p>Use an email address where you can receive updates. A phone number is optional and is used to provide the ticket’s WhatsApp contact link.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">07</span>
                    <h3>Communicate respectfully</h3>
                    <p>Keep ticket updates professional, relevant, and courteous. Add new information to the existing ticket when it concerns the same issue.</p>
                </article>
                <article class="welcome-rule-card">
                    <span class="welcome-rule-number">08</span>
                    <h3>Check your ticket status</h3>
                    <p>Review the dashboard for status updates and respond with any requested information so the support team can continue investigating.</p>
                </article>
                <?php endif; ?>
            </div>
        </section>

        <section class="welcome-process" aria-labelledby="responseProcessHeading">
            <div class="welcome-section-heading">
                <div>
                    <span class="welcome-eyebrow"><?= $isAdmin || $isSupportMember ? "SUPPORT WORKFLOW" : "GETTING A RESPONSE" ?></span>
                    <h2 id="responseProcessHeading"><?= $isAdmin
                        ? "Managing support requests"
                        : ($isSupportMember ? "Managing organization-wide tickets" : "How to get a response from support") ?></h2>
                </div>
                <p><?= $isAdmin
                    ? "Review contact requests promptly and keep each response connected to a ticket."
                    : ($isSupportMember
                        ? "Use the dashboard to manage organization-wide tickets, reply to customers, and update ticket status."
                        : "Choose WhatsApp for a direct message or send the support team a message here.") ?></p>
            </div>

            <?php if ($isAdmin): ?>
                <div class="welcome-process-grid">
                    <article class="welcome-process-card">
                        <span class="welcome-rule-number">01</span>
                        <h3>Review customer requests</h3>
                        <p>New on-site messages appear in your administrator notifications and are also emailed to admin@helpdesk.com.</p>
                    </article>
                    <article class="welcome-process-card">
                        <span class="welcome-rule-number">02</span>
                        <h3>Create a ticket</h3>
                        <p>Open the request from notifications and create a ticket using the customer's details and message.</p>
                    </article>
                    <article class="welcome-process-card">
                        <span class="welcome-rule-number">03</span>
                        <h3>Respond within 24 hours</h3>
                        <p>Review and respond to new support requests as soon as possible, with a target response time of 24 hours.</p>
                    </article>
                    <article class="welcome-process-card">
                        <span class="welcome-rule-number">04</span>
                        <h3>Keep the user informed</h3>
                        <p>Ticket creation sends an update to the customer's account email so they can follow progress from their dashboard.</p>
                    </article>
                </div>
            <?php elseif ($isSupportMember): ?>
                <div class="welcome-process-grid">
                    <article class="welcome-process-card">
                        <span class="welcome-rule-number">01</span>
                        <h3>Open your dashboard</h3>
                        <p>Your dashboard includes tickets across your organization, with assignment filters and team workload details.</p>
                    </article>
                    <article class="welcome-process-card">
                        <span class="welcome-rule-number">02</span>
                        <h3>Review the request</h3>
                        <p>Read the ticket description and conversation before responding so you understand the customer’s issue.</p>
                    </article>
                    <article class="welcome-process-card">
                        <span class="welcome-rule-number">03</span>
                        <h3>Reply and update progress</h3>
                        <p>Use the ticket conversation for customer-facing updates and change the status as work progresses.</p>
                    </article>
                    <article class="welcome-process-card">
                        <span class="welcome-rule-number">04</span>
                        <h3>Watch for notifications</h3>
                        <p>Support notifications include customer replies, customer requests, and overdue alerts for your organization.</p>
                    </article>
                </div>
            <?php else: ?>
                <div class="welcome-contact-actions">
                    <a class="welcome-contact-link welcome-contact-whatsapp"
                       href="https://wa.me/919313426208?text=<?= rawurlencode("Hello Samadhan support, I need help. My account is " . html_entity_decode($userName, ENT_QUOTES, "UTF-8") . ".") ?>"
                       target="_blank" rel="noopener noreferrer">
                        <span aria-hidden="true">↗</span>
                        Message us on WhatsApp
                        <small>93134 26208</small>
                    </a>
                    <a class="welcome-contact-link welcome-contact-email" href="#supportContactForm">
                        <span aria-hidden="true">@</span>
                        Email the support team
                        <small>Send a message securely</small>
                    </a>
                </div>
                <form id="supportContactForm" class="support-contact-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["support_contact_csrf"], ENT_QUOTES, "UTF-8") ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="support-contact-form-heading">
                        <h3>Send a support message</h3>
                        <p>We’ll notify the support team and send a confirmation to your account email.</p>
                    </div>
                    <label for="supportContactSubject">Subject</label>
                    <input id="supportContactSubject" name="subject" type="text" maxlength="150" required placeholder="Example: I can’t sign in to my account">
                    <label for="supportContactMessage">Message</label>
                    <textarea id="supportContactMessage" name="message" rows="5" maxlength="5000" required placeholder="Example: I’m unable to sign in, even after resetting my password. The page shows an “invalid credentials” message. Could you please help me restore access?"></textarea>
                    <div class="support-contact-form-footer">
                        <small>Our team aims to respond within 24 hours.</small>
                        <button class="welcome-primary-link" type="submit">Send message</button>
                    </div>
                    <p class="support-contact-status" id="supportContactStatus" role="status" aria-live="polite"></p>
                </form>
                <a class="welcome-my-requests-link" href="support-inquiries.php">
                    View my support requests <span aria-hidden="true">&rarr;</span>
                </a>
            <?php endif; ?>
        </section>

        <aside class="welcome-note">
            <strong><?= $isAdmin ? "Main Admin access" : ($isSupportMember ? "Support Member access" : "Need help?") ?></strong>
            <span><?= $isAdmin
                ? "You can create and edit tickets, assign them to Support Members, view organization-wide analytics, and access the activity log from the dashboard navigation."
                : ($isSupportMember
                    ? "You can create, edit, delete, assign, and update organization tickets, and use analytics, reports, customer requests, and the activity log. Workspace Access remains with the Main Admin."
                    : "Use the WhatsApp or email support options above. Once the team creates a ticket for your request, you can follow it from your dashboard.") ?></span>
        </aside>

        <div class="welcome-footer-action">
            <span>You’re ready to get started.</span>
            <a class="welcome-primary-link" href="index.php">Go to dashboard</a>
        </div>
    </main>
    <?php require "partials/footer.php"; ?>
    <?php if ($isCustomer): ?>
        <script src="assets/js/support-contact.js?v=1" defer></script>
    <?php endif; ?>
</body>
</html>
