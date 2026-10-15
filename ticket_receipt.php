<?php
require_once "auth.php";
require_once "db.php";
requireLogin();

$ticketId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$ticket = null;
$loadError = false;
$isSupportStaff = isSupportStaff();
$userId = (int)($_SESSION["user_id"] ?? 0);
$organizationId = getOrganizationId();

if ($ticketId && $ticketId > 0) {
    try {
        $sql = "
            SELECT
                ticket.id,
                ticket.user_id,
                ticket.name,
                ticket.email,
                ticket.subject,
                ticket.category,
                ticket.priority,
                ticket.status,
                ticket.description,
                ticket.created_at,
                ticket.updated_at,
                (
                    SELECT MAX(activity.created_at)
                    FROM activity_logs activity
                    WHERE activity.ticket_id = ticket.id
                      AND activity.organization_id = ticket.organization_id
                      AND activity.action = 'STATUS_CHANGE'
                      AND (
                          activity.description LIKE '% to Resolved.'
                          OR activity.description LIKE '% to Closed.'
                      )
                ) AS completed_at
            FROM tickets ticket
            WHERE ticket.id = :id
              AND ticket.organization_id = :organization_id
        ";
        $params = [
            ":id" => $ticketId,
            ":organization_id" => $organizationId,
        ];
        if (!$isSupportStaff) {
            $sql .= " AND ticket.user_id = :user_id";
            $params[":user_id"] = $userId;
        }
        $stmt = $conn->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Samadhan ticket receipt error: " . $e->getMessage());
        $loadError = true;
    }
}

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
};
$isCompleted = $ticket && in_array($ticket["status"], ["Resolved", "Closed"], true);
$statusClass = $ticket ? strtolower(str_replace(" ", "-", (string)$ticket["status"])) : "";
$receiptReference = $ticket
    ? "SDN-TKT-" . str_pad((string)(int)$ticket["id"], 6, "0", STR_PAD_LEFT)
    : "";
$activePage = "dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ticket ? "Ticket #" . (int)$ticket["id"] . " Receipt" : "Ticket Receipt" ?> | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">
    <link rel="stylesheet" href="assets/css/navigation.css?v=20">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=6">
    <link rel="stylesheet" href="assets/css/ticket-receipt.css?v=2">
    <script src="assets/js/page-transitions.js?v=5" defer></script>
</head>
<body class="app-page-body ticket-receipt-body">
    <?php require "partials/navigation.php"; ?>
    <main class="ticket-receipt-page page-transition-surface">
        <?php if ($ticket): ?>
            <nav class="ticket-receipt-actions" aria-label="Receipt actions">
                <a href="index.php" class="ticket-receipt-back">&larr; Back to tickets</a>
                <button type="button" class="ticket-receipt-print" onclick="window.print()">Print / Save as PDF</button>
            </nav>
            <article class="ticket-receipt-card">
                <header class="ticket-receipt-header">
                    <div class="ticket-receipt-brand">
                        <img src="assets/images/samadhan-logo.svg" alt="" width="46" height="46">
                        <span><strong>Samadhan</strong><small>Support &amp; Resolution Desk</small></span>
                    </div>
                    <span class="ticket-receipt-kind"><?= $isCompleted ? "RESOLUTION RECEIPT" : "SUPPORT TICKET RECEIPT" ?></span>
                </header>

                <section class="ticket-receipt-intro">
                    <div>
                        <p class="ticket-receipt-eyebrow">OFFICIAL SUPPORT RECORD</p>
                        <h1>Ticket #<?= (int)$ticket["id"] ?></h1>
                        <p class="ticket-receipt-subject"><?= $escape($ticket["subject"]) ?></p>
                    </div>
                    <span class="ticket-receipt-status status-<?= $escape($statusClass) ?>"><?= $escape($ticket["status"]) ?></span>
                </section>

                <dl class="ticket-receipt-details">
                    <div><dt>Customer</dt><dd><?= $escape($ticket["name"]) ?></dd></div>
                    <div><dt>Email</dt><dd><?= $escape($ticket["email"]) ?></dd></div>
                    <div><dt>Category</dt><dd><?= $escape($ticket["category"]) ?></dd></div>
                    <div><dt>Priority</dt><dd><?= $escape($ticket["priority"]) ?></dd></div>
                    <div><dt>Submitted</dt><dd><?= $escape(date("M j, Y · g:i A", strtotime((string)$ticket["created_at"]))) ?></dd></div>
                    <div><dt>Last updated</dt><dd><?= $escape(date("M j, Y · g:i A", strtotime((string)$ticket["updated_at"]))) ?></dd></div>
                    <?php if ($isCompleted): ?>
                        <div class="ticket-receipt-completion">
                            <dt>Resolved / closed</dt>
                            <dd><?= !empty($ticket["completed_at"])
                                ? $escape(date("M j, Y · g:i A", strtotime((string)$ticket["completed_at"])))
                                : "Completion date is not available" ?></dd>
                        </div>
                        <div>
                            <dt>Receipt reference</dt>
                            <dd><?= $escape($receiptReference) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <section class="ticket-receipt-description">
                    <h2>Request summary</h2>
                    <p><?= nl2br($escape($ticket["description"])) ?></p>
                </section>

                <?php if ($isCompleted): ?>
                    <section class="ticket-receipt-authorization" aria-label="Receipt authorization">
                        <img class="ticket-receipt-stamp" src="assets/images/samadhan-authorized-stamp.svg?v=7" alt="Official Samadhan authorization stamp">
                        <img class="ticket-receipt-completed-stamp" src="assets/images/ticket-completed-stamp.png?v=2" alt="Completed">
                        <div class="ticket-receipt-signoff">
                            <img src="assets/images/parshva-shah-signature.svg" alt="Signature of Parshva Shah">
                            <strong>Parshva Shah</strong>
                            <small>Authorized representative</small>
                        </div>
                    </section>
                <?php endif; ?>

                <footer class="ticket-receipt-footer">
                    <span>Keep this receipt for your records.</span>
                    <span>Generated <?= $escape(date("M j, Y · g:i A")) ?></span>
                </footer>
            </article>
        <?php else: ?>
            <section class="ticket-receipt-card ticket-receipt-state" role="<?= $loadError ? "alert" : "status" ?>">
                <h1><?= $loadError ? "Unable to load this receipt" : "Ticket receipt not found" ?></h1>
                <p><?= $loadError
                    ? "Please try again later."
                    : "This ticket may not exist or you may not have permission to view it." ?></p>
                <a href="index.php" class="ticket-receipt-back">&larr; Back to tickets</a>
            </section>
        <?php endif; ?>
    </main>
    <?php require "partials/footer.php"; ?>
</body>
</html>
