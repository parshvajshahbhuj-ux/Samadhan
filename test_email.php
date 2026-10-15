<?php
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/email_helper.php";

$recipient = $argv[1] ?? "";
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php test_email.php recipient@example.com" . PHP_EOL);
    exit(1);
}

$body = "
    <h2>Samadhan</h2>
    <p>This is a test email from your Samadhan installation.</p>
    <p>SMTP email configuration is working successfully.</p>
";

if (!sendHelpDeskEmail($recipient, "Samadhan - Test Email", $body)) {
    fwrite(STDERR, "Email could not be sent. Check server logs for details." . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Test email sent successfully." . PHP_EOL);