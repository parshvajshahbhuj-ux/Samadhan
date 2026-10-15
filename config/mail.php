<?php

$mailConfig = [
    "host" => getenv("SMTP_HOST"),
    "port" => getenv("SMTP_PORT"),
    "username" => getenv("SMTP_USERNAME"),
    "password" => getenv("SMTP_PASSWORD"),
    "encryption" => strtolower(trim((string)(getenv("SMTP_ENCRYPTION") ?: ""))),
    "from_email" => getenv("SMTP_FROM_EMAIL"),
    "from_name" => getenv("SMTP_FROM_NAME") ?: "Samadhan",
];

$missingSettings = [];
foreach ($mailConfig as $setting => $value) {
    if ($value === false || trim((string)$value) === "") {
        $missingSettings[] = "SMTP_" . strtoupper($setting);
    }
}

$smtpPortProvided = $mailConfig["port"] !== false && trim((string)$mailConfig["port"]) !== "";
$mailConfig["port"] = filter_var($mailConfig["port"], FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1, "max_range" => 65535],
]);

if ($mailConfig["port"] === false && $smtpPortProvided) {
    $missingSettings[] = "SMTP_PORT (must be between 1 and 65535)";
}

if (!in_array($mailConfig["encryption"], ["tls", "ssl"], true)) {
    if ($mailConfig["encryption"] !== "") {
        $missingSettings[] = "SMTP_ENCRYPTION (must be tls or ssl)";
    }
}

if (
    $mailConfig["from_email"] !== false &&
    trim((string)$mailConfig["from_email"]) !== "" &&
    !filter_var($mailConfig["from_email"], FILTER_VALIDATE_EMAIL)
) {
    $missingSettings[] = "SMTP_FROM_EMAIL (must be a valid email address)";
}

if ($missingSettings) {
    throw new RuntimeException(
        "SMTP configuration is incomplete: " . implode(", ", array_unique($missingSettings))
    );
}

return $mailConfig;