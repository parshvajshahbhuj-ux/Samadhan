<?php

$configuredEnvironment = getenv("APP_ENV");
$requestHost = strtolower(trim((string)($_SERVER["HTTP_HOST"] ?? "")));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$remoteAddress = (string)($_SERVER["REMOTE_ADDR"] ?? "");
$isLocalhostRequest =
    PHP_SAPI !== "cli" &&
    in_array($requestHost, ["localhost", "127.0.0.1", "::1", "[::1]"], true) &&
    in_array($remoteAddress, ["127.0.0.1", "::1"], true);
$hasDatabaseEnvironment = false;
foreach (["DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASSWORD"] as $setting) {
    $value = getenv($setting);
    if ($value !== false && trim($value) !== "") {
        $hasDatabaseEnvironment = true;
        break;
    }
}
$appEnvironment = strtolower(trim(
    $configuredEnvironment === false || trim($configuredEnvironment) === ""
        ? ($isLocalhostRequest ? "development" : "production")
        : $configuredEnvironment
));
$isDevelopment = $appEnvironment === "development"
    || ($isLocalhostRequest && !$hasDatabaseEnvironment);

if (!in_array($appEnvironment, ["development", "staging", "production"], true)) {
    error_log("HelpDesk Pro configuration error: APP_ENV must be development, staging, or production.");
    http_response_code(500);
    exit("Service temporarily unavailable.");
}

$databaseConfig = [
    "host" => getenv("DB_HOST"),
    "port" => getenv("DB_PORT"),
    "name" => getenv("DB_NAME"),
    "username" => getenv("DB_USER"),
    "password" => getenv("DB_PASSWORD"),
];

if ($isDevelopment) {
    $databaseConfig["host"] = $databaseConfig["host"] !== false ? $databaseConfig["host"] : "localhost";
    $databaseConfig["port"] = $databaseConfig["port"] !== false ? $databaseConfig["port"] : "3306";
    $databaseConfig["name"] = $databaseConfig["name"] !== false ? $databaseConfig["name"] : "helpdesk_pro";
    $databaseConfig["username"] = $databaseConfig["username"] !== false ? $databaseConfig["username"] : "root";
    $databaseConfig["password"] = $databaseConfig["password"] !== false ? $databaseConfig["password"] : "";
}

$missingSettings = [];
foreach ($databaseConfig as $setting => $value) {
    if ($value === false || ($setting !== "password" && trim((string)$value) === "")) {
        $settingName = $setting === "username" ? "DB_USER" : "DB_" . strtoupper($setting);
        $missingSettings[] = $settingName;
    }
}

$databasePort = filter_var($databaseConfig["port"], FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1, "max_range" => 65535],
]);

if ($databasePort === false && $databaseConfig["port"] !== false) {
    $missingSettings[] = "DB_PORT (must be between 1 and 65535)";
}

if (!$isDevelopment) {
    if ($databaseConfig["username"] === "root") {
        $missingSettings[] = "DB_USER (must be a dedicated non-root account)";
    }
    if ($databaseConfig["password"] === "") {
        $missingSettings[] = "DB_PASSWORD (must not be empty outside development)";
    }
}

if ($missingSettings) {
    error_log("HelpDesk Pro database configuration is incomplete: " . implode(", ", array_unique($missingSettings)));
    http_response_code(500);
    exit("Service temporarily unavailable.");
}

try {
    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
        $databaseConfig["host"],
        $databasePort,
        $databaseConfig["name"]
    );
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $databaseSslCa = getenv("DB_SSL_CA");
    if ($databaseSslCa !== false && trim($databaseSslCa) !== "") {
        if (!is_file($databaseSslCa) || !is_readable($databaseSslCa)) {
            throw new RuntimeException("The configured database TLS CA file is unavailable.");
        }
        $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $databaseSslCa;
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $conn = new PDO($dsn, $databaseConfig["username"], $databaseConfig["password"], $pdoOptions);
} catch (PDOException $e) {
    error_log("HelpDesk Pro database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Service temporarily unavailable.");
} catch (RuntimeException $e) {
    error_log("HelpDesk Pro database TLS configuration failed: " . $e->getMessage());
    http_response_code(500);
    exit("Service temporarily unavailable.");
}
?>