<?php
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/db.php";

function failProvisioning(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(name|email|role)=(.*)$/s', $argument, $matches)) {
        failProvisioning("Usage: php create_users.php --name=\"Full Name\" --email=user@example.com --role=user|admin");
    }
    $options[$matches[1]] = trim($matches[2]);
}

$name = $options["name"] ?? "";
$email = $options["email"] ?? "";
$role = $options["role"] ?? "";

if ($name === "" || strlen($name) > 100) {
    failProvisioning("Name is required and must be 100 characters or fewer.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    failProvisioning("Provide a valid email address of 150 characters or fewer.");
}
if (!in_array($role, ["user", "admin"], true)) {
    failProvisioning("Role must be either user or admin.");
}

if (function_exists("stream_isatty") && stream_isatty(STDIN)) {
    failProvisioning("Password input must be piped through standard input; do not type or pass it as a command-line argument.");
}

$password = fgets(STDIN);
if ($password === false) {
    failProvisioning("Password input is required.");
}
$password = rtrim($password, "\r\n");
if (strlen($password) < 12) {
    failProvisioning("Password must contain at least 12 characters.");
}

try {
    $organizationStmt = $conn->prepare("
        SELECT id
        FROM organizations
        WHERE slug = :slug
        LIMIT 1
    ");
    $organizationStmt->execute([":slug" => "samadhan"]);
    $organizationId = $organizationStmt->fetchColumn();
    if ($organizationId === false) {
        failProvisioning("The default Samadhan workspace is not configured.");
    }

    $stmt = $conn->prepare("
        INSERT INTO users (organization_id, name, email, password, role)
        VALUES (:organization_id, :name, :email, :password, :role)
    ");
    $stmt->execute([
        ":organization_id" => (int)$organizationId,
        ":name" => $name,
        ":email" => $email,
        ":password" => password_hash($password, PASSWORD_DEFAULT),
        ":role" => $role,
    ]);
    $membership = $conn->prepare("
        INSERT INTO organization_memberships (organization_id, user_id, role)
        VALUES (:organization_id, :user_id, :role)
    ");
    $membership->execute([
        ":organization_id" => (int)$organizationId,
        ":user_id" => (int)$conn->lastInsertId(),
        ":role" => $role,
    ]);
    fwrite(STDOUT, "Account created successfully. Sign in and store the credentials securely." . PHP_EOL);
} catch (PDOException $e) {
    error_log("HelpDesk Pro CLI account provisioning failed: " . $e->getMessage());
    failProvisioning("Account could not be created. Check whether the email address is already registered.");
}
