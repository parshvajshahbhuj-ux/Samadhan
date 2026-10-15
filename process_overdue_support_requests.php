<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/support_inquiry_sla.php";

try {
    $createdCount = createOverdueSupportInquiryNotifications($conn);
    fwrite(STDOUT, "Overdue support request check complete. New notifications: {$createdCount}." . PHP_EOL);
} catch (Throwable $e) {
    error_log("Samadhan overdue support request check failed: " . $e->getMessage());
    fwrite(STDERR, "Overdue support request check failed. See the server error log." . PHP_EOL);
    exit(1);
}
