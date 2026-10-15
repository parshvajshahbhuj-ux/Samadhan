<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

requireLogin();

$ticketId = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
if (!$ticketId || $ticketId <= 0) {
    http_response_code(400);
    $pageError = "A valid ticket ID is required to view ticket activity.";
} else {
    try {
        $ticketSql = "
            SELECT id, subject, category, priority, status, description, created_at
            FROM tickets
            WHERE id = :ticket_id
              AND organization_id = :organization_id
        ";
        $ticketParams = [
            ":ticket_id" => $ticketId,
            ":organization_id" => getOrganizationId(),
        ];
        if (!isSupportStaff()) {
            $ticketSql .= " AND user_id = :user_id";
            $ticketParams[":user_id"] = (int)($_SESSION["user_id"] ?? 0);
        }

        $ticketStmt = $conn->prepare($ticketSql . " LIMIT 1");
        $ticketStmt->execute($ticketParams);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            unset($ticket);
            http_response_code(404);
            $pageError = "Ticket not found or you do not have permission to view it.";
        }
    } catch (Throwable $e) {
        error_log("HelpDesk Pro ticket activity page error: " . $e->getMessage());
        http_response_code(500);
        $pageError = "Ticket activity could not be loaded right now.";
    }
}

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
$statusClass = isset($ticket)
    ? strtolower(str_replace(" ", "-", (string)$ticket["status"]))
    : "";
$priorityClass = isset($ticket)
    ? strtolower((string)$ticket["priority"])
    : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($ticket) ? "Ticket #" . (int)$ticket["id"] . " Activity" : "Ticket Activity" ?> | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=17">
    <link rel="stylesheet" href="assets/css/navigation.css?v=18">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=13">
    <link rel="stylesheet" href="assets/css/ticket-activity.css?v=2">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="app-page-body ticket-activity-page">
    <?php
    $activePage = "";
    require __DIR__ . "/partials/navigation.php";
    ?>

    <main class="ticket-activity-main page-transition-surface">
        <?php if (isset($pageError)): ?>
            <section class="ticket-activity-error-card" role="alert">
                <span class="ticket-activity-error-icon" aria-hidden="true">!</span>
                <p class="ticket-activity-eyebrow">TICKET ACTIVITY</p>
                <h1>We couldn't open this activity</h1>
                <p><?= $escape($pageError) ?></p>
                <a class="ticket-activity-button" href="index.php">Back to dashboard</a>
            </section>
        <?php else: ?>
            <a class="ticket-activity-back" href="index.php">
                <span aria-hidden="true">←</span> Back to dashboard
            </a>

            <section class="ticket-activity-hero">
                <div class="ticket-activity-hero-top">
                    <div>
                        <p class="ticket-activity-eyebrow">TICKET ACTIVITY</p>
                        <h1>Progress timeline</h1>
                        <p class="ticket-activity-subtitle">A complete history of updates and replies for this support request.</p>
                    </div>
                    <span class="ticket-activity-hero-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M5 18.5V5.5M5 18.5h14M8.5 15l3-3 2.5 2 4-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="8.5" cy="15" r="1" fill="currentColor"/>
                            <circle cx="11.5" cy="12" r="1" fill="currentColor"/>
                            <circle cx="14" cy="14" r="1" fill="currentColor"/>
                            <circle cx="18" cy="9" r="1" fill="currentColor"/>
                        </svg>
                    </span>
                </div>

                <div class="ticket-activity-ticket-summary">
                    <div class="ticket-activity-ticket-title">
                        <span class="ticket-activity-ticket-number">TICKET #<?= (int)$ticket["id"] ?></span>
                        <h2><?= $escape($ticket["subject"]) ?></h2>
                    </div>
                    <div class="ticket-activity-ticket-badges">
                        <span class="ticket-activity-badge status-<?= $escape($statusClass) ?>"><?= $escape($ticket["status"]) ?></span>
                        <span class="ticket-activity-badge priority-<?= $escape($priorityClass) ?>"><?= $escape($ticket["priority"]) ?> priority</span>
                    </div>
                    <div class="ticket-activity-ticket-meta">
                        <span><small>Category</small><strong><?= $escape($ticket["category"]) ?></strong></span>
                        <span><small>Submitted</small><strong><?= $escape(date("M j, Y · g:i A", strtotime((string)$ticket["created_at"]))) ?></strong></span>
                    </div>
                </div>

                <div class="ticket-activity-metrics" aria-label="Activity summary">
                    <article>
                        <span class="ticket-activity-metric-icon metric-events" aria-hidden="true">↗</span>
                        <span><strong id="activityEventCount">—</strong><small>Timeline events</small></span>
                    </article>
                    <article>
                        <span class="ticket-activity-metric-icon metric-status" aria-hidden="true">◉</span>
                        <span><strong id="activityStatusCount">—</strong><small>Status changes</small></span>
                    </article>
                    <article>
                        <span class="ticket-activity-metric-icon metric-replies" aria-hidden="true">↩</span>
                        <span><strong id="activityReplyCount">—</strong><small>Conversation replies</small></span>
                    </article>
                </div>
            </section>

            <div class="ticket-activity-content-grid">
                <section class="ticket-activity-history" aria-labelledby="activityHistoryHeading">
                    <div class="ticket-activity-section-heading">
                        <div>
                            <p class="ticket-activity-eyebrow">THE FULL STORY</p>
                            <h2 id="activityHistoryHeading">Activity history</h2>
                        </div>
                        <span class="ticket-activity-live-label"><i aria-hidden="true"></i> Chronological</span>
                    </div>
                    <div id="ticketActivityHistory" data-ticket-id="<?= (int)$ticket["id"] ?>" aria-live="polite">
                        <p class="ticket-activity-loading">Loading ticket activity...</p>
                    </div>
                </section>

                <aside class="ticket-activity-description">
                    <span class="ticket-activity-description-icon" aria-hidden="true">i</span>
                    <p class="ticket-activity-eyebrow">REQUEST DETAILS</p>
                    <h2>Original description</h2>
                    <p class="ticket-activity-description-text"><?= nl2br($escape($ticket["description"])) ?></p>
                    <a href="index.php?open_ticket=<?= (int)$ticket["id"] ?>" class="ticket-activity-return-link">Open ticket details <span aria-hidden="true">→</span></a>
                </aside>
            </div>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . "/partials/footer.php"; ?>
    <?php if (isset($ticket)): ?>
        <script src="assets/js/ticket-activity.js?v=1" defer></script>
    <?php endif; ?>
</body>
</html>
