# Samadhan deployment

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `fileinfo`, `mbstring`, and OpenSSL enabled.
- MySQL or MariaDB with InnoDB and `utf8mb4` support.
- Composer dependencies installed from `composer.lock`.
- The locked Dompdf dependency is required to attach resolution receipts as PDFs to completion emails.
- HTTPS for any public deployment.

## Secrets and server configuration

Do not commit database or SMTP credentials. Configure the following variables in the web server/PHP process environment, not in a public PHP file:

| Variable | Required | Example |
| --- | --- | --- |
| `APP_ENV` | Yes | `production` |
| `DB_HOST` | Yes | `127.0.0.1` |
| `DB_PORT` | Yes | `3306` |
| `DB_NAME` | Yes | `helpdesk_pro` |
| `DB_USER` | Yes | A dedicated application DB user, never `root` |
| `DB_PASSWORD` | Yes | A unique, non-empty secret |
| `DB_SSL_CA` | No | Path to the MySQL server CA certificate when TLS verification requires it |
| `SMTP_HOST` | Yes for email | Provider SMTP host |
| `SMTP_PORT` | Yes for email | `587` for STARTTLS or `465` for implicit TLS |
| `SMTP_USERNAME` | Yes for email | Provider-issued SMTP account |
| `SMTP_PASSWORD` | Yes for email | Provider-issued SMTP secret |
| `SMTP_ENCRYPTION` | Yes for email | `tls` or `ssl` |
| `SMTP_FROM_EMAIL` | Yes for email | Verified sender address |
| `SMTP_FROM_NAME` | No | `Samadhan` (default) |

If `SMTP_FROM_NAME` is already set in the server environment, update it to `Samadhan` so email notifications use the new brand.

The PHP app reads process environment variables using `getenv()`. On a direct local XAMPP request whose host and remote address are both loopback (`localhost`, `127.0.0.1`, or `::1`), the development-only defaults (`localhost`, `helpdesk_pro`, MySQL `root`, and an empty password) are enabled when no database variables are configured. Other hosts default to `production` and fail closed unless production database variables are configured. If local XAMPP is accessed over a LAN address or through a proxy, set `APP_ENV=development` only in protected local Apache configuration; never enable that mode on a client deployment. For a client, configure all database and SMTP variables in protected server/vhost configuration; for PHP-FPM, configure the pool/service environment and ensure its environment-clearing settings permit only the required variables. Do not put secrets in a web-accessible `.env` file.

The SMTP credential previously embedded in this project must be revoked/rotated with its provider before deployment. Remove any old copies from deployment packages, backups, or source-control history as appropriate.

## Deploying on Render

This application uses MySQL/MariaDB and PDO MySQL. Render's managed relational database is PostgreSQL, which is not compatible without a full database and query migration. The included Render Blueprint keeps the current MySQL implementation and expects a separately hosted MySQL/MariaDB database.

### Before deploying

1. Push the application to a Git repository Render can access. `.gitignore` excludes local attachment files, local environment files, Composer's generated `vendor/` directory, and local analytics seed scripts. Do not include database dumps or secrets in that repository.
2. Create or select a managed MySQL/MariaDB database that accepts connections from Render. Restrict database access to the outbound IP ranges shown for the Render service. Use the database provider's TLS option where available.
3. For a new installation, create an empty database and import `database/schema.sql` once. For an existing installation, back up and restore/migrate the existing data and schema to the external database. Never import the fresh schema over an existing database.
4. If the MySQL provider requires a CA certificate for TLS, add the certificate to the Render web service and cron service as a secret file named, for example, `mysql-ca.pem`. Set `DB_SSL_CA=/etc/secrets/mysql-ca.pem` on both services. The application verifies the server certificate when `DB_SSL_CA` is configured.

### Create the Render services

1. In the Render Dashboard, choose **New > Blueprint**, connect the repository, and sync the root `render.yaml`.
2. The Blueprint defines a PHP 8.3 Apache Docker web service, a persistent disk mounted at `uploads/ticket_attachments/`, and a cron service that runs `php process_overdue_support_requests.php` every five minutes.
3. After the Blueprint creates the `samadhan-production` environment group, add `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` to that group. Add the verified SMTP values (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION`, and `SMTP_FROM_EMAIL`) there as well. The group is shared by both services. The services will remain unhealthy until the required database values are present. Do not add secret values to `render.yaml` or the repository.
4. Keep `APP_ENV=production`. The container listens on Render's `PORT`; do not override the web service's Docker start command.
5. The 1 GB persistent attachment disk requires a paid web-service plan. The cron service is billed separately. The cron job only connects to the database and does not need the disk.
6. Wait for both services to deploy. The web service health check uses `/login.php`. Check the Render service logs if a build, health check, or database connection fails.

### Initialize and verify

For a new database, create the first Main Admin only after importing the schema. Use the Render web service's trusted shell if available, or a trusted local CLI that can access the database. `create_users.php` is CLI-only and reads the password from standard input; never put the password in a command argument or source file. Follow the secure account-creation example in [Create accounts from a trusted CLI](#create-accounts-from-a-trusted-cli).

For an existing installation, copy the backed-up contents of `uploads/ticket_attachments/` to the Render persistent disk at `/var/www/html/uploads/ticket_attachments/`. Preserve the stored filenames so existing attachment records continue to resolve.

Before opening the service to customers, verify sign-in, workspace registration/invitations, ticket creation and ownership, conversations, attachments and authorized downloads, notifications/email, analytics filters, CSV exports, and the overdue-inquiry cron run. Confirm uploaded attachments remain available after a redeploy. Test migrations and restores against a staging database first.

## Fresh installation

1. Create an empty database using the client's database administrator account.
2. Create a dedicated database user and grant only the permissions required by the app. The app uses normal `SELECT`, `INSERT`, `UPDATE`, `DELETE`, and `CREATE`/`ALTER` only if migrations are applied by that account; production runtime permissions may be narrowed after migrations.
3. Configure the process variables listed above.
4. Install PHP dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`.
5. Import `database/schema.sql` into the empty database. This is a fresh-install schema; it creates the default Samadhan workspace but does not insert demo accounts or sample tickets.
6. Create the first administrator from a trusted server terminal using the CLI-only account command below. CLI-created users are assigned to the default Samadhan workspace.
7. Ensure `uploads/ticket_attachments` is writable by PHP and is not directly web-accessible. The bundled `.htaccess` is Apache-specific; configure the equivalent deny rule for the actual web server or move uploaded files outside the document root.
8. Verify login, ticket create/edit, ownership access, attachments, email, notifications, analytics, CSV export, and restore from a backup in staging before going live.

## Create accounts from a trusted CLI

`create_users.php` refuses non-CLI requests and contains no preset credentials. Supply the account metadata as arguments and pipe a securely prompted password to standard input (the script refuses an interactive terminal, so the password is not echoed):

```text
$secure = Read-Host "Account password" -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) |
        php .\create_users.php --name="Client Admin" --email=admin@example.com --role=admin
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    $secure.Dispose()
}
```

The command requires at least 12 characters and stores only a password hash. Do not pass passwords as command-line arguments or place them in shell history. On other platforms, use an equivalent secure prompt and pipe the value through standard input. Run the command from a trusted terminal with database environment variables configured.

## Organization registration and invitations

Visitors can create a new organization workspace through `register.php`. The first verified account becomes that workspace's administrator. The account and workspace are created only after the visitor submits the one-time code sent to their email. Registration codes expire after 10 minutes, are limited to five verification attempts, and can be resent no more than once per minute for that registration session. Workspace members and customers join through email invitations from a workspace administrator; invitations expire after seven days. Invitation emails contain a unique 20-character workspace access code. Entering it on the sign-in page activates the invitation and signs the person in; the same code can be used for later passwordless sign-ins to that workspace. Resending a pending invitation replaces its code, and administrators can reset active members' codes from Workspace Access. Removing the membership revokes access. Treat access codes like passwords and keep them private. An email address has one account and can be a member of multiple workspaces; existing account holders keep their account and gain the invited workspace membership. Configure SMTP before enabling registration or invitations. Invitees must be able to reach the application's sign-in page.

SMTP connectivity can also be tested from the server terminal without exposing mail errors on the website:

```text
php test_email.php recipient@example.com
```

## Upgrading an existing Samadhan database

1. Take and verify a database backup and a copy of `uploads/ticket_attachments`.
2. Configure and verify the protected database and SMTP environment variables.
3. If conversations are not installed, apply `database/ticket_messages.sql`.
4. Apply `database/admin_notifications.sql`; its backfill creates unread notifications for existing customer messages and is safe to rerun.
5. Apply `database/support_inquiries.sql` to enable support requests, administrator inquiry notifications, and per-request conversations.
6. Apply `database/support_inquiry_sla.sql` to add 24-hour overdue tracking and administrator alerts.
7. Apply `database/user_notifications.sql` to enable user notifications for support replies and ticket status changes.
8. Apply `database/migrations/20261012_create_organizations.sql` exactly once. It creates the default Samadhan workspace and assigns all existing users, tickets, support requests, and activity logs to it. This migration is additive but is not repeatable; do not rerun it or import `database/schema.sql` into the existing database.
9. Apply `database/migrations/20261013_create_organization_memberships.sql` after the organizations migration. It creates workspace-specific memberships and backfills each existing user's current workspace and role. Back up first and apply it before deploying code that uses memberships.
10. Apply `database/migrations/20261014_add_invitation_access_codes.sql` after the memberships migration to add hashed, per-workspace sign-in codes to pending invitations and memberships. Back up first and apply it before deploying code that uses access codes.
11. Deploy the tenant-aware application code only after the migrations succeed; avoid running the new code against a pre-migration schema.
12. Schedule `process_overdue_support_requests.php` to run every 5 minutes using PHP CLI. Also run it after deployment to detect already-overdue requests. The CLI job processes requests across all workspaces; the authenticated admin notification endpoint processes only the current workspace.
13. On Windows/XAMPP, create a Windows Task Scheduler task with a 5-minute repeat interval, using the PHP executable as the program and the full script path as the argument, for example: Program `C:\xampp\php\php.exe`; Arguments `C:\xampp\htdocs\helpdesk_pro\process_overdue_support_requests.php`.
14. Test on a staging copy, including organization registration, invitation acceptance/revocation/expiry, invitation-code sign-in and workspace scope, workspace switching, administrator-wide access within a workspace, user-only ticket access, analytics filters, CSV export, attachments, notifications, and backup/restore before production rollout.
