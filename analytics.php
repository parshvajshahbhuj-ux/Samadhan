<?php
require_once "auth.php";
requireLogin();

$userName = htmlspecialchars($_SESSION["user_name"] ?? "User", ENT_QUOTES, "UTF-8");
$userRole = htmlspecialchars($_SESSION["role"] ?? "user", ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics &amp; Reports | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=16">
    <link rel="stylesheet" href="assets/css/analytics.css?v=6">
    <link rel="stylesheet" href="assets/css/navigation.css?v=18">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=4">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="analytics-page app-page-body">
    <?php
    $activePage = "analytics";
    require "partials/navigation.php";
    ?>

    <main class="analytics-container page-transition-surface">
        <section class="analytics-heading">
            <div>
                <p class="analytics-eyebrow">INSIGHTS &amp; PERFORMANCE</p>
                <h2>Analytics &amp; Reports</h2>
                <p>Track ticket volume, resolution progress, and support trends.</p>
            </div>
            <span id="analyticsUpdated" class="analytics-updated" aria-live="polite"></span>
        </section>

        <section class="analytics-filter-card" aria-label="Analytics filters">
            <div class="analytics-filter-field">
                <label for="analyticsRange">Date range</label>
                <select id="analyticsRange">
                    <option value="last7">Last 7 days</option>
                    <option value="last30" selected>Last 30 days</option>
                    <option value="thisMonth">This month</option>
                    <option value="previousMonth">Previous month</option>
                    <option value="custom">Custom range</option>
                </select>
            </div>
            <div class="analytics-date-fields">
                <div class="analytics-filter-field">
                    <label for="analyticsStartDate">From</label>
                    <input type="date" id="analyticsStartDate" disabled>
                </div>
                <div class="analytics-filter-field">
                    <label for="analyticsEndDate">To</label>
                    <input type="date" id="analyticsEndDate" disabled>
                </div>
            </div>
            <button type="button" id="applyAnalyticsRange" class="btn btn-primary">Apply range</button>
        </section>
        <p id="analyticsError" class="analytics-error" role="alert" hidden></p>

        <section class="analytics-stats" aria-label="Ticket summary">
            <article class="analytics-stat stat-total">
                <span>Total tickets</span><strong id="metricTotal">—</strong>
                <small>Created in selected range</small>
            </article>
            <article class="analytics-stat stat-open">
                <span>Open</span><strong id="metricOpen">—</strong>
                <small>Awaiting action</small>
            </article>
            <article class="analytics-stat stat-progress">
                <span>In progress</span><strong id="metricProgress">—</strong>
                <small>Currently being handled</small>
            </article>
            <article class="analytics-stat stat-resolved">
                <span>Resolved</span><strong id="metricResolved">—</strong>
                <small>Marked resolved</small>
            </article>
            <article class="analytics-stat stat-closed">
                <span>Closed</span><strong id="metricClosed">—</strong>
                <small>Completed tickets</small>
            </article>
            <article class="analytics-stat stat-critical">
                <span>Critical priority</span><strong id="metricCritical">—</strong>
                <small>Needs urgent attention</small>
            </article>
            <article class="analytics-stat stat-rate">
                <span>Resolution rate</span><strong id="metricResolutionRate">—</strong>
                <small>Resolved or closed</small>
            </article>
            <article class="analytics-stat stat-time">
                <span>Average resolution time</span><strong id="metricAverageResolution">—</strong>
                <small id="averageResolutionNote">Based on recorded status changes</small>
            </article>
        </section>

        <section class="analytics-charts" aria-label="Ticket charts">
            <article class="analytics-chart-card chart-wide analytics-chart-trend">
                <div class="analytics-card-heading">
                    <div><span class="analytics-chart-kicker">01 · PERFORMANCE</span><h3>Tickets created vs. resolved</h3><p>Monthly activity in the selected range</p></div>
                    <span class="analytics-chart-mark" aria-hidden="true">↗</span>
                </div>
                <div class="analytics-chart-wrap"><canvas id="monthlyChart" aria-label="Monthly tickets created and resolved chart"></canvas><p class="chart-empty" hidden></p></div>
            </article>
            <article class="analytics-chart-card analytics-chart-status">
                <div class="analytics-card-heading">
                    <div><span class="analytics-chart-kicker">02 · WORKFLOW</span><h3>Ticket status</h3><p>Current status of tickets created in range</p></div>
                    <span class="analytics-chart-mark" aria-hidden="true">◉</span>
                </div>
                <div class="analytics-chart-wrap"><canvas id="statusChart" aria-label="Ticket status distribution chart"></canvas><p class="chart-empty" hidden></p></div>
            </article>
            <article class="analytics-chart-card analytics-chart-priority">
                <div class="analytics-card-heading">
                    <div><span class="analytics-chart-kicker">03 · URGENCY</span><h3>Priority distribution</h3><p>Tickets by assigned priority</p></div>
                    <span class="analytics-chart-mark" aria-hidden="true">!</span>
                </div>
                <div class="analytics-chart-wrap"><canvas id="priorityChart" aria-label="Ticket priority distribution chart"></canvas><p class="chart-empty" hidden></p></div>
            </article>
            <article class="analytics-chart-card analytics-chart-category">
                <div class="analytics-card-heading">
                    <div><span class="analytics-chart-kicker">04 · TOPICS</span><h3>Tickets by category</h3><p>Support requests grouped by category</p></div>
                    <span class="analytics-chart-mark" aria-hidden="true">▦</span>
                </div>
                <div class="analytics-chart-wrap"><canvas id="categoryChart" aria-label="Tickets by category chart"></canvas><p class="chart-empty" hidden></p></div>
            </article>
            <article class="analytics-chart-card analytics-chart-weekday">
                <div class="analytics-card-heading">
                    <div><span class="analytics-chart-kicker">05 · ACTIVITY PATTERN</span><h3>Tickets by day of week</h3><p>When tickets were submitted in the selected range</p></div>
                    <span class="analytics-chart-mark" aria-hidden="true">▤</span>
                </div>
                <div class="analytics-chart-wrap"><canvas id="weekdayChart" aria-label="Ticket creation by day of week chart"></canvas><p class="chart-empty" hidden></p></div>
            </article>
            <article class="analytics-chart-card chart-wide analytics-chart-daily">
                <div class="analytics-card-heading">
                    <div><span class="analytics-chart-kicker">06 · DAILY VOLUME</span><h3>Daily ticket creation</h3><p>New tickets for each day in the selected range</p></div>
                    <span class="analytics-chart-mark" aria-hidden="true">⌁</span>
                </div>
                <div class="analytics-chart-wrap analytics-daily-wrap"><canvas id="dailyChart" aria-label="Daily ticket creation trend chart"></canvas><p class="chart-empty" hidden></p></div>
            </article>
            <article class="analytics-chart-card chart-wide analytics-satisfaction-card">
                <div class="analytics-card-heading analytics-satisfaction-heading">
                    <div><span class="analytics-chart-kicker">07 · CUSTOMER EXPERIENCE</span><h3>Customer satisfaction</h3><p>Ratings submitted for resolved and closed tickets in the selected range</p></div>
                    <div class="analytics-satisfaction-score">
                        <strong id="metricSatisfaction">—</strong>
                        <span id="metricSatisfactionStars" class="analytics-satisfaction-stars" aria-label="No customer rating yet">☆☆☆☆☆</span>
                        <span>AVERAGE SCORE</span>
                        <small><b id="metricSatisfactionCount">0</b> ratings</small>
                    </div>
                </div>
                <div class="analytics-satisfaction-chart-meta">
                    <span><i aria-hidden="true"></i> Rating breakdown</span>
                    <span><strong id="metricSatisfactionPositive">—</strong> rated 4 or 5 stars</span>
                </div>
                <div class="analytics-chart-wrap analytics-satisfaction-wrap"><canvas id="satisfactionChart" aria-label="Customer satisfaction ratings from five to one stars, including counts and percentages"></canvas><p class="chart-empty" hidden></p></div>
            </article>
        </section>

        <section class="analytics-export-card">
            <div class="analytics-card-heading">
                <div><h3>Export ticket report</h3><p>Download ticket details created in the selected date range.</p></div>
            </div>
            <form id="analyticsExportForm" action="api/export_tickets.php" method="get">
                <input type="hidden" id="exportStartDate" name="start_date">
                <input type="hidden" id="exportEndDate" name="end_date">
                <div class="analytics-export-filters">
                    <div class="analytics-filter-field">
                        <label for="exportStatus">Status</label>
                        <select id="exportStatus" name="status">
                            <option value="">All statuses</option>
                            <option value="Open">Open</option>
                            <option value="In Progress">In progress</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <div class="analytics-filter-field">
                        <label for="exportPriority">Priority</label>
                        <select id="exportPriority" name="priority">
                            <option value="">All priorities</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Export CSV</button>
                </div>
            </form>
        </section>
    </main>
    <?php require "partials/footer.php"; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script src="assets/js/analytics.js?v=8"></script>
</body>
</html>
