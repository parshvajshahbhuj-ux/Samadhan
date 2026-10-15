<?php
require_once "auth.php";
require_once "db.php";
requireLogin();

$isAdmin = isSupportStaff();
$userId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();
if (empty($_SESSION["support_inquiry_csrf"])) {
    $_SESSION["support_inquiry_csrf"] = bin2hex(random_bytes(32));
}

$inquiryId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$inboxView = $isAdmin && (string)($_GET["view"] ?? "") === "overdue" ? "overdue" : "all";
$inquiry = null;
$requests = [];
$allRequests = [];
$overdueRequestCount = 0;
$loadError = false;

try {
    if ($inquiryId && $inquiryId > 0) {
        $sql = "
            SELECT
                inquiry.id,
                inquiry.user_id,
                inquiry.name,
                inquiry.email,
                inquiry.subject,
                inquiry.message,
                inquiry.ticket_id,
                inquiry.created_at,
                inquiry.completed_at,
                UNIX_TIMESTAMP(inquiry.created_at) + 86400 AS deadline_timestamp,
                linked_ticket.status AS ticket_status,
                linked_ticket.updated_at AS ticket_updated_at,
                linked_ticket.assigned_to_user_id,
                ticket_assignee.name AS ticket_assignee_name
            FROM support_inquiries inquiry
            LEFT JOIN tickets linked_ticket
                ON linked_ticket.id = inquiry.ticket_id
                AND linked_ticket.organization_id = inquiry.organization_id
            LEFT JOIN organization_memberships ticket_assignee_membership
                ON ticket_assignee_membership.user_id = linked_ticket.assigned_to_user_id
                AND ticket_assignee_membership.organization_id = inquiry.organization_id
                AND ticket_assignee_membership.role IN ('admin', 'support')
            LEFT JOIN users ticket_assignee
                ON ticket_assignee.id = ticket_assignee_membership.user_id
            WHERE inquiry.id = :id
              AND inquiry.organization_id = :organization_id
        ";
        $params = [
            ":id" => $inquiryId,
            ":organization_id" => $organizationId,
        ];
        if (!$isAdmin) {
            $sql .= " AND inquiry.user_id = :user_id";
            $params[":user_id"] = $userId;
        }
        $stmt = $conn->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($inquiry && !empty($inquiry["ticket_id"]) && empty($inquiry["assigned_to_user_id"]) && isSupportMember()) {
            $mainAdminStmt = $conn->prepare("
                SELECT users.name
                FROM organization_memberships membership
                INNER JOIN users ON users.id = membership.user_id
                WHERE membership.organization_id = :organization_id
                  AND membership.role = 'admin'
                ORDER BY membership.created_at ASC, users.id ASC
                LIMIT 1
            ");
            $mainAdminStmt->execute([":organization_id" => $organizationId]);
            $mainAdminName = $mainAdminStmt->fetchColumn();
            if (is_string($mainAdminName) && $mainAdminName !== "") {
                $inquiry["main_admin_name"] = $mainAdminName;
            }
        }
    } else {
        $sql = "
            SELECT
                inquiry.id,
                inquiry.name,
                inquiry.subject,
                inquiry.ticket_id,
                inquiry.created_at,
                inquiry.completed_at,
                UNIX_TIMESTAMP(inquiry.created_at) + 86400 AS deadline_timestamp,
                linked_ticket.priority AS ticket_priority,
                linked_ticket.status AS ticket_status,
                linked_ticket.updated_at AS ticket_updated_at,
                COALESCE(latest.message, inquiry.message) AS latest_message,
                COALESCE(latest.created_at, inquiry.created_at) AS latest_activity,
                latest.user_role AS latest_reply_role
            FROM support_inquiries inquiry
            LEFT JOIN tickets linked_ticket
                ON linked_ticket.id = inquiry.ticket_id
                AND linked_ticket.organization_id = inquiry.organization_id
            LEFT JOIN (
                SELECT thread.support_inquiry_id, thread.message, thread.created_at, thread.user_role
                FROM support_inquiry_messages thread
                INNER JOIN (
                    SELECT support_inquiry_id, MAX(id) AS latest_id
                    FROM support_inquiry_messages
                    GROUP BY support_inquiry_id
                ) latest_ids ON latest_ids.latest_id = thread.id
            ) latest ON latest.support_inquiry_id = inquiry.id
        ";
        $sql .= " WHERE inquiry.organization_id = :organization_id";
        $params = [":organization_id" => $organizationId];
        if (!$isAdmin) {
            $sql .= " AND inquiry.user_id = :user_id";
            $params[":user_id"] = $userId;
        }
        $sql .= " ORDER BY COALESCE(latest.created_at, inquiry.created_at) DESC, inquiry.id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $allRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allRequests as $request) {
            $request["sla"] = supportRequestSla($request);
            if ($request["sla"]["state"] === "overdue") {
                $overdueRequestCount++;
            }
            if ($inboxView !== "overdue" || $request["sla"]["state"] === "overdue") {
                $requests[] = $request;
            }
        }
    }
} catch (PDOException $e) {
    error_log("HelpDesk Pro support request page error: " . $e->getMessage());
    $loadError = true;
}

function supportRequestSla(array $request): array
{
    $deadline = (int)$request["deadline_timestamp"];
    $ticketStatus = (string)($request["ticket_status"] ?? "");
    $isCompleted = in_array($ticketStatus, ["Resolved", "Closed"], true);

    if ($isCompleted) {
        $completedAt = $request["completed_at"] ?: ($request["ticket_updated_at"] ?? null);
        $completedTimestamp = $completedAt ? strtotime((string)$completedAt) : null;
        return [
            "state" => "completed",
            "deadline" => $deadline,
            "completed_at" => $completedAt,
            "completion_timing" => $completedTimestamp !== false && $completedTimestamp !== null && $completedTimestamp <= $deadline
                ? "on-time"
                : "late",
            "remaining" => 0,
        ];
    }

    $remaining = $deadline - time();
    return [
        "state" => $remaining <= 0 ? "overdue" : ($remaining <= 21600 ? "due-soon" : "on-track"),
        "deadline" => $deadline,
        "completed_at" => null,
        "remaining" => abs($remaining),
    ];
}

function formatSupportRequestDuration(int $seconds): string
{
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($hours >= 24) {
        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;
        return $days . "d " . $remainingHours . "h";
    }
    if ($hours > 0) {
        return $hours . "h " . $minutes . "m";
    }
    return max(1, $minutes) . "m";
}

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
};
$activePage = "requests";
$csrfToken = $escape($_SESSION["support_inquiry_csrf"]);
$inquirySla = $inquiry ? supportRequestSla($inquiry) : null;
$ticketAssigneeLabel = "";
if ($inquiry && !empty($inquiry["ticket_id"])) {
    if (empty($inquiry["assigned_to_user_id"])) {
        $ticketAssigneeLabel = (string)($inquiry["main_admin_name"] ?? "Self");
    } elseif ((int)$inquiry["assigned_to_user_id"] === $userId) {
        $ticketAssigneeLabel = "Self";
    } else {
        $ticketAssigneeLabel = (string)($inquiry["ticket_assignee_name"] ?? "Support Member");
    }
}
$activeRequestCount = 0;
$completedRequestCount = 0;
foreach ($allRequests as $requestSummary) {
    if (in_array((string)($requestSummary["ticket_status"] ?? ""), ["Resolved", "Closed"], true)) {
        $completedRequestCount++;
    } else {
        $activeRequestCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $inquiry ? "Request #" . (int)$inquiry["id"] : ($isAdmin ? "Customer Requests" : "My Support Requests") ?> | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/navigation.css?v=18">
    <link rel="stylesheet" href="assets/css/support-inquiries.css?v=14">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=4">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="app-page-body support-requests-body">
    <?php require "partials/navigation.php"; ?>
    <main class="support-inquiry-page page-transition-surface">
        <?php if ($inquiryId && $inquiry): ?>
            <a class="support-inquiry-back" href="support-inquiries.php">&larr; <?= $isAdmin ? "All customer requests" : "My support requests" ?></a>
            <section class="support-inquiry-card support-thread-card"
                     id="supportConversation"
                     data-inquiry-id="<?= (int)$inquiry["id"] ?>"
                     data-csrf-token="<?= $csrfToken ?>"
                     data-is-admin="<?= $isAdmin ? "1" : "0" ?>">
                <header class="support-inquiry-heading">
                    <div>
                        <span class="support-inquiry-eyebrow">SUPPORT CENTER <span aria-hidden="true">/</span> <?= $isAdmin ? "CUSTOMER REQUEST" : "YOUR REQUEST" ?></span>
                        <h1><?= $escape($inquiry["subject"]) ?></h1>
                        <p class="support-inquiry-subtitle"><?= $isAdmin ? "Continue the conversation with this customer." : "View your conversation with the support team." ?></p>
                    </div>
                    <div class="support-inquiry-heading-meta">
                        <span class="support-inquiry-id">Request #<?= (int)$inquiry["id"] ?></span>
                        <time datetime="<?= $escape($inquiry["created_at"]) ?>"><?= $escape(date("M j, Y · g:i A", strtotime((string)$inquiry["created_at"]))) ?></time>
                    </div>
                </header>
                <div class="support-inquiry-contact">
                    <div class="support-inquiry-requester">
                        <span class="support-inquiry-avatar" aria-hidden="true"><?= $escape(strtoupper(substr((string)$inquiry["name"], 0, 1))) ?></span>
                        <span><small><?= $isAdmin ? "CUSTOMER" : "REQUESTED BY" ?></small><strong><?= $escape($inquiry["name"]) ?></strong></span>
                    </div>
                    <?php if ($isAdmin): ?>
                        <div class="support-inquiry-email">
                            <span class="support-inquiry-contact-icon" aria-hidden="true">@</span>
                            <span><small>EMAIL ADDRESS</small><a href="mailto:<?= $escape($inquiry["email"]) ?>"><?= $escape($inquiry["email"]) ?></a></span>
                        </div>
                        <?php if (!empty($inquiry["ticket_id"])): ?>
                            <div class="support-inquiry-assignee">
                                <span class="support-inquiry-contact-icon" aria-hidden="true">↗</span>
                                <span>
                                    <small>ASSIGNED TO</small>
                                    <strong><?= $escape($ticketAssigneeLabel) ?></strong>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="support-inquiry-request-status">
                        <span class="request-status-dot"></span>
                        <span><?= !empty($inquiry["ticket_id"]) ? "Ticket #" . (int)$inquiry["ticket_id"] . " linked" : "Awaiting ticket" ?></span>
                    </div>
                </div>
                <?php
                    $deadlineText = date("M j, Y · g:i A", $inquirySla["deadline"]);
                    if ($inquirySla["state"] === "completed") {
                        $completedTime = $inquirySla["completed_at"]
                            ? date("M j, Y · g:i A", strtotime((string)$inquirySla["completed_at"]))
                            : "time unavailable";
                        $deadlineTitle = $inquirySla["completion_timing"] === "on-time"
                            ? "Completed within 24 hours"
                            : "Completed after the 24-hour target";
                        $deadlineDetail = "Completed " . $completedTime . ". Deadline was " . $deadlineText . ".";
                    } elseif ($inquirySla["state"] === "overdue") {
                        $deadlineTitle = "Overdue by " . formatSupportRequestDuration($inquirySla["remaining"]);
                        $deadlineDetail = "The 24-hour target was " . $deadlineText . ". The support team has been alerted.";
                    } elseif ($inquirySla["state"] === "due-soon") {
                        $deadlineTitle = "Due in " . formatSupportRequestDuration($inquirySla["remaining"]);
                        $deadlineDetail = "The 24-hour target is " . $deadlineText . ".";
                    } else {
                        $deadlineTitle = "Due in " . formatSupportRequestDuration($inquirySla["remaining"]);
                        $deadlineDetail = "The support team aims to complete this request within 24 hours, by " . $deadlineText . ".";
                    }
                ?>
                <section class="support-request-deadline is-<?= $escape($inquirySla["state"]) ?>"
                         data-request-deadline
                         data-deadline-unix="<?= (int)$inquirySla["deadline"] ?>"
                         data-deadline-state="<?= $escape($inquirySla["state"]) ?>"
                         data-completed-at="<?= $escape($inquirySla["completed_at"] ?? "") ?>">
                    <span class="support-request-deadline-icon" aria-hidden="true"><?= $inquirySla["state"] === "completed" ? "✓" : "24h" ?></span>
                    <span class="support-request-deadline-copy">
                        <strong data-deadline-title><?= $escape($deadlineTitle) ?></strong>
                        <span data-deadline-detail><?= $escape($deadlineDetail) ?></span>
                    </span>
                    <span class="support-request-deadline-target">24-hour target</span>
                </section>
                <section class="support-conversation" aria-label="Request conversation">
                    <div class="support-conversation-heading">
                        <div class="support-conversation-title">
                            <span class="support-conversation-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M5 5.75A2.75 2.75 0 0 1 7.75 3h8.5A2.75 2.75 0 0 1 19 5.75v6.5A2.75 2.75 0 0 1 16.25 15H11l-4.5 4v-4.1A2.75 2.75 0 0 1 5 12.25v-6.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                    <path d="M9 8h6M9 11h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span class="support-conversation-heading-copy">
                                <span class="support-conversation-kicker">REQUEST THREAD</span>
                                <h2>Conversation</h2>
                                <p>Every update, together in one clear timeline.</p>
                            </span>
                        </div>
                        <div class="support-conversation-heading-meta">
                            <span class="support-conversation-count" id="supportMessageCount">Loading updates</span>
                            <span class="conversation-live-indicator"><i></i> Live updates</span>
                        </div>
                    </div>
                    <div class="support-message-list" id="supportMessageList" aria-live="polite">
                        <p class="support-message-loading">Loading conversation...</p>
                    </div>
                    <form class="support-reply-form" id="supportReplyForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="inquiry_id" value="<?= (int)$inquiry["id"] ?>">
                        <label for="supportReply">Write a reply</label>
                        <textarea id="supportReply" name="message" rows="3" maxlength="5000" required placeholder="<?= $isAdmin ? "Write a helpful response to the customer..." : "Add more information or reply to support..." ?>"></textarea>
                        <div class="support-reply-footer">
                            <p id="supportReplyStatus" role="status" aria-live="polite"></p>
                            <button class="support-inquiry-primary" type="submit">Send reply <span aria-hidden="true">&rarr;</span></button>
                        </div>
                    </form>
                </section>
                <?php if ($isAdmin): ?>
                    <footer class="support-inquiry-footer">
                        <div class="support-inquiry-next-step">
                            <span class="support-inquiry-step-icon" aria-hidden="true">#</span>
                            <div>
                                <?php if (!empty($inquiry["ticket_id"])): ?>
                                    <strong>Ticket #<?= (int)$inquiry["ticket_id"] ?> is linked</strong>
                                    <span>Continue managing this request in the ticket.</span>
                                <?php else: ?>
                                    <strong>Need a tracked ticket?</strong>
                                    <span>Create one from this request with customer details prefilled.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($inquiry["ticket_id"])): ?>
                            <a class="support-inquiry-secondary" href="index.php?open_ticket=<?= (int)$inquiry["ticket_id"] ?>">Open ticket <span aria-hidden="true">&rarr;</span></a>
                        <?php else: ?>
                            <a class="support-inquiry-secondary" href="index.php?open_inquiry=<?= (int)$inquiry["id"] ?>">Create ticket <span aria-hidden="true">&rarr;</span></a>
                        <?php endif; ?>
                    </footer>
                <?php endif; ?>
            </section>
        <?php elseif ($inquiryId): ?>
            <a class="support-inquiry-back" href="support-inquiries.php">&larr; Back to requests</a>
            <section class="support-inquiry-card support-inquiry-state" role="<?= $loadError ? "alert" : "status" ?>">
                <h1><?= $loadError ? "Unable to load requests" : "Support request not found" ?></h1>
                <p><?= $loadError ? "Please try again later." : "This request may have been removed or you may not have access to it." ?></p>
            </section>
        <?php else: ?>
            <header class="support-inbox-heading">
                <div>
                    <span class="support-inquiry-eyebrow"><?= $isAdmin ? "SUPPORT CENTER / INBOX" : "SUPPORT CENTER / ACCOUNT" ?></span>
                    <h1><?= $isAdmin && $inboxView === "overdue" ? "Overdue Requests" : ($isAdmin ? "Customer Requests" : "My Support Requests") ?></h1>
                    <p><?= $isAdmin
                        ? ($inboxView === "overdue" ? "Requests still pending after their 24-hour completion target." : "Manage conversations, deadlines, and customer follow-up in one place.")
                        : "Open a request to continue your conversation with the support team." ?></p>
                </div>
                <div class="support-inbox-heading-actions">
                    <span class="support-inbox-count">
                        <strong><?= $inboxView === "overdue" ? $overdueRequestCount : count($requests) ?></strong>
                        <span><?= ($inboxView === "overdue" ? $overdueRequestCount : count($requests)) === 1 ? "request" : "requests" ?></span>
                    </span>
                    <?php if (!$isAdmin): ?>
                        <a class="support-inquiry-primary support-inbox-new-request" href="welcome.php#responseProcessHeading">
                            <span aria-hidden="true">+</span> Contact support
                        </a>
                    <?php endif; ?>
                </div>
            </header>
            <?php if ($isAdmin && !$loadError): ?>
                <nav class="support-inbox-views" aria-label="Request views">
                    <a href="support-inquiries.php"<?= $inboxView === "all" ? ' aria-current="page"' : "" ?>>All requests <span><?= count($allRequests ?? []) ?></span></a>
                    <a href="support-inquiries.php?view=overdue"<?= $inboxView === "overdue" ? ' aria-current="page"' : "" ?>>Overdue <span><?= $overdueRequestCount ?></span></a>
                </nav>
            <?php endif; ?>
            <?php if (!$loadError && $allRequests): ?>
                <section class="support-inbox-summary" aria-label="Request summary">
                    <article class="support-inbox-summary-card">
                        <span class="support-inbox-summary-icon is-blue" aria-hidden="true">▤</span>
                        <span><small>Total requests</small><strong><?= count($allRequests) ?></strong></span>
                    </article>
                    <article class="support-inbox-summary-card">
                        <span class="support-inbox-summary-icon is-teal" aria-hidden="true">◷</span>
                        <span><small>Active requests</small><strong><?= $activeRequestCount ?></strong></span>
                    </article>
                    <article class="support-inbox-summary-card">
                        <span class="support-inbox-summary-icon is-green" aria-hidden="true">✓</span>
                        <span><small>Completed</small><strong><?= $completedRequestCount ?></strong></span>
                    </article>
                    <article class="support-inbox-summary-card">
                        <span class="support-inbox-summary-icon is-amber" aria-hidden="true">!</span>
                        <span><small>Overdue</small><strong><?= $overdueRequestCount ?></strong></span>
                    </article>
                </section>
            <?php endif; ?>
            <?php if ($loadError): ?>
                <section class="support-inquiry-card support-inquiry-state" role="alert">
                    <h2>Unable to load requests</h2>
                    <p>Please refresh the page or try again later.</p>
                </section>
            <?php elseif (!$requests): ?>
                <section class="support-inquiry-card support-inbox-empty">
                    <span class="support-inbox-empty-icon" aria-hidden="true">✉</span>
                    <h2><?= $isAdmin && $inboxView === "overdue" ? "No overdue support requests" : ($isAdmin ? "No customer requests yet" : "No support requests yet") ?></h2>
                    <p><?= $isAdmin && $inboxView === "overdue" ? "All current requests are within their 24-hour target or have been completed." : ($isAdmin ? "New messages from customers will appear here." : "Contact support from the Welcome page to start a conversation.") ?></p>
                    <?php if (!$isAdmin): ?><a class="support-inquiry-secondary" href="welcome.php#responseProcessHeading">Contact support <span aria-hidden="true">&rarr;</span></a><?php endif; ?>
                </section>
            <?php else: ?>
                <form class="support-inbox-filters" id="supportInboxFilters" role="search">
                    <label class="support-inbox-search">
                        <span class="support-inbox-search-icon" aria-hidden="true">⌕</span>
                        <span class="support-inbox-visually-hidden">Search requests</span>
                        <input type="search" id="supportInboxSearch" placeholder="<?= $isAdmin ? "Search customer, subject, or request number" : "Search subject, message, or request number" ?>" autocomplete="off">
                    </label>
                    <label class="support-inbox-filter">
                        <span>Status</span>
                        <select id="supportInboxStatus">
                            <option value="">All statuses</option>
                            <option value="awaiting-ticket">Awaiting ticket</option>
                            <option value="open">Open</option>
                            <option value="in-progress">In progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </label>
                    <label class="support-inbox-filter">
                        <span>Priority</span>
                        <select id="supportInboxPriority">
                            <option value="">All priorities</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="not-set">Not set</option>
                        </select>
                    </label>
                    <label class="support-inbox-filter">
                        <span>Date submitted</span>
                        <select id="supportInboxDate">
                            <option value="">Any time</option>
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </label>
                </form>
                <p class="support-inbox-results" id="supportInboxResults" role="status" aria-live="polite"></p>
                <section class="support-inbox-list" aria-label="<?= $isAdmin ? "Customer requests" : "Your support requests" ?>">
                    <?php foreach ($requests as $request): ?>
                        <?php
                            $ticketStatus = trim((string)($request["ticket_status"] ?? ""));
                            $ticketStatusKey = $ticketStatus !== ""
                                ? strtolower((string)(preg_replace('/[^a-z0-9]+/i', '-', $ticketStatus) ?? "other"))
                                : "awaiting-ticket";
                            $ticketPriority = trim((string)($request["ticket_priority"] ?? ""));
                            $ticketPriorityKey = $ticketPriority !== "" ? strtolower($ticketPriority) : "not-set";
                            $searchText = implode(" ", [
                                (string)$request["id"],
                                (string)$request["name"],
                                (string)$request["subject"],
                                (string)$request["latest_message"],
                            ]);
                        ?>
                        <a class="support-inbox-item"
                           href="support-inquiries.php?id=<?= (int)$request["id"] ?>"
                           data-request-item
                           data-request-search="<?= $escape($searchText) ?>"
                           data-request-status="<?= $escape($ticketStatusKey) ?>"
                           data-request-priority="<?= $escape($ticketPriorityKey) ?>"
                           data-request-created="<?= (int)(strtotime((string)$request["created_at"]) ?: 0) ?>">
                            <span class="support-inbox-avatar" aria-hidden="true"><?= $escape(strtoupper(substr((string)$request["name"], 0, 1))) ?></span>
                            <span class="support-inbox-item-main">
                                <span class="support-inbox-item-top">
                                    <strong><?= $escape($isAdmin ? $request["name"] : $request["subject"]) ?></strong>
                                    <time datetime="<?= $escape($request["latest_activity"]) ?>"><span>Updated</span> <?= $escape(date("M j, Y · g:i A", strtotime((string)$request["latest_activity"]))) ?></time>
                                </span>
                                <span class="support-inbox-item-subject"><?= $isAdmin ? $escape($request["subject"]) : "Request #" . (int)$request["id"] ?></span>
                                <span class="support-inbox-preview"><?= $escape($request["latest_message"]) ?></span>
                            </span>
                            <span class="support-inbox-item-meta">
                                <span class="support-inbox-badges">
                                    <span class="support-inbox-status is-<?= $escape($ticketStatusKey) ?>"><?= $escape($ticketStatus !== "" ? $ticketStatus : "Awaiting ticket") ?></span>
                                    <?php if ($ticketPriority !== ""): ?>
                                        <span class="support-inbox-priority is-<?= $escape($ticketPriorityKey) ?>"><?= $escape($ticketPriority) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="support-inquiry-id">#<?= (int)$request["id"] ?></span>
                                <span class="support-inbox-ticket<?= !empty($request["ticket_id"]) ? " is-linked" : "" ?>">
                                    <i aria-hidden="true"></i><?= !empty($request["ticket_id"]) ? "Ticket linked" : "Open request" ?>
                                </span>
                                <?php
                                    $requestSla = $request["sla"];
                                    $requestDeadline = date("M j, Y · g:i A", $requestSla["deadline"]);
                                    if ($requestSla["state"] === "completed") {
                                        $requestDeadlineTitle = $requestSla["completion_timing"] === "on-time" ? "Completed on time" : "Completed late";
                                        $requestDeadlineDetail = $requestSla["completed_at"]
                                            ? "Completed " . date("M j, Y · g:i A", strtotime((string)$requestSla["completed_at"]))
                                            : "Completion time unavailable";
                                    } elseif ($requestSla["state"] === "overdue") {
                                        $requestDeadlineTitle = "Overdue by " . formatSupportRequestDuration($requestSla["remaining"]);
                                        $requestDeadlineDetail = "Deadline " . $requestDeadline;
                                    } else {
                                        $requestDeadlineTitle = "Due in " . formatSupportRequestDuration($requestSla["remaining"]);
                                        $requestDeadlineDetail = "Deadline " . $requestDeadline;
                                    }
                                ?>
                                <span class="support-inbox-deadline is-<?= $escape($requestSla["state"]) ?>"
                                      data-request-deadline
                                      data-deadline-unix="<?= (int)$requestSla["deadline"] ?>"
                                      data-deadline-state="<?= $escape($requestSla["state"]) ?>"
                                      data-completed-at="<?= $escape($requestSla["completed_at"] ?? "") ?>">
                                    <strong data-deadline-title><?= $escape($requestDeadlineTitle) ?></strong>
                                    <small data-deadline-detail><?= $escape($requestDeadlineDetail) ?></small>
                                </span>
                                <span class="support-inbox-open" aria-hidden="true">&rarr;</span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </section>
                <section class="support-inquiry-card support-inbox-empty support-inbox-filter-empty" id="supportInboxFilterEmpty" hidden>
                    <span class="support-inbox-empty-icon" aria-hidden="true">⌕</span>
                    <h2>No matching requests</h2>
                    <p>Try changing your search or clearing one or more filters.</p>
                    <button type="button" class="support-inquiry-secondary" id="supportInboxClearFilters">Clear filters</button>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php require "partials/footer.php"; ?>
    <?php if ($inquiry || $requests): ?><script src="assets/js/support-inquiries.js?v=4" defer></script><?php endif; ?>
</body>
</html>
