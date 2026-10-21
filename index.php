<?php
require_once "auth.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit;
}
requireLogin();
if (empty($_SESSION["ticket_messages_csrf"])) {
    $_SESSION["ticket_messages_csrf"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Samadhan | Support &amp; Resolution Desk</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">

    <link rel="stylesheet" href="assets/css/style.css?v=17">
    <link rel="stylesheet" href="assets/css/navigation.css?v=26">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=21">
    <script src="assets/js/page-transitions.js?v=5" defer></script>

    <!-- Shared JS config: runs after session is available -->
    <script>
        window.userRole = <?= json_encode($_SESSION["role"] ?? "user") ?>;
        window.userId = <?= (int)($_SESSION["user_id"] ?? 0) ?>;
        window.ticketMessagesCsrf = <?= json_encode($_SESSION["ticket_messages_csrf"]) ?>;
    </script>

    <!-- Dashboard layout refinements -->
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f6fb; color: #172033; }

        .topbar {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            padding: 22px clamp(20px, 5vw, 72px);
            min-height: 112px;
        }
        .topbar .brand { flex: 1 1 230px; min-width: 200px; }
        .topbar .brand h1 { margin: 0 0 6px; font-size: clamp(24px, 2vw, 30px); }
        .topbar .brand p { margin: 0; line-height: 1.45; }

        #addTicketBtn { white-space: nowrap; min-height: 44px; }
        .analytics-nav-link {
            display: inline-flex;
            align-items: center;
            min-height: 42px;
            padding: 0 15px;
            border: 1px solid rgba(148, 163, 184, .35);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }
        .analytics-nav-link:hover { background: rgba(148, 163, 184, .12); }
        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 13px;
            border: 1px solid #16a34a;
            border-radius: 8px;
            background: #16a34a;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-whatsapp:hover { background: #15803d; border-color: #15803d; }

        .header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            margin-left: auto;
        }
        .user-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            width: auto;
            min-width: 190px;
            padding: 10px 14px;
        }
        .user-details { min-width: 0; }
        .logout-btn { white-space: nowrap; }
        .optional-label { color: #64748b; font-size: 12px; font-weight: 400; }
        .phone-hint { display: block; margin-top: 6px; color: #64748b; font-size: 12px; }

        .container { width: min(1440px, calc(100% - 48px)); margin: 0 auto; padding: 30px 0 48px; }

        /* ---------- Statistics cards ---------- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card { min-width: 0; min-height: 118px; padding: 22px 24px; }
        .stat-card span { display: block; margin-bottom: 10px; }
        .stat-card h2 { margin: 0; font-size: 30px; line-height: 1.2; }

        .dashboard-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: 28px; }
        .dashboard-stats .stat-card {
            position: relative;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
        }
        .dashboard-stats .stat-card::before {
            content: "";
            position: absolute;
            left: 0; top: 18px; bottom: 18px;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: #2563eb;
        }
        .dashboard-stats .stat-open::before     { background: #f59e0b; }
        .dashboard-stats .stat-progress::before { background: #6366f1; }
        .dashboard-stats .stat-resolved::before { background: #10b981; }
        .dashboard-stats .stat-closed::before   { background: #64748b; }
        .dashboard-stats .stat-critical::before { background: #ef4444; }
        .dashboard-stats .stat-card span { color: #64748b; font-size: 14px; }
        .dashboard-stats .stat-card h2   { color: #0f172a; font-size: 30px; font-weight: 750; }
        .dashboard-stats .stat-card small {
            display: block;
            color: #94a3b8;
            margin-top: 8px;
            font-size: 12px;
        }

        .sla-overview {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin: -8px 0 24px;
        }
        .sla-overview-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 76px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }
        .sla-overview-card span { color: #64748b; font-size: 13px; font-weight: 600; }
        .sla-overview-card strong { color: #0f172a; font-size: 24px; }
        .sla-overview-card.is-alert strong { color: #dc2626; }
        .sla-description { margin: -12px 0 22px; color: #64748b; font-size: 12px; line-height: 1.5; }
        .sla-cell { min-width: 165px; }
        .sla-line { display: flex; align-items: center; gap: 6px; margin: 3px 0; white-space: nowrap; }
        .sla-caption { width: 76px; color: #64748b; font-size: 11px; }
        .sla-pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
        }
        .sla-met { background: #dcfce7; color: #166534; }
        .sla-due { background: #dbeafe; color: #1d4ed8; }
        .sla-overdue, .sla-breached { background: #fee2e2; color: #b91c1c; }
        .sla-unavailable { background: #f1f5f9; color: #64748b; }
        .sla-line small { color: #94a3b8; font-size: 10px; }
        /* ---------- Reports section ---------- */
        .reports-section { margin-bottom: 28px; }

        .reports-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        .reports-heading h2 { margin: 0 0 5px; font-size: 21px; color: #172033; }
        .reports-heading p  { margin: 0; color: #64748b; font-size: 13px; }

        #refreshReportsBtn { min-height: 38px; white-space: nowrap; }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .report-card {
            min-width: 0;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .03);
        }
        .report-card-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 18px;
        }
        .report-card-heading h3    { margin: 0; font-size: 15px; color: #1e293b; }
        .report-card-heading span  { color: #94a3b8; font-size: 12px; }

        .report-bars { display: flex; flex-direction: column; gap: 14px; }
        .report-row {
            display: grid;
            grid-template-columns: minmax(85px, 115px) minmax(40px, 1fr) 32px;
            align-items: center;
            gap: 10px;
        }
        .report-label {
            color: #475569;
            font-size: 13px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .report-track {
            height: 8px;
            background: #f1f5f9;
            border-radius: 99px;
            overflow: hidden;
        }
        .report-fill {
            height: 100%;
            width: 0;
            border-radius: 99px;
            transition: width .35s ease;
            background: #3b82f6;
        }
        .report-row:nth-child(2) .report-fill { background: #6366f1; }
        .report-row:nth-child(3) .report-fill { background: #10b981; }
        .report-row:nth-child(4) .report-fill { background: #f59e0b; }
        .report-value {
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            text-align: right;
        }
        .report-loading, .report-empty { margin: 0; color: #94a3b8; font-size: 13px; }

        /* Trend mini-chart */
        .trend-chart {
            min-height: 145px;
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            gap: 10px;
            padding-top: 8px;
        }
        .trend-column {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 132px;
            gap: 8px;
        }
        .trend-value { color: #475569; font-size: 11px; font-weight: 700; }
        .trend-track {
            width: min(30px, 75%);
            height: 90px;
            display: flex;
            align-items: flex-end;
            background: #f1f5f9;
            border-radius: 7px 7px 3px 3px;
            overflow: hidden;
        }
        .trend-fill {
            width: 100%;
            min-height: 2px;
            border-radius: 7px 7px 3px 3px;
            background: linear-gradient(180deg, #60a5fa, #2563eb);
        }
        .trend-label { color: #64748b; font-size: 11px; white-space: nowrap; }

        .reports-updated { margin: 10px 2px 0; color: #94a3b8; font-size: 11px; text-align: right; }

        /* ---------- Filter bar ---------- */
        .filter-section {
            display: grid;
            grid-template-columns: minmax(220px, 2fr) minmax(140px, 1fr) minmax(140px, 1fr) auto;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }
        .filter-section.has-assignee-filter {
            grid-template-columns: minmax(220px, 2fr) repeat(3, minmax(140px, 1fr)) auto;
        }
        .search-box { min-width: 0; }
        .search-box input,
        .filter-section select { width: 100%; min-width: 0; height: 44px; }

        #exportReportsBtn { min-height: 44px; padding: 0 18px; white-space: nowrap; }

        /* ---------- Ticket table ---------- */
        .table-card { margin-top: 0; overflow: hidden; }
        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 24px;
        }
        .ticket-status-partitions {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 0 24px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .ticket-status-partition {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 13px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #fff;
            color: #52637b;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .16s ease, border-color .16s ease, color .16s ease;
        }
        .ticket-status-partition:hover { border-color: #bfcee2; background: #f8fafc; }
        .ticket-status-partition[aria-pressed="true"] {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .ticket-status-partition-count {
            display: inline-grid;
            min-width: 21px;
            height: 21px;
            place-items: center;
            padding: 0 5px;
            border-radius: 999px;
            background: #eef2f7;
            color: #52637b;
            font-size: 11px;
            font-weight: 700;
        }
        .ticket-status-partition[aria-pressed="true"] .ticket-status-partition-count {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .table-wrapper { overflow-x: auto; }
        #ticketTable { width: 100%; border-collapse: collapse; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1000px) {
            .topbar { gap: 16px; }
            .header-actions { flex: 1 1 100%; justify-content: space-between; margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-section,
            .filter-section.has-assignee-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .search-box { grid-column: 1 / -1; }
            #exportReportsBtn { width: 100%; }
        }

        @media (max-width: 900px) {
            .dashboard-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .reports-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 560px) {
            .container { width: calc(100% - 28px); padding-top: 20px; }
            .topbar { padding: 18px 14px; }
            .stats-grid { gap: 12px; }
            .stat-card { padding: 16px; min-height: 100px; }
            .filter-section,
            .filter-section.has-assignee-filter { grid-template-columns: 1fr; }
            .search-box { grid-column: auto; }
            .header-actions { align-items: stretch; flex-direction: column; }
            .user-badge { width: 100%; }
            .table-header { padding: 18px; }
            .ticket-status-partitions { padding: 0 18px 14px; }

            .table-wrapper { overflow: visible; padding: 0 12px 12px; }
            #ticketTable,
            #ticketTable tbody {
                display: block;
                width: 100%;
                min-width: 0;
            }
            #ticketTable thead { display: none; }
            #ticketTable tbody {
                display: grid;
                gap: 12px;
            }
            #ticketTable .ticket-row {
                display: block;
                overflow: hidden;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                background: #fff;
                box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
            }
            #ticketTable .ticket-row:hover { background: #fff; }
            #ticketTable .ticket-row td {
                display: grid;
                grid-template-columns: minmax(68px, .55fr) minmax(0, 1fr);
                align-items: start;
                gap: 12px;
                min-width: 0;
                padding: 11px 14px;
                border-top: 1px solid #eef2f7;
                overflow-wrap: anywhere;
            }
            #ticketTable .ticket-row td:first-child { border-top: 0; }
            #ticketTable .ticket-row td:nth-child(1)::before { content: "No."; }
            #ticketTable .ticket-row td:nth-child(2)::before { content: "Subject"; }
            #ticketTable .ticket-row td:nth-child(3)::before { content: "Name"; }
            #ticketTable .ticket-row td:nth-child(4)::before { content: "Category"; }
            #ticketTable .ticket-row td:nth-child(5)::before { content: "Priority"; }
            #ticketTable .ticket-row td:nth-child(6)::before { content: "Status"; }
            #ticketTable .ticket-row td:nth-child(7)::before { content: "SLA"; }
            #ticketTable .ticket-row td:nth-child(8)::before {
                content: "Actions";
                flex: 0 0 100%;
            }
            #ticketTable .ticket-row td::before {
                color: #64748b;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: .04em;
                text-transform: uppercase;
            }
            #ticketTable .ticket-row td.sla-cell {
                grid-template-columns: minmax(68px, .55fr) minmax(0, 1fr);
            }
            #ticketTable .sla-line { flex-wrap: wrap; white-space: normal; }
            #ticketTable .sla-caption { flex: 0 0 76px; }
            .dashboard-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .dashboard-stats .stat-card { padding: 15px 16px 15px 20px; }
            .dashboard-stats .stat-card h2 { font-size: 25px; }
            .dashboard-stats .stat-card small { line-height: 1.35; }
            .sla-overview { gap: 10px; }
            .sla-overview-card { align-items: flex-start; flex-direction: column; gap: 5px; padding: 13px; }
            .sla-overview-card strong { font-size: 22px; }
            .sla-description { margin-top: -8px; }
            .reports-heading { align-items: flex-start; }
            .report-card { padding: 16px; }
            .report-row { grid-template-columns: 90px minmax(30px, 1fr) 25px; gap: 7px; }
        }

        @media (max-width: 380px) {
            body.app-page-body .container { width: calc(100% - 24px); }
            body.app-page-body .dashboard-stats { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=3">
</head>

<body class="app-page-body">

    <!-- ================= HEADER ================= -->
    <?php
    $activePage = "dashboard";
    $showNewTicketButton = isSupportStaff();
    require "partials/navigation.php";
    ?>

    <!-- ================= MAIN ================= -->
    <main class="container page-transition-surface">

        <section class="dashboard-heading" aria-labelledby="dashboardTitle">
            <div>
                <span class="dashboard-eyebrow">SUPPORT OPERATIONS</span>
                <h1 id="dashboardTitle">Dashboard</h1>
                <p><?= isSupportStaff()
                    ? "A clear view of incoming requests, ticket progress, and service priorities."
                    : "Track your support requests and stay up to date on their progress." ?></p>
            </div>
            <span class="dashboard-heading-mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none">
                    <path d="M8 36V12h7l9 12 9-12h7v24h-7V23l-9 11-9-11v13H8Z" fill="currentColor"/>
                </svg>
            </span>
        </section>

        <!-- Statistics -->
        <section class="stats-grid dashboard-stats">
            <div class="stat-card stat-total">
                <div class="stat-card-heading">
                    <span>Total Tickets</span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 4.75h10a2 2 0 0 1 2 2v2.1a2.75 2.75 0 0 0 0 5.3v2.1a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-2.1a2.75 2.75 0 0 0 0-5.3v-2.1a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/><path d="M10 8.5h4M10 12h4M10 15.5h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </div>
                <h2 id="totalTickets">0</h2>
                <small>All tickets you can access</small>
            </div>
            <div class="stat-card stat-open">
                <div class="stat-card-heading">
                    <span>Open</span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.7"/><path d="M12 7.5v5l3.25 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h2 id="openTickets">0</h2>
                <small>Awaiting action</small>
            </div>
            <div class="stat-card stat-progress">
                <div class="stat-card-heading">
                    <span>In Progress</span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h8M5 12h5M5 17h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="m14 13 2.5 2.5L21 11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h2 id="progressTickets">0</h2>
                <small>Currently being handled</small>
            </div>
            <div class="stat-card stat-resolved">
                <div class="stat-card-heading">
                    <span>Resolved</span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.7"/><path d="m8.5 12.2 2.3 2.3 4.8-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h2 id="resolvedTickets">0</h2>
                <small>Resolved tickets</small>
            </div>
            <div class="stat-card stat-closed">
                <div class="stat-card-heading">
                    <span>Closed</span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5.25" y="10" width="13.5" height="10" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 10V7.5a3.5 3.5 0 0 1 7 0V10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </div>
                <h2 id="closedTickets">0</h2>
                <small>Completed tickets</small>
            </div>
            <div class="stat-card stat-critical">
                <div class="stat-card-heading">
                    <span>Critical Priority</span>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4.5 21 20H3l9-15.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 9v5M12 17.25v.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </div>
                <h2 id="criticalTickets">0</h2>
                <small>Needs urgent attention</small>
            </div>
        </section>

        <section class="sla-overview" aria-label="SLA alerts">
            <div class="sla-overview-card" id="responseSlaCard">
                <span>Overdue first responses</span>
                <strong id="responseSlaOverdue">0</strong>
            </div>
            <div class="sla-overview-card" id="resolutionSlaCard">
                <span>Overdue resolutions</span>
                <strong id="resolutionSlaOverdue">0</strong>
            </div>
        </section>
        <p class="sla-description">
            24/7 targets — Critical: 1h response / 4h resolution; High: 4h / 24h; Medium: 24h / 72h; Low: 48h / 120h.
            First response is measured by the first Main Admin or Support Member reply. Counts reflect the tickets currently shown.
        </p>

        <!-- Filter bar -->
        <section class="filter-section<?= isSupportStaff() ? " has-assignee-filter" : "" ?>">
            <div class="search-box">
                <input type="text" id="searchTicket" placeholder="Search tickets...">
            </div>

            <select id="statusFilter">
                <option value="">All Status</option>
                <option value="Open">Open</option>
                <option value="In Progress">In Progress</option>
                <option value="Resolved">Resolved</option>
                <option value="Closed">Closed</option>
            </select>

            <select id="priorityFilter">
                <option value="">All Priority</option>
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
                <option value="Critical">Critical</option>
            </select>

            <?php if (isSupportStaff()): ?>
                <select id="assigneeFilter" aria-label="Filter tickets by assignee">
                    <option value="">All assignees</option>
                    <option value="unassigned">Unassigned</option>
                </select>
            <?php endif; ?>

            <button type="button" id="exportReportsBtn" class="btn btn-primary" title="Export tickets matching the current filters">
                Export Reports (CSV)
            </button>
        </section>

        <?php if (isAdmin()): ?>
            <section class="assignment-workload" id="assignmentWorkload" aria-label="Support team workload" hidden>
                <div class="assignment-workload-heading">
                    <div><span class="dashboard-eyebrow">TEAM CAPACITY</span><h2>Support member workload</h2></div>
                    <p>Active tickets assigned to each support member</p>
                </div>
                <div class="assignment-workload-grid" id="assignmentWorkloadGrid"></div>
            </section>
        <?php endif; ?>

        <!-- Ticket table -->
        <section class="table-card">
            <div class="table-header">
                <div>
                    <h2>Support Tickets</h2>
                    <p><?= isSupportStaff()
                        ? "Manage and track support requests"
                        : "View and manage your support requests" ?></p>
                </div>
                <span id="ticketCount">0 tickets</span>
            </div>

            <div class="ticket-status-partitions" role="group" aria-label="Filter tickets by status">
                <button class="ticket-status-partition" type="button" data-status-partition="all" aria-pressed="true">
                    All <span class="ticket-status-partition-count" data-status-count="all">0</span>
                </button>
                <button class="ticket-status-partition" type="button" data-status-partition="open" aria-pressed="false">
                    Open <span class="ticket-status-partition-count" data-status-count="open">0</span>
                </button>
                <button class="ticket-status-partition" type="button" data-status-partition="progress" aria-pressed="false">
                    In Progress <span class="ticket-status-partition-count" data-status-count="progress">0</span>
                </button>
                <button class="ticket-status-partition" type="button" data-status-partition="completed" aria-pressed="false">
                    Resolved / Closed <span class="ticket-status-partition-count" data-status-count="completed">0</span>
                </button>
            </div>

            <div class="table-wrapper">
                <table id="ticketTable">
                    <thead>
                        <tr>
                            <th>No. / Ticket ID</th>
                            <th>Subject</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <?php if (isSupportStaff()): ?>
                                <th>Assignement</th>
                            <?php endif; ?>
                            <th>SLA</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ticketBody"><!-- AJAX data loads here --></tbody>
                </table>
            </div>

            <div id="emptyMessage" class="empty-message">No tickets found.</div>
        </section>
    </main>
    <?php require "partials/footer.php"; ?>

    <?php if (isSupportStaff()): ?>
    <!-- ================= NEW TICKET MODAL ================= -->
    <div id="ticketModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2>Create New Ticket</h2>
                    <p>Submit a new support request</p>
                </div>
                <button id="closeModal" class="close-btn">&times;</button>
            </div>

            <form id="ticketForm" novalidate>
                <input type="hidden" id="supportInquiryId" name="support_inquiry_id" value="">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter your name">
                    <small id="nameError" class="error"></small>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email">
                    <small id="emailError" class="error"></small>
                </div>

                <div class="form-group">
                    <label for="phone">Phone number <span class="optional-label">(optional)</span></label>
                    <input type="tel" id="phone" name="phone" maxlength="25" autocomplete="tel" inputmode="tel" placeholder="Include country code, e.g. +1 555 123 4567">
                    <small class="phone-hint">Used to enable WhatsApp contact for this ticket.</small>
                    <small id="phoneError" class="error"></small>
                </div>

                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" placeholder="Enter ticket subject">
                    <small id="subjectError" class="error"></small>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">Select Category</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Software">Software</option>
                        <option value="Network">Network</option>
                        <option value="Account">Account</option>
                        <option value="Other">Other</option>
                    </select>
                    <small id="categoryError" class="error"></small>
                </div>

                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority">
                        <option value="">Select Priority</option>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                    <small id="priorityError" class="error"></small>
                </div>

                <div class="form-group">
                    <label for="createTicketAssignee">Assign to yourself or a Support Member</label>
                    <select id="createTicketAssignee" name="assigned_to_user_id">
                        <option value="<?= (int)($_SESSION["user_id"] ?? 0) ?>" data-self-option>Self</option>
                    </select>
                    <small class="phone-hint">Assign this ticket to yourself or a Support Member in your organization.</small>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5" placeholder="Describe your problem..."></textarea>
                    <small id="descriptionError" class="error"></small>
                </div>

                <div class="form-actions">
                    <button type="button" id="cancelBtn" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= EDIT TICKET MODAL ================= -->
    <div id="editTicketModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2>Edit Ticket</h2>
                    <p>Update support ticket information</p>
                </div>
                <button type="button" class="modal-close" id="closeEditModal">&times;</button>
            </div>

            <form id="editTicketForm">
                <input type="hidden" id="editTicketId" name="id">

                <div class="form-group">
                    <label for="editName">Name</label>
                    <input type="text" id="editName" name="name" required>
                </div>

                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" required>
                </div>

                <div class="form-group">
                    <label for="editPhone">Phone number <span class="optional-label">(optional)</span></label>
                    <input type="tel" id="editPhone" name="phone" maxlength="25" autocomplete="tel" inputmode="tel" placeholder="Include country code, e.g. +1 555 123 4567">
                    <small class="phone-hint">Used to enable WhatsApp contact for this ticket.</small>
                </div>

                <div class="form-group">
                    <label for="editSubject">Subject</label>
                    <input type="text" id="editSubject" name="subject" required>
                </div>

                <div class="form-group">
                    <label for="editCategory">Category</label>
                    <select id="editCategory" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Software">Software</option>
                        <option value="Network">Network</option>
                        <option value="Account">Account</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="editPriority">Priority</label>
                    <select id="editPriority" name="priority" required>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="editDescription">Description</label>
                    <textarea id="editDescription" name="description" rows="5" required></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Ticket</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================= SCRIPTS ================= -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        // Export the same ticket subset currently selected by the dashboard filters.
        $(document).on("click", "#exportReportsBtn", function () {
            const params = new URLSearchParams();
            const search   = $("#searchTicket").val().trim();
            const status   = $("#statusFilter").val();
            const priority = $("#priorityFilter").val();
            const assignedTo = $("#assigneeFilter").val();
            const statusGroup = $(".ticket-status-partition[aria-pressed='true']").data("status-partition");

            if (search)   params.set("search", search);
            if (status)   params.set("status", status);
            if (priority) params.set("priority", priority);
            if (assignedTo) params.set("assigned_to", assignedTo);
            if (statusGroup && statusGroup !== "all") params.set("status_group", statusGroup);

            const query = params.toString();
            window.location.href = "api/export_tickets.php" + (query ? "?" + query : "");
        });
    </script>

    <script src="assets/js/app.js?v=49"></script>
    <script src="assets/js/reports.js?v=1"></script>
</body>
</html>