<?php
declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 38px 44px 54px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #40596a; font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.55; }
        .brand { position: relative; width: 100%; margin-bottom: 18px; padding: 0 0 10px; border-bottom: 1px solid #dce8ed; }
        .brand-logo { display: inline-block; width: 34px; height: 34px; margin-right: 8px; vertical-align: middle; }
        .brand-name { display: inline-block; color: #082f49; font-size: 16pt; font-weight: bold; vertical-align: middle; }
        .brand-note { position: absolute; top: 2px; right: 0; margin: 0; padding: 6px 9px; border: 1px solid #dce8ed; border-radius: 10px; background: #f5f9fa; color: #547080; font-size: 7pt; font-weight: bold; letter-spacing: .65px; text-transform: uppercase; }
        .eyebrow { color: #0b8794; font-size: 8pt; font-weight: bold; letter-spacing: 1.15px; text-transform: uppercase; }
        h1 { margin: 10px 0 10px; color: #fff; font-size: 25pt; line-height: 1.14; letter-spacing: -.25px; }
        h2 { margin: 0 0 8px; color: #082f49; font-size: 18pt; }
        h3 { margin: 0 0 5px; color: #12334a; font-size: 12pt; }
        p { margin: 0 0 8px; }
        .hero { width: 100%; margin: 0 0 12px; border-collapse: collapse; border-radius: 14px; background: #082f49; }
        .hero-copy { width: 58%; padding: 27px 25px 25px; vertical-align: top; }
        .hero-copy .eyebrow { display: inline-block; padding: 6px 9px; border: 1px solid #276273; border-radius: 10px; background: #103f54; color: #91e7df; }
        .hero-copy p { max-width: 295px; margin: 0; color: #d7e7ed; font-size: 10pt; line-height: 1.65; }
        .hero-accent { display: block; width: 35px; height: 3px; margin: 0 0 9px; border-radius: 2px; background: #53d1c3; }
        .hero-art { width: 42%; padding: 14px 14px 14px 4px; vertical-align: middle; }
        .mock-window { padding: 9px; border: 1px solid #466f82; border-radius: 10px; background: #f8fbfc; box-shadow: 0 8px 20px rgba(0, 0, 0, .16); }
        .mock-top { padding-bottom: 6px; border-bottom: 1px solid #e5edf0; color: #456071; font-size: 6px; font-weight: bold; }
        .mock-dot { display: inline-block; width: 5px; height: 5px; margin-right: 3px; border-radius: 50%; background: #16a69a; }
        .mock-workspace { width: 100%; margin-top: 7px; border-collapse: collapse; }
        .mock-sidebar { width: 22%; padding: 6px 4px; border-radius: 5px; background: #edf4f6; vertical-align: top; }
        .mock-sidebar-line { height: 3px; margin: 4px 2px; border-radius: 3px; background: #c7d9df; }
        .mock-sidebar-line.active { width: 75%; background: #24a99f; }
        .mock-content { padding: 1px 0 0 7px; vertical-align: top; }
        .mock-label { margin: 0 0 4px; color: #728694; font-size: 5px; font-weight: bold; letter-spacing: .7px; }
        .mock-ticket { padding: 7px; border: 1px solid #e2ebef; border-radius: 6px; background: #fff; }
        .mock-ticket-title { margin-bottom: 4px; color: #12334a; font-size: 7px; font-weight: bold; }
        .mock-ticket-copy { color: #6e8290; font-size: 6px; }
        .mock-status { display: inline-block; margin-top: 6px; padding: 3px 6px; border-radius: 8px; background: #e4f6f2; color: #087d6f; font-size: 5px; font-weight: bold; }
        .mock-progress { height: 4px; margin-top: 7px; border-radius: 4px; background: #e5edf0; }
        .mock-progress span { display: block; width: 67%; height: 4px; border-radius: 4px; background: #21aaa3; }
        .mock-foot { margin-top: 6px; color: #718693; font-size: 5px; }
        .mock-foot strong { color: #0a887f; }
        .hero-caption { margin-top: 6px; color: #c4dce2; font-size: 5px; text-align: right; }
        .value-strip { width: 100%; margin: 0 0 12px; border-collapse: collapse; border: 1px solid #dce8ed; border-radius: 8px; background: #f6fafb; }
        .value-strip td { width: 33.33%; padding: 7px 9px; color: #466575; font-size: 6px; font-weight: bold; letter-spacing: .35px; text-align: center; }
        .value-strip td + td { border-left: 1px solid #dce8ed; }
        .value-strip strong { margin-right: 4px; color: #0b9b91; font-size: 8px; }
        .route-table { width: 100%; margin: 0 0 12px; border-spacing: 7px 0; }
        .route { width: 50%; padding: 12px 14px; border: 1px solid #dce8ed; border-radius: 9px; vertical-align: top; }
        .route.primary { border-top: 2px solid #19a79c; background: #eef8f8; }
        .route.secondary { border-top: 2px solid #97adbb; background: #f4f7fa; }
        .route-label { margin-bottom: 5px; color: #087f8b; font-size: 7pt; font-weight: bold; letter-spacing: .65px; }
        .route.secondary .route-label { color: #64798a; }
        .route p { margin: 0; color: #627686; font-size: 9pt; line-height: 1.5; }
        .section-bar { margin: 0 0 8px; }
        .section-bar h2 { display: inline; margin: 0; color: #082f49; font-size: 15pt; }
        .section-tag { float: right; padding-top: 3px; color: #80919d; font-size: 6px; font-weight: bold; letter-spacing: .7px; }
        .steps-grid { width: 100%; border-collapse: separate; border-spacing: 7px; margin: 0 -7px 5px; }
        .step-card { width: 50%; height: 145px; padding: 13px 14px; border: 1px solid #e0e9ee; border-radius: 9px; background: #fff; vertical-align: top; page-break-inside: avoid; }
        .step-card:nth-child(1) { border-top: 2px solid #19a79c; }
        .step-card:nth-child(2) { border-top: 2px solid #2a86ad; }
        .steps-grid tr + tr .step-card:first-child { border-top-color: #64849a; }
        .steps-grid tr + tr .step-card:last-child { border-top-color: #e3a143; }
        .step-head { margin-bottom: 5px; }
        .step-number { display: inline-block; width: 25px; height: 25px; margin-right: 7px; border-radius: 7px; background: #e4f5f3; color: #087f8b; font-size: 8pt; font-weight: bold; line-height: 25px; text-align: center; vertical-align: middle; }
        .step-kicker { color: #83929d; font-size: 7pt; font-weight: bold; letter-spacing: .65px; vertical-align: middle; }
        .step-card h3 { margin: 0 0 6px; font-size: 11pt; }
        .step-card p { margin: 0; color: #687d8b; font-size: 9pt; line-height: 1.5; }
        .callout { margin-top: 9px; padding: 10px 12px; border-left: 3px solid #e8a437; border-radius: 4px; background: #fff8eb; color: #705b37; font-size: 9pt; }
        .page-break { page-break-before: always; }
        .page-two-brand { margin-bottom: 16px; padding-bottom: 9px; border-bottom: 1px solid #dce8ed; color: #082f49; font-size: 9pt; font-weight: bold; }
        .page-two-brand .page-two-label { float: right; color: #80919d; font-size: 7pt; font-weight: bold; letter-spacing: .65px; }
        .page-heading { margin-bottom: 15px; padding: 17px 19px; border-left: 4px solid #4dd0c3; border-radius: 10px; background: #082f49; color: #fff; }
        .page-heading .eyebrow { color: #80ded9; }
        .page-heading h2 { margin: 6px 0 5px; color: #fff; font-size: 20pt; }
        .page-heading p { margin: 0; color: #d7e7ed; font-size: 9pt; }
        .guide-section { margin-bottom: 11px; padding: 12px 14px; border: 1px solid #e0e9ee; border-left: 3px solid #b8e5e4; border-radius: 8px; background: #fff; page-break-inside: avoid; }
        .guide-section.ticket-section { border-left-color: #18a79c; background: #f7fbfb; }
        .guide-section.report-section { border-left-color: #4c8fb1; background: #f6f9fb; }
        .guide-section.safety-section { border-left-color: #e7a344; background: #fffaf1; }
        .guide-section .section-intro { margin-bottom: 10px; color: #526b7b; font-size: 10pt; }
        .guide-section h3 { margin-bottom: 7px; color: #12334a; font-size: 13pt; }
        .guide-section p { margin-bottom: 7px; color: #526b7b; font-size: 10pt; line-height: 1.55; }
        .section-index { display: inline-block; width: 23px; height: 23px; margin-right: 7px; border-radius: 6px; background: #e4f5f3; color: #087f8b; font-size: 8pt; font-weight: bold; line-height: 23px; text-align: center; vertical-align: middle; }
        .bullet-list { margin: 5px 0 0; padding-left: 14px; }
        .bullet-list li { margin-bottom: 6px; color: #526b7b; font-size: 9.5pt; line-height: 1.5; }
        .check-grid { width: 100%; border-spacing: 6px; margin: 0 -6px; }
        .check-card { width: 50%; padding: 11px 12px; border: 1px solid #dce8ed; border-radius: 8px; background: #f7fafb; vertical-align: top; }
        .check-card strong { display: block; margin-bottom: 4px; color: #12334a; font-size: 10pt; }
        .check-card p { margin: 0; color: #526b7b; font-size: 9pt; line-height: 1.5; }
        .step-list { width: 100%; border-collapse: collapse; }
        .step-list td { padding: 8px 6px; border-bottom: 1px solid #e6edf0; color: #526b7b; font-size: 9.5pt; line-height: 1.45; vertical-align: top; }
        .step-list td:first-child { width: 36px; color: #07877e; font-weight: bold; }
        .step-list strong { color: #12334a; }
        .help-note { margin-top: 10px; padding: 11px 13px; border: 1px solid #dce8ed; border-radius: 8px; background: #f4f8fa; color: #526b7b; font-size: 9pt; line-height: 1.5; }
        .two-col { width: 100%; border-spacing: 7px 0; margin: 0 -7px 10px; }
        .info-card { width: 50%; padding: 12px 14px; border: 1px solid #dce8ed; border-radius: 8px; vertical-align: top; }
        .info-card.teal { background: #eef8f8; }
        .info-card p { margin: 0; color: #526b7b; font-size: 9pt; line-height: 1.5; }
        .role-mark { display: inline-block; width: 23px; height: 23px; margin-right: 7px; border-radius: 6px; background: #d9f1ee; color: #087f8b; font-size: 9pt; font-weight: bold; line-height: 23px; text-align: center; vertical-align: middle; }
        .status-flow { width: 100%; margin-top: 7px; border-spacing: 4px 0; }
        .status-flow td { padding: 9px 5px; border: 1px solid #dce8ed; border-radius: 6px; background: #f4f8fa; color: #355365; font-size: 8pt; font-weight: bold; text-align: center; }
        .status-flow td:first-child { border-color: #cde5e2; background: #edf8f6; color: #087b73; }
        .status-flow td:last-child { border-color: #d8e2eb; background: #eef3f7; }
        .status-flow .arrow { width: 16px; padding: 0; border: 0; background: transparent; color: #16a39a; font-size: 13pt; }
        .report-tags { width: 100%; margin-top: 6px; border-spacing: 4px 0; }
        .report-tags td { padding: 7px 5px; border: 1px solid #dce8ed; border-radius: 5px; background: #fff; color: #547080; font-size: 7pt; font-weight: bold; text-align: center; }
        .closing { margin-top: 9px; padding: 13px 15px; border: 1px solid #286173; border-radius: 9px; background: #0b3c50; }
        .closing strong { color: #91e7df; font-size: 11pt; }
        .closing p { margin: 5px 0 0; color: #e0edf0; font-size: 9.5pt; line-height: 1.5; }
        .footer { position: fixed; right: 0; bottom: -30px; left: 0; padding-top: 8px; border-top: 1px solid #dce8ed; color: #82919d; font-size: 7pt; }
        .footer .right { float: right; color: #087f8b; font-weight: bold; }
    </style>
</head>
<body>
    <div class="brand">
        {{BRAND_LOGO}}<span class="brand-name">Samadhan</span>
        <span class="brand-note">Support &amp; Resolution Desk&nbsp;&nbsp; · &nbsp;&nbsp;First-visit guide</span>
    </div>

    <table class="hero">
        <tr>
            <td class="hero-copy">
                <span class="eyebrow">A practical first-visit guide</span>
                <h1>Getting started<br>with Samadhan</h1>
                <p>Set up your organization, join the right workspace, and keep support requests moving with clear information and visible progress.</p>
            </td>
            <td class="hero-art">
                <div class="mock-window">
                    <div class="mock-top"><span class="mock-dot"></span><span class="mock-dot"></span><span class="mock-dot"></span>&nbsp; SUPPORT WORKSPACE</div>
                    <table class="mock-workspace"><tr>
                        <td class="mock-sidebar">
                            <div class="mock-sidebar-line active"></div>
                            <div class="mock-sidebar-line"></div>
                            <div class="mock-sidebar-line"></div>
                            <div class="mock-sidebar-line"></div>
                        </td>
                        <td class="mock-content">
                            <div class="mock-label">LATEST REQUEST</div>
                            <div class="mock-ticket">
                                <div class="mock-ticket-title">Account access</div>
                                <div class="mock-ticket-copy">Your request is moving forward.</div>
                                <span class="mock-status">IN PROGRESS</span>
                                <div class="mock-progress"><span></span></div>
                            </div>
                            <div class="mock-foot"><strong>●</strong> &nbsp;Next step is visible</div>
                        </td>
                    </tr></table>
                </div>
                <div class="hero-caption">A clearer path from request to resolution</div>
            </td>
        </tr>
    </table>

    <table class="value-strip"><tr>
        <td><strong>01</strong> ONE CLEAR WORKSPACE</td>
        <td><strong>02</strong> VISIBLE REQUEST PROGRESS</td>
        <td><strong>03</strong> THE RIGHT PEOPLE, CONNECTED</td>
    </tr></table>

    <table class="route-table">
        <tr>
            <td class="route primary">
                <div class="route-label">01 &nbsp; / &nbsp; CREATING A WORKSPACE</div>
                <h3>Main Admin</h3>
                <p>Register your organization, verify your email, and become its first Main Admin.</p>
            </td>
            <td class="route secondary">
                <div class="route-label">02 &nbsp; / &nbsp; JOINING A WORKSPACE</div>
                <h3>Invited Customer or Support Member</h3>
                <p>Ask your Main Admin for an invitation and use the email address it was sent to.</p>
            </td>
        </tr>
    </table>

    <div class="page-break"></div>
    <div class="page-two-brand">SAMADHAN <span class="page-two-label">NEW ORGANIZATION &nbsp; / &nbsp; SETUP</span></div>
    <div class="page-heading">
        <span class="eyebrow">CHAPTER 01 &nbsp; / &nbsp; WORKSPACE SETUP</span>
        <h2>Create your organization in four steps.</h2>
        <p>Follow this sequence to verify your email, open your workspace, and invite the people who need access.</p>
    </div>

    <div class="section-bar">
        <h2>Main Admin setup checklist</h2>
        <span class="section-tag">SETUP &nbsp; / &nbsp; 01—04</span>
    </div>
    <table class="steps-grid">
        <tr>
            <td class="step-card">
                <div class="step-head"><span class="step-number">01</span><span class="step-kicker">GET STARTED</span></div>
                <h3>Enter your registration details</h3>
                <p>Provide your organization name, your name, email address, and a password with at least 12 characters. Use an inbox you can access.</p>
            </td>
            <td class="step-card">
                <div class="step-head"><span class="step-number">02</span><span class="step-kicker">VERIFY</span></div>
                <h3>Verify your email address</h3>
                <p>Enter the six-digit code sent to your email. It expires after 10 minutes; if needed, request a new code.</p>
            </td>
        </tr>
        <tr>
            <td class="step-card">
                <div class="step-head"><span class="step-number">03</span><span class="step-kicker">SIGN IN</span></div>
                <h3>Open your new workspace</h3>
                <p>Once verified, your organization and Main Admin account are ready. Sign in with your registered email and password.</p>
            </td>
            <td class="step-card">
                <div class="step-head"><span class="step-number">04</span><span class="step-kicker">INVITE</span></div>
                <h3>Bring in the right people</h3>
                <p>Use Workspace Access to invite Customers or Support Members and choose the appropriate role. Invitations are valid for seven days.</p>
            </td>
        </tr>
    </table>
    <div class="callout"><strong>Already invited?</strong> You do not need to register a new organization. Follow the invitation and sign-in instructions from your Main Admin.</div>

    <div class="page-break"></div>
    <div class="page-two-brand">SAMADHAN <span class="page-two-label">YOUR WORKSPACE &nbsp; / &nbsp; DAY TO DAY</span></div>
    <div class="page-heading">
        <span class="eyebrow">CHAPTER 01 &nbsp; / &nbsp; FOR EVERYONE</span>
        <h2>Turn a problem into a useful request.</h2>
        <p>A well-described ticket helps the right person understand the issue and decide what should happen next.</p>
    </div>

    <div class="guide-section ticket-section">
        <h3><span class="section-index">01</span>Before you submit a ticket</h3>
        <p class="section-intro">Start from the dashboard and complete the ticket form with enough context for someone who has not seen the problem before.</p>
        <table class="step-list">
            <tr><td>01</td><td><strong>Write a specific subject.</strong> Summarize the issue in a few words, such as “Unable to access shared files.”</td></tr>
            <tr><td>02</td><td><strong>Choose the closest category and priority.</strong> This helps organize the request and communicate its urgency.</td></tr>
            <tr><td>03</td><td><strong>Describe the impact.</strong> Explain what happened, what you expected, who is affected, and whether work is blocked.</td></tr>
            <tr><td>04</td><td><strong>Include steps already tried.</strong> Note any error message and what you did immediately before it appeared.</td></tr>
            <tr><td>05</td><td><strong>Check your contact details.</strong> Use an email address where you can receive updates. A phone number is optional and enables the ticket’s WhatsApp contact link.</td></tr>
        </table>
    </div>

    <div class="guide-section">
        <h3><span class="section-index">02</span>Stay connected to your request</h3>
        <p>Sign in to see requests associated with your account. Open a request to review its details and continue the conversation in one place. If support asks a question, reply with the missing information so the team can keep working.</p>
        <p>Keep messages focused on this issue. If the same problem continues, add an update to its existing request rather than creating a duplicate.</p>
    </div>

    <div class="page-break"></div>
    <div class="page-two-brand">SAMADHAN <span class="page-two-label">CUSTOMER JOURNEY &nbsp; / &nbsp; STATUS GUIDE</span></div>
    <div class="page-heading">
        <span class="eyebrow">CHAPTER 01 &nbsp; / &nbsp; CONTINUED</span>
        <h2>Know where your request stands.</h2>
        <p>Status gives you a quick view of progress. The conversation carries the detail and the next step.</p>
    </div>

    <div class="section-bar"><h2>Follow the request from start to finish</h2></div>
    <table class="status-flow"><tr>
        <td>OPEN</td><td class="arrow">›</td><td>IN PROGRESS</td><td class="arrow">›</td><td>RESOLVED</td><td class="arrow">/</td><td>CLOSED</td>
    </tr></table>
    <table class="check-grid">
        <tr>
            <td class="check-card"><strong>Open</strong><p>The request is recorded and awaiting work or review.</p></td>
            <td class="check-card"><strong>In Progress</strong><p>The support team is actively handling the request.</p></td>
        </tr>
        <tr>
            <td class="check-card"><strong>Resolved</strong><p>The team has marked the reported issue as addressed.</p></td>
            <td class="check-card"><strong>Closed</strong><p>The request is in a completed state.</p></td>
        </tr>
    </table>
    <div class="help-note"><strong>If the issue is not fixed:</strong> Reply in the request conversation with what still happens, any new error message, and the impact on your work. This gives the team context to determine the next step.</div>
    <div class="guide-section">
        <h3><span class="section-index">03</span>What makes a helpful follow-up?</h3>
        <p>Be specific about what changed since your last message. Mention whether the issue is still happening, whether anything improved, and what you tried. Keep the new information in the existing conversation so the request history stays together.</p>
    </div>

    <div class="page-break"></div>
    <div class="page-two-brand">SAMADHAN <span class="page-two-label">WORKING THE QUEUE &nbsp; / &nbsp; SUPPORT TEAM</span></div>
    <div class="page-heading">
        <span class="eyebrow">CHAPTER 02 &nbsp; / &nbsp; FOR SUPPORT TEAMS</span>
        <h2>Make every handoff clear.</h2>
        <p>Main Admins and Support Members can manage tickets across the organization. Customers follow requests they submitted.</p>
    </div>

    <div class="guide-section ticket-section">
        <h3><span class="section-index">01</span>Review and update a request</h3>
        <p>Open any organization ticket and read its subject, description, category, priority, status, and previous updates before making changes. Confirm the reported impact and check whether the customer has already tried any troubleshooting steps.</p>
        <p>When work begins, update the status to reflect the current state. Support Members can create, assign, edit, delete, reply to, and update tickets. Workspace Access remains restricted to the Main Admin.</p>
    </div>

    <div class="guide-section">
        <h3><span class="section-index">02</span>Keep the conversation useful</h3>
        <p>Reply in the request’s conversation so the discussion stays with the issue. A helpful update tells the customer what you checked, what you need from them, or what happens next. Avoid unexplained status changes; a short note gives the customer useful context.</p>
        <table class="check-grid"><tr>
            <td class="check-card"><strong>When you need information</strong><p>Ask one clear question at a time and explain why the detail is needed.</p></td>
            <td class="check-card"><strong>When work is complete</strong><p>Summarize the outcome and set the appropriate completed status.</p></td>
        </tr></table>
    </div>

    <div class="page-break"></div>
    <div class="page-two-brand">SAMADHAN <span class="page-two-label">MAIN ADMIN TOOLKIT &nbsp; / &nbsp; REPORTING</span></div>
    <div class="page-heading">
        <span class="eyebrow">CHAPTER 02 &nbsp; / &nbsp; CONTINUED</span>
        <h2>See patterns. Make informed decisions.</h2>
        <p>The Main Admin uses organization-level views to understand ticket volume and follow the work over time.</p>
    </div>

    <div class="guide-section report-section">
        <h3><span class="section-index">03</span>Use workspace tools to follow progress</h3>
        <p>Use the dashboard’s search and filters to find tickets by their available details, status, priority, or assignee. Review ticket activity when you need to understand recorded changes. Main Admins and Support Members see organization-wide analytics; Customers see their own requests.</p>
        <p>When a spreadsheet is needed, export a ticket report with the applicable date range, status, and priority filters.</p>
        <table class="report-tags"><tr>
            <td>TICKET TOTALS</td><td>STATUS &amp; PRIORITY</td><td>CATEGORIES &amp; TRENDS</td><td>FILTERED CSV</td>
        </tr></table>
    </div>
    <div class="guide-section">
        <h3><span class="section-index">04</span>Use reports as a starting point</h3>
        <p>Choose a date range that matches the question you are asking. Compare ticket creation and resolution trends, check the status mix, and look at category or priority counts to spot areas that may need attention. Apply status or priority filters when preparing a CSV for follow-up.</p>
        <p>Reports summarize recorded ticket data; review individual requests before deciding how to respond or allocate work.</p>
    </div>
    <table class="check-grid"><tr>
        <td class="check-card"><strong>Before sharing an export</strong><p>Confirm the date range and filters, and share the file only with people authorized to see the information.</p></td>
        <td class="check-card"><strong>When reviewing activity</strong><p>Use the activity log to understand recorded events, then open the related ticket for full context.</p></td>
    </tr></table>

    <div class="page-break"></div>
    <div class="page-two-brand">SAMADHAN <span class="page-two-label">ACCESS &amp; GOOD PRACTICE</span></div>
    <div class="page-heading">
        <span class="eyebrow">CHAPTER 03 &nbsp; / &nbsp; SAFETY &amp; QUICK REFERENCE</span>
        <h2>Helpful support starts with trust.</h2>
        <p>Protect account access, share only the information needed, and make the next step easy to understand.</p>
    </div>

    <div class="guide-section safety-section">
        <h3><span class="section-index">!</span>Protect sensitive information</h3>
        <ul class="bullet-list">
            <li>Never put passwords, verification codes, payment-card details, or other secrets in a ticket, reply, or attachment.</li>
            <li>Share only information relevant to resolving the issue. Remove unrelated personal or confidential data from screenshots and files.</li>
            <li>Attach only files you are authorized to share, and use a clear message to explain why the file is relevant.</li>
            <li>Use your own account and invitation. Do not share passwords or invitation codes with other people.</li>
        </ul>
    </div>

    <div class="guide-section safety-section">
        <h3><span class="section-index">02</span>Keep communication professional</h3>
        <p>Use clear, respectful language, even when an issue is urgent or frustrating. Describe the problem and its effect without including unrelated personal information. A concise update makes it easier for the next person to understand the request.</p>
    </div>

    <div class="page-break"></div>
    <div class="page-two-brand">SAMADHAN <span class="page-two-label">JOINING &amp; QUICK REFERENCE</span></div>
    <div class="page-heading">
        <span class="eyebrow">CHAPTER 03 &nbsp; / &nbsp; CONTINUED</span>
        <h2>Join the right workspace with confidence.</h2>
        <p>Use the invitation from your organization and keep your account details private.</p>
    </div>

    <div class="guide-section">
        <h3><span class="section-index">02</span>Joining an existing organization</h3>
        <p>If your organization has invited you, use the invitation instructions and the email address the invitation was sent to. Choose the invitation-code sign-in option when your Main Admin provides a personal code. Do not register a second organization to join an existing workspace.</p>
        <p>If your invitation is missing, expired, or does not work, contact your Main Admin and request a replacement or confirmation of the correct email address.</p>
    </div>

    <div class="section-bar"><h2>A quick checklist before you send</h2></div>
    <table class="check-grid">
        <tr>
            <td class="check-card"><strong>Clear subject</strong><p>Does it summarize the problem?</p></td>
            <td class="check-card"><strong>Useful context</strong><p>Have you described the impact and steps tried?</p></td>
        </tr>
        <tr>
            <td class="check-card"><strong>Right contact</strong><p>Can the team reach you at this email?</p></td>
            <td class="check-card"><strong>Safe to share</strong><p>Have you removed passwords and sensitive details?</p></td>
        </tr>
    </table>

    <div class="help-note"><strong>Need help with account access?</strong> Contact your Main Admin. For a new organization, use Register your organization and complete email verification before signing in.</div>

    <div class="closing">
        <strong>A simple way to begin</strong>
        <p>Create or join the right workspace, submit one clear request, and use its updates to keep the next step visible. For account access, contact your Main Admin.</p>
    </div>

    <div class="footer"><span>Samadhan &nbsp;·&nbsp; Support &amp; Resolution Desk</span><span class="right">A clearer path from request to resolution</span></div>
</body>
</html>
HTML;

try {
    $logoContents = file_get_contents(__DIR__ . "/assets/images/samadhan-logo.svg");
    if ($logoContents === false) {
        throw new RuntimeException("Samadhan logo asset could not be read.");
    }
    $html = str_replace(
        "{{BRAND_LOGO}}",
        '<img class="brand-logo" src="data:image/svg+xml;base64,' . base64_encode($logoContents) . '" alt="">',
        $html
    );

    $options = new Options();
    $options->set("isRemoteEnabled", false);
    $options->set("defaultFont", "DejaVu Sans");

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, "UTF-8");
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont("DejaVu Sans", "bold");
    $canvas->page_text(267, 815, "PAGE {PAGE_NUM} / {PAGE_COUNT}", $font, 7, [0.03, 0.5, 0.55]);
    $dompdf->stream("Samadhan-Getting-Started-Guide.pdf", ["Attachment" => true]);
} catch (Throwable $exception) {
    error_log("Samadhan getting-started guide PDF generation failed: " . $exception->getMessage());
    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "The getting-started guide is temporarily unavailable. Please try again later.";
}
