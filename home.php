<?php
require_once "auth.php";
if (isset($_SESSION["user_id"])) {
    header("Location: welcome.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Samadhan | Support, organized</title>
    <meta name="description" content="Manage support tickets, customer conversations, team assignments, analytics, and reports in one organized workspace.">
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/sm-home.css?v=22">
    <script src="assets/js/sm-home.js?v=8" defer></script>
</head>
<body class="sm-body">

    <!-- ============ HEADER ============ -->
    <header class="sm-header" id="smHeader">
        <a class="sm-brand" href="home.php" aria-label="Samadhan home">
            <img src="assets/images/samadhan-logo.svg" alt="" width="40" height="40">
            <span><strong>Samadhan</strong><small>Support &amp; Resolution Desk</small></span>
        </a>
        <nav class="sm-nav" aria-label="Public navigation">
            <a href="#smEntryPaths">Start here</a>
            <a href="#smFeatures">Features</a>
            <a href="#smHow">How it works</a>
            <a href="#smRoles">Roles</a>
            <a href="#smGettingStarted">New here?</a>
            <a href="#smFaq">FAQ</a>
            <a class="sm-nav-signin" href="login.php">Sign in</a>
            <a class="sm-nav-cta" href="register.php">Get Started</a>
        </nav>
        <button class="sm-burger" id="smBurger" aria-label="Open menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </header>

    <!-- ============ HERO ============ -->
    <main>
    <section class="sm-hero">
        <div class="sm-hero-bg" aria-hidden="true"></div>
        <div class="sm-hero-inner">
            <div class="sm-hero-copy">
                <span class="sm-pill"><i aria-hidden="true"></i> SUPPORT THAT STAYS ORGANIZED</span>
                <h1>Good support starts with <em>everything in its place.</em></h1>
                <p>Need help with a request, or setting up support for your organization? Choose the path that fits you.</p>
                <div class="sm-hero-actions">
                    <a class="sm-btn-primary" href="login.php">I need support <span aria-hidden="true">→</span></a>
                    <a class="sm-btn-ghost" href="register.php">Set up a workspace</a>
                </div>
                <div class="sm-hero-proof">
                    <div class="sm-avatars" aria-hidden="true">
                        <span>R</span><span>A</span><span>S</span><span>M</span>
                    </div>
                    <div class="sm-proof-text">
                        <div class="sm-stars" aria-label="5 out of 5">★★★★★</div>
                        <small>Made for companies, organizations, and shops.</small>
                    </div>
                </div>
            </div>

            <div class="sm-hero-visual">
                <figure class="sm-hero-preview">
                    <div class="sm-preview-browser-bar" aria-hidden="true">
                        <span class="sm-preview-browser-dot"></span>
                        <span class="sm-preview-browser-dot"></span>
                        <span class="sm-preview-browser-dot"></span>
                        <span>samadhan.app / workspace</span>
                    </div>
                    <div class="sm-preview-app" aria-hidden="true">
                        <div class="sm-preview-nav">
                            <div class="sm-preview-brand">
                                <img src="assets/images/samadhan-logo.svg" alt="">
                                <span><strong>Samadhan</strong><small>Support &amp; Resolution Desk</small></span>
                            </div>
                            <div class="sm-preview-links">
                                <span class="is-active">Welcome</span>
                                <span>Dashboard</span>
                                <span>Analytics</span>
                                <span>Activity Log</span>
                            </div>
                            <div class="sm-preview-user"><span>S</span><strong>Admin</strong></div>
                        </div>
                        <div class="sm-preview-content">
                            <section class="sm-preview-welcome">
                                <div>
                                    <span class="sm-preview-eyebrow">SAMADHAN · ADMIN GUIDELINES</span>
                                    <h2>Welcome, System Admin</h2>
                                    <p>Manage support tickets and organization-wide reports from your workspace.</p>
                                    <span class="sm-preview-button">Continue to dashboard <b aria-hidden="true">→</b></span>
                                </div>
                                <div class="sm-preview-mark"><img src="assets/images/samadhan-logo.svg" alt=""></div>
                            </section>
                            <section class="sm-preview-guidelines">
                                <div class="sm-preview-section-heading">
                                    <div><span>ADMIN ACCESS &amp; RESPONSIBILITIES</span><strong>Using the administrator workspace</strong></div>
                                    <small>Keep ticket handling accurate and accountable.</small>
                                </div>
                                <div class="sm-preview-cards">
                                    <article><span>01</span><div><strong>Create tickets for users</strong><small>Confirm account details before creating a request.</small></div></article>
                                    <article><span>02</span><div><strong>Edit and manage tickets</strong><small>Keep updates clear and relevant.</small></div></article>
                                    <article><span>03</span><div><strong>Use analytics and reports</strong><small>Review workload and outcomes.</small></div></article>
                                    <article><span>04</span><div><strong>Review the activity log</strong><small>Understand recorded support changes.</small></div></article>
                                </div>
                            </section>
                        </div>
                    </div>
                </figure>
            </div>
        </div>
    </section>

    <!-- ============ CUSTOMER AND OWNER ENTRY PATHS ============ -->
    <section class="sm-entry-paths" id="smEntryPaths" aria-labelledby="smEntryPathsHeading">
        <div class="sm-entry-paths-heading">
            <span class="sm-pill"><i aria-hidden="true"></i> START WITH THE RIGHT PATH</span>
            <h2 id="smEntryPathsHeading">What brings you here?</h2>
            <p>Customers can sign in to send and follow a request. Organizations can create a workspace for their support team.</p>
        </div>
        <div class="sm-entry-path-grid">
            <article class="sm-entry-path sm-entry-customer">
                <span class="sm-entry-path-label">FOR CUSTOMERS</span>
                <h3>Looking for help?</h3>
                <p>Sign in to submit a support ticket, reply to your support team, and follow updates. Don’t have an account? Ask your organization’s administrator for an invitation.</p>
                <a class="sm-entry-link sm-entry-link-primary" href="login.php">Sign in for support <span aria-hidden="true">→</span></a>
            </article>
            <article class="sm-entry-path sm-entry-owner">
                <span class="sm-entry-path-label">FOR ORGANIZATIONS</span>
                <h3>Setting up support for your team?</h3>
                <p>Create a workspace to organize customer requests, invite your team, assign tickets, and review support activity.</p>
                <a class="sm-entry-link" href="register.php">Create a workspace <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </section>

    <!-- ============ CUSTOMER JOURNEY ============ -->
    <section class="sm-customer-journey" id="smCustomerJourney" aria-labelledby="smCustomerJourneyHeading">
        <div class="sm-customer-journey-heading">
            <span class="sm-pill"><i aria-hidden="true"></i> FOR CUSTOMERS</span>
            <h2 id="smCustomerJourneyHeading">Getting support is simple.</h2>
            <p>From signing in to hearing back from the team, your request stays easy to follow.</p>
        </div>
        <ol class="sm-customer-steps">
            <li class="sm-customer-step">
                <span class="sm-customer-step-number">01</span>
                <div>
                    <h3>Sign in to your account</h3>
                    <p>Use your existing account. If you’re new, ask your organization’s administrator to invite you.</p>
                </div>
            </li>
            <li class="sm-customer-step">
                <span class="sm-customer-step-number">02</span>
                <div>
                    <h3>Send your request</h3>
                    <p>Describe the issue and include the details the support team needs to help.</p>
                </div>
            </li>
            <li class="sm-customer-step">
                <span class="sm-customer-step-number">03</span>
                <div>
                    <h3>Follow the conversation</h3>
                    <p>Check the ticket for status updates and replies, then respond in the same conversation.</p>
                </div>
            </li>
        </ol>
        <a class="sm-entry-link sm-entry-link-primary sm-customer-journey-link" href="login.php">Sign in to get support <span aria-hidden="true">→</span></a>
    </section>

    <!-- ============ LOGO / TRUST STRIP ============ -->
    <section class="sm-strip" aria-label="Who Samadhan is for">
        <span>Built for</span>
        <ul>
            <li>Companies</li>
            <li>Organizations</li>
            <li>Shops</li>
            <li>Support teams</li>
            <li>Service desks</li>
        </ul>
    </section>

    <!-- ============ FEATURES ============ -->
    <section class="sm-features" id="smFeatures" aria-labelledby="smFeaturesHeading">
        <div class="sm-heading">
            <span class="sm-pill"><i aria-hidden="true"></i> TOOLS FOR EVERYDAY SUPPORT</span>
            <h2 id="smFeaturesHeading">Less searching. More meaningful progress.</h2>
            <p>Practical tools help your team stay on top of requests without losing sight of the people behind them.</p>
        </div>
        <div class="sm-feature-grid">
            <article class="sm-fcard">
                <span class="sm-ficon is-blue" aria-hidden="true">▤</span>
                <h3>Ticket management and assignment</h3>
                <p>Create, search, filter, and update tickets. Assign work to Support Members and track each request by status and priority.</p>
                <a href="register.php">Explore <span aria-hidden="true">→</span></a>
            </article>
            <article class="sm-fcard">
                <span class="sm-ficon is-teal" aria-hidden="true">↔</span>
                <h3>Customer conversations</h3>
                <p>Keep replies, status history, and notifications with the ticket. Customers can follow and respond to their own requests.</p>
                <a href="register.php">Explore <span aria-hidden="true">→</span></a>
            </article>
            <article class="sm-fcard">
                <span class="sm-ficon is-green" aria-hidden="true">▥</span>
                <h3>Analytics and reports</h3>
                <p>Review ticket trends, resolution metrics, statuses, and priorities. Filter by date and export matching tickets to CSV.</p>
                <a href="register.php">Explore <span aria-hidden="true">→</span></a>
            </article>
            <article class="sm-fcard">
                <span class="sm-ficon is-amber" aria-hidden="true">◷</span>
                <h3>Activity and follow-up</h3>
                <p>Review ticket updates in a chronological timeline. If an issue returns, customers can reopen a resolved ticket within 7 days.</p>
                <a href="register.php">Explore <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </section>

    <!-- ============ HOW IT WORKS ============ -->
    <section class="sm-how" id="smHow" aria-labelledby="smHowHeading">
        <div class="sm-heading">
            <span class="sm-pill sm-pill-light"><i aria-hidden="true"></i> A BETTER SUPPORT WORKFLOW</span>
            <h2 id="smHowHeading">From first message to a clear resolution.</h2>
            <p>Give your team and customers a shared view of what needs attention and what happens next.</p>
        </div>
        <div class="sm-steps">
            <div class="sm-step-line" aria-hidden="true"></div>
            <article class="sm-step">
                <span class="sm-step-no">01</span>
                <h3>Bring every request together</h3>
                <p>Keep the issue, its updates, and related details connected in one place.</p>
            </article>
            <article class="sm-step">
                <span class="sm-step-no">02</span>
                <h3>Make the next step clear</h3>
                <p>Use status and priority to help your team focus on the right response.</p>
            </article>
            <article class="sm-step">
                <span class="sm-step-no">03</span>
                <h3>Keep everyone in the loop</h3>
                <p>Follow progress and understand support activity across your workspace.</p>
            </article>
        </div>
    </section>

    <!-- ============ GETTING STARTED ============ -->
    <section class="sm-getting-started" id="smGettingStarted" aria-labelledby="smGettingStartedHeading">
        <div class="sm-getting-started-inner">
            <div class="sm-getting-started-heading">
                <span class="sm-pill"><i aria-hidden="true"></i> NEW TO SAMADHAN?</span>
                <h2 id="smGettingStartedHeading">Your first steps, made clear.</h2>
                <p>Choose the path that fits you, then follow a few simple steps to get your support workspace ready.</p>
                <div class="sm-getting-started-actions">
                    <a class="sm-btn-primary" href="assets/docs/Samadhan-Getting-Started-Guide.pdf" download>Download Brochure <span aria-hidden="true">↓</span></a>
                    <a class="sm-getting-started-signin" href="login.php">Already have an account? Sign in</a>
                </div>
            </div>

            <div class="sm-start-paths">
                <article class="sm-start-path sm-start-path-admin">
                    <span class="sm-start-path-label">STARTING A NEW WORKSPACE</span>
                    <h3>For organization administrators</h3>
                    <ol class="sm-start-steps">
                        <li><span>01</span><div><strong>Create your organization</strong><p>Select Get Started and enter your organization name, your name, email, and a password of at least 12 characters.</p></div></li>
                        <li><span>02</span><div><strong>Verify your email</strong><p>Enter the six-digit code sent to your email. The code expires after 10 minutes; request a new one if needed.</p></div></li>
                        <li><span>03</span><div><strong>Sign in and invite your people</strong><p>After verification, sign in as the workspace administrator. Invite customers or support teammates from Workspace Access.</p></div></li>
                        <li><span>04</span><div><strong>Manage requests and review progress</strong><p>Create and update tickets, keep conversations connected, and use Analytics &amp; Reports to review your workspace.</p></div></li>
                    </ol>
                </article>

                <aside class="sm-start-path sm-start-path-member">
                    <span class="sm-start-path-icon" aria-hidden="true">↗</span>
                    <span class="sm-start-path-label">JOINING AN EXISTING WORKSPACE</span>
                    <h3>For invited customers and teammates</h3>
                    <p>Ask your organization administrator for an invitation, then follow the sign-in or invitation instructions they provide. You do not need to create a second organization.</p>
                    <ul>
                        <li>Customers can follow and reply to their own requests.</li>
                        <li>Support Members can manage organization tickets, assignments, customer requests, reports, and activity.</li>
                        <li>Main Admins manage Workspace Access and can view team capacity.</li>
                        <li>Use the email address associated with your invitation.</li>
                    </ul>
                    <a class="sm-text-link" href="login.php">Go to sign in <span aria-hidden="true">→</span></a>
                </aside>
            </div>

            <p class="sm-start-safety"><strong>A quick safety note</strong> Keep ticket details clear and relevant. Never include passwords, verification codes, or payment information in a request or attachment.</p>
        </div>
    </section>

    <!-- ============ INTERACTIVE TOUR ============ -->
    <section class="sm-tour" aria-labelledby="smTourHeading">
        <div class="sm-tour-inner">
            <div class="sm-tour-copy">
                <span class="sm-pill"><i aria-hidden="true"></i> A CLOSER LOOK</span>
                <h2 id="smTourHeading">One request. A clearer path forward.</h2>
                <p>Keep the request, its status, and the conversation together as your team works toward a resolution.</p>
                <div class="sm-tour-steps" role="group" aria-label="Choose a workflow step">
                    <button type="button" data-sm-tour="0" aria-pressed="true">
                        <span>01</span><div><strong>Capture the request</strong><small>Record the issue, priority, and relevant contact details in a ticket.</small></div>
                    </button>
                    <button type="button" data-sm-tour="1" aria-pressed="false">
                        <span>02</span><div><strong>Keep progress visible</strong><small>Use status updates and ticket activity to follow what has changed.</small></div>
                    </button>
                    <button type="button" data-sm-tour="2" aria-pressed="false">
                        <span>03</span><div><strong>Close the loop</strong><small>Continue the conversation and record the outcome when the issue is resolved.</small></div>
                    </button>
                </div>
            </div>
            <div class="sm-tour-card" aria-label="Interactive example of a support request moving through its workflow">
                <div class="sm-tour-top"><span class="sm-dot sm-dot-r"></span><span class="sm-dot sm-dot-y"></span><span class="sm-dot sm-dot-g"></span><em>PRODUCT WALKTHROUGH · EXAMPLE</em></div>
                <div class="sm-tour-title">
                    <span class="sm-tour-ic" id="smTourIcon" aria-hidden="true">↗</span>
                    <div><small>SUPPORT REQUEST</small><strong id="smTourTitle" aria-live="polite">Request received</strong></div>
                </div>
                <div class="sm-tour-track" aria-hidden="true">
                    <span class="sm-node" data-node="0"></span>
                    <div class="sm-track-seg"><i id="smTourFill" style="width:0%"></i></div>
                    <span class="sm-node" data-node="1"></span>
                    <div class="sm-track-seg"><i></i></div>
                    <span class="sm-node" data-node="2"></span>
                </div>
                <div class="sm-tour-labels"><span>Received</span><span>In progress</span><span>Resolved</span></div>
                <div class="sm-tour-update">
                    <span class="sm-tour-check" aria-hidden="true">✓</span>
                    <div><strong id="smTourUpdateTitle">The request is captured</strong><small id="smTourUpdateDesc">Details and priority are kept with the ticket.</small></div>
                </div>
                <div class="sm-tour-meta"><span id="smTourStatus" class="sm-tag is-new">New</span><span class="sm-tour-time">Last updated just now</span></div>
            </div>
        </div>
    </section>

    <!-- ============ ACCESS ============ -->
    <section class="sm-access" id="smAccess" aria-labelledby="smAccessHeading">
        <div class="sm-access-inner">
            <div class="sm-access-copy">
                <span class="sm-pill"><i aria-hidden="true"></i> ACCESS THAT IS EASY TO UNDERSTAND</span>
                <h2 id="smAccessHeading">The right people in the right workspace.</h2>
                <p>Workspace invitations let administrators add customers or support teammates. Role-based access keeps responsibilities clear, while customers follow their own requests.</p>
                <a class="sm-text-link" href="register.php">Set up your workspace <span aria-hidden="true">→</span></a>
            </div>
            <div class="sm-access-grid">
                <div class="sm-apoint"><span>01</span><div><strong>Invite by email</strong><small>Send an invitation to join your workspace.</small></div></div>
                <div class="sm-apoint"><span>02</span><div><strong>Choose a workspace role</strong><small>Assign customer or support administrator access.</small></div></div>
                <div class="sm-apoint"><span>03</span><div><strong>Manage access</strong><small>Review members and pending invitations in one place.</small></div></div>
            </div>
        </div>
    </section>

    <!-- ============ WORKSPACE ROLES ============ -->
    <section class="sm-roles" id="smRoles" aria-labelledby="smRolesHeading">
        <div class="sm-heading">
            <span class="sm-pill"><i aria-hidden="true"></i> CLEAR RESPONSIBILITIES</span>
            <h2 id="smRolesHeading">Three roles, one coordinated support team.</h2>
            <p>Each workspace role has the access it needs to keep requests moving and customer information protected.</p>
        </div>
        <div class="sm-role-grid">
            <article class="sm-role-card">
                <span class="sm-role-number">01 · WORKSPACE OWNER</span>
                <h3>Owner (Main Admin)</h3>
                <p>Oversees the workspace and support operation.</p>
                <ul>
                    <li>Manage workspace members, invitations, and access.</li>
                    <li>View and manage organization-wide tickets and assignments.</li>
                    <li>Review team capacity, analytics, reports, and activity.</li>
                </ul>
            </article>
            <article class="sm-role-card">
                <span class="sm-role-number">02 · SUPPORT TEAM</span>
                <h3>Support Members</h3>
                <p>Work with customers and manage support across the organization.</p>
                <ul>
                    <li>Create, edit, assign, and update organization tickets.</li>
                    <li>Handle customer requests, replies, and support notifications.</li>
                    <li>Use analytics, reports, and the activity history.</li>
                    <li>Workspace Access and team capacity remain Main-Admin-only.</li>
                </ul>
            </article>
            <article class="sm-role-card">
                <span class="sm-role-number">03 · REQUESTER</span>
                <h3>Customers</h3>
                <p>Submit requests and stay informed about their own support tickets.</p>
                <ul>
                    <li>View and reply to their own tickets and follow progress.</li>
                    <li>Read ticket conversations and activity updates.</li>
                    <li>Reopen a resolved ticket within 7 days with an explanation.</li>
                </ul>
            </article>
        </div>
    </section>

    <!-- ============ STATS BAND ============ -->
    <section class="sm-stats" aria-label="What your workspace gets">
        <div class="sm-stats-inner">
            <div><strong>1</strong><span>Workspace for everything</span></div>
            <div><strong>4</strong><span>Clear request statuses</span></div>
            <div><strong>7<span class="sm-unit">days</span></strong><span>Resolved-ticket reopen window</span></div>
            <div><strong>CSV</strong><span>Filtered report exports</span></div>
        </div>
    </section>

    <!-- ============ FAQ ============ -->
    <section class="sm-faq" id="smFaq" aria-labelledby="smFaqHeading">
        <div class="sm-heading">
            <span class="sm-pill"><i aria-hidden="true"></i> GOOD TO KNOW</span>
            <h2 id="smFaqHeading">A few helpful answers.</h2>
            <p>Quick details about getting started and using your support workspace.</p>
        </div>
        <div class="sm-faq-list">
            <details class="sm-faq-item" open>
                <summary>How do I get started?<span aria-hidden="true"></span></summary>
                <p>Create a workspace through registration, then sign in to manage support tickets and invite members.</p>
            </details>
            <details class="sm-faq-item">
                <summary>How do workspace invitations work?<span aria-hidden="true"></span></summary>
                <p>An administrator can invite someone by email and choose a customer or support administrator role. Invitations are valid for seven days and can be resent or revoked.</p>
            </details>
            <details class="sm-faq-item">
                <summary>What can customers access?<span aria-hidden="true"></span></summary>
                <p>Customers can view and reply to their own support tickets, follow status changes, and review the conversation and activity history.</p>
            </details>
            <details class="sm-faq-item">
                <summary>Can a customer reopen a resolved ticket?<span aria-hidden="true"></span></summary>
                <p>Yes. The customer who submitted it can reopen a resolved ticket within 7 days by explaining what still needs attention. Closed tickets cannot be reopened from the customer dashboard.</p>
            </details>
            <details class="sm-faq-item">
                <summary>What can Support Members and Main Admins do?<span aria-hidden="true"></span></summary>
                <p>Support Members can manage tickets and customer requests across their organization, assign support work, and use analytics and activity tools. Main Admins have those capabilities plus Workspace Access and team capacity.</p>
            </details>
            <details class="sm-faq-item">
                <summary>Can I review or export ticket reports?<span aria-hidden="true"></span></summary>
                <p>Yes. The Analytics &amp; Reports area includes ticket metrics, charts, date filters, and filtered CSV exports.</p>
            </details>
            <details class="sm-faq-item">
                <summary>Can I sign in if I already have an account?<span aria-hidden="true"></span></summary>
                <p>Yes. Use the Sign in link at the top of the page to access your existing workspace.</p>
            </details>
        </div>
    </section>

    <!-- ============ INDUSTRIES ============ -->
    <section class="sm-industries" aria-labelledby="smIndustriesHeading">
        <div class="sm-industries-inner">
            <div class="sm-industries-head">
                <div class="sm-industries-heading">
                    <span class="sm-pill"><i aria-hidden="true"></i> BUILT FOR DIFFERENT TEAMS</span>
                    <h2 id="smIndustriesHeading">Support workflows for every kind of team.</h2>
                    <p>From customer questions to internal requests, keep conversations and follow-up organized.</p>
                </div>
                <div class="sm-industries-stats" aria-label="Industries at a glance">
                    <div><strong>5+</strong><span>Core industries</span></div>
                    <div><strong>3</strong><span>Workspace roles</span></div>
                    <div><strong>24/7</strong><span>Request visibility</span></div>
                </div>
            </div>

            <ul class="sm-industry-list" aria-label="Example industries">
                <li>
                    <div class="sm-industry-media">
                        <span class="sm-industry-no">01</span>
                        <img class="sm-industry-photo" src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&amp;fit=crop&amp;w=720&amp;q=80" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                        <span class="sm-industry-arrow" aria-hidden="true">→</span>
                    </div>
                    <span class="sm-industry-copy"><strong>Retail &amp; e-commerce</strong><small>Order questions, returns, and customer follow-ups.</small></span>
                </li>
                <li>
                    <div class="sm-industry-media">
                        <span class="sm-industry-no">02</span>
                        <img class="sm-industry-photo" src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&amp;fit=crop&amp;w=720&amp;q=80" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                        <span class="sm-industry-arrow" aria-hidden="true">→</span>
                    </div>
                    <span class="sm-industry-copy"><strong>IT &amp; technology</strong><small>Access issues, troubleshooting, and service requests.</small></span>
                </li>
                <li>
                    <div class="sm-industry-media">
                        <span class="sm-industry-no">03</span>
                        <img class="sm-industry-photo" src="https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&amp;fit=crop&amp;w=720&amp;q=80" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                        <span class="sm-industry-arrow" aria-hidden="true">→</span>
                    </div>
                    <span class="sm-industry-copy"><strong>Professional services</strong><small>Client questions, requests, and follow-up tasks.</small></span>
                </li>
                <li>
                    <div class="sm-industry-media">
                        <span class="sm-industry-no">04</span>
                        <img class="sm-industry-photo" src="https://images.unsplash.com/photo-1523580494863-6f3031224c94?auto=format&amp;fit=crop&amp;w=720&amp;q=80" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                        <span class="sm-industry-arrow" aria-hidden="true">→</span>
                    </div>
                    <span class="sm-industry-copy"><strong>Education</strong><small>Student, staff, and campus support requests.</small></span>
                </li>
                <li>
                    <div class="sm-industry-media">
                        <span class="sm-industry-no">05</span>
                        <img class="sm-industry-photo" src="https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&amp;fit=crop&amp;w=720&amp;q=80" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                        <span class="sm-industry-arrow" aria-hidden="true">→</span>
                    </div>
                    <span class="sm-industry-copy"><strong>Operations teams</strong><small>Internal requests and day-to-day coordination.</small></span>
                </li>
            </ul>

            <div class="sm-industry-marquee" aria-hidden="true">
                <div class="sm-industry-marquee-track">
                    <span>Retail &amp; E-commerce</span><span>IT &amp; Technology</span><span>Professional Services</span><span>Education</span><span>Operations</span><span>Healthcare</span><span>Hospitality</span>
                    <span>Retail &amp; E-commerce</span><span>IT &amp; Technology</span><span>Professional Services</span><span>Education</span><span>Operations</span><span>Healthcare</span><span>Hospitality</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ CTA ============ -->
    <section class="sm-cta">
        <div class="sm-cta-inner">
            <div>
                <span class="sm-pill sm-pill-light"><i aria-hidden="true"></i> A BETTER WAY TO HANDLE SUPPORT</span>
                <h2>Bring your requests, people, and progress together.</h2>
                <p>Create your workspace and take the next step toward a clearer support experience.</p>
            </div>
            <div class="sm-cta-actions">
                <a class="sm-btn-primary sm-btn-invert" href="register.php">Create your workspace <span aria-hidden="true">→</span></a>
                <a class="sm-btn-ghost sm-btn-ghost-light" href="login.php">Sign in</a>
            </div>
        </div>
    </section>
    </main>

    <footer class="sm-footer sm-reveal" aria-label="Site footer">
        <div class="sm-footer-main">
            <div class="sm-footer-brand">
                <a class="sm-brand" href="home.php" aria-label="Samadhan home">
                    <img src="assets/images/samadhan-logo.svg" alt="" width="40" height="40">
                    <span><strong>Samadhan</strong><small>Support &amp; Resolution Desk</small></span>
                </a>
                <p>A clearer place to manage support requests, follow progress, and keep conversations connected.</p>
                <a class="sm-footer-start" href="register.php">Create your workspace <span aria-hidden="true">→</span></a>
            </div>

            <nav class="sm-footer-column" aria-label="Explore Samadhan">
                <h2>Explore</h2>
                <a href="#smFeatures">Features</a>
                <a href="#smHow">How it works</a>
                <a href="#smGettingStarted">Getting started</a>
                <a href="#smFaq">Frequently asked questions</a>
            </nav>

            <nav class="sm-footer-column" aria-label="Workspace links">
                <h2>Your workspace</h2>
                <a href="register.php">Create an organization</a>
                <a href="login.php">Member sign in</a>
                <a href="#smAccess">Workspace access</a>
                <a href="assets/docs/Samadhan-Getting-Started-Guide.pdf" download>Getting-started guide (PDF)</a>
            </nav>

            <div class="sm-footer-note">
                <span class="sm-footer-note-icon" aria-hidden="true">✓</span>
                <h2>Support, in better order.</h2>
                <p>Bring requests, people, and progress together in one organized workspace.</p>
                <a href="#smHeader">Back to top <span aria-hidden="true">↑</span></a>
            </div>
        </div>
        <div class="sm-footer-bottom">
            <span>© <?= date("Y") ?> Samadhan Support &amp; Resolution Desk</span>
            <span class="sm-footer-bottom-note">Made for clearer support, every day.</span>
            <a href="#smHeader">Back to top <span aria-hidden="true">↑</span></a>
        </div>
    </footer>
</body>
</html>