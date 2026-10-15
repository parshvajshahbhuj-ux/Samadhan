<?php
require_once "auth.php";
requireLogin();

if (($_SESSION["role"] ?? "") !== "user") {
    http_response_code(403);
    exit("Access denied.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=16">
    <link rel="stylesheet" href="assets/css/navigation.css?v=18">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=3">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
    <style>
        .support-container {
            width: min(900px, calc(100% - 40px));
            margin: 0 auto;
            padding: 42px 0 56px;
        }
        .support-card {
            padding: clamp(24px, 5vw, 42px);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 25px rgba(15, 23, 42, .045);
        }
        .support-eyebrow {
            margin: 0 0 10px;
            color: #2563eb;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .support-card h1 {
            margin: 0;
            color: #0f172a;
            font-size: clamp(26px, 4vw, 34px);
        }
        .support-intro {
            margin: 12px 0 28px;
            color: #64748b;
            font-size: 15px;
            line-height: 1.65;
        }
        .support-contact {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }
        .support-contact-label {
            display: block;
            margin-bottom: 7px;
            color: #64748b;
            font-size: 12px;
        }
        .support-number {
            color: #0f172a;
            font-size: clamp(20px, 4vw, 25px);
            font-weight: 750;
            letter-spacing: .01em;
            text-decoration: none;
        }
        .support-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .support-action {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 0 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            font-size: 13px;
            font-weight: 650;
            text-decoration: none;
            transition: border-color .18s ease, background .18s ease, color .18s ease;
        }
        .support-action:hover {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .support-tip {
            margin: 22px 0 0;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }
        @media (max-width: 600px) {
            .support-container { width: calc(100% - 28px); padding: 26px 0 36px; }
            .support-contact { align-items: flex-start; flex-direction: column; }
            .support-actions { width: 100%; }
            .support-action { flex: 1 1 100%; }
        }
        @media (max-width: 360px) {
            .support-container { width: calc(100% - 20px); padding-top: 18px; }
            .support-card { padding: 18px; }
            .support-contact { padding: 15px; }
        }
    </style>
</head>
<body class="app-page-body">
    <?php
    $activePage = "support";
    require "partials/navigation.php";
    ?>
    <main class="support-container page-transition-surface">
        <section class="support-card" aria-labelledby="supportHeading">
            <p class="support-eyebrow">Samadhan Support</p>
            <h1 id="supportHeading">Contact Support</h1>
            <p class="support-intro">Need help with a ticket or the support desk? Contact our support team using WhatsApp or phone.</p>

            <div class="support-contact">
                <div>
                    <span class="support-contact-label">Support contact number</span>
                    <a class="support-number" href="tel:+919313426208">+91 93134 26208</a>
                </div>
                <div class="support-actions">
                    <a
                        class="support-action"
                        href="https://wa.me/919313426208?text=Hello%20HelpDesk%20Pro%20support%2C%20I%20need%20help%20with%20a%20support%20ticket."
                        target="_blank"
                        rel="noopener noreferrer"
                    >Message on WhatsApp</a>
                    <a class="support-action" href="tel:+919313426208">Call support</a>
                </div>
            </div>

            <p class="support-tip">When contacting support about an existing request, include your ticket number so the team can find it more quickly. Never share your password or verification codes.</p>
        </section>
    </main>
    <?php require "partials/footer.php"; ?>
</body>
</html>
