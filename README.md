# Samadhan — HelpDesk Pro Support Ticket Management System

Samadhan is a multi-workspace help desk application for receiving, assigning, tracking, and resolving customer support tickets. It combines a PHP server-rendered application with MySQL/MariaDB, JavaScript (jQuery and Fetch/AJAX), and responsive CSS.

## Contents

- [What the application does](#what-the-application-does)
- [Roles and access](#roles-and-access)
- [Main pages](#main-pages)
- [Application structure](#application-structure)
- [Technology and dependencies](#technology-and-dependencies)
- [Local setup with XAMPP](#local-setup-with-xampp)
- [Database overview](#database-overview)
- [Security and operational notes](#security-and-operational-notes)
- [Development and validation](#development-and-validation)
- [Deployment and upgrade guide](#deployment-and-upgrade-guide)

## What the application does

- Creates and manages support tickets with a subject, category, priority, status, description, customer contact details, and timestamps.
- Supports ticket search, filtering, editing, deletion, assignment, status changes, and ticket activity history.
- Keeps customer and support-team conversations attached to tickets.
- Allows a customer to reopen their own recently resolved ticket, with a reason, during the configured seven-day reopening window.
- Supports customer support inquiries and threaded replies, with support-team notifications and 24-hour overdue tracking.
- Allows customers to submit ticket feedback and ratings.
- Provides ticket analytics, date-range filters, charts, summary reports, and filtered CSV exports.
- Supports ticket file attachments and PDF ticket receipts/resolution receipts.
- Sends email notifications for relevant account, ticket, invitation, and support events when SMTP is configured.
- Supports WhatsApp click-to-chat links when a ticket contains a customer phone number.
- Provides workspace invitations, multi-workspace membership, workspace switching, and workspace-scoped data.
- Includes a public landing page, onboarding content, account profile, and account password/sign-in settings.

## Roles and access

Roles are scoped to a workspace through `organization_memberships`.

| Role | Access |
| --- | --- |
| **Main Admin** (`admin`) | Manages tickets across the active workspace, assignments, customer requests, activity, analytics, and workspace membership/invitations. |
| **Support Member** (`support`) | Manages tickets and customer requests across the active workspace, including assignment and activity/analytics features. Workspace membership administration remains with the Main Admin. |
| **Customer** (`user`) | Views and follows their own tickets and support requests, replies to conversations, submits feedback, and may reopen an eligible recently resolved ticket they own. |

Access checks are implemented on the server as well as reflected in the interface. A user who belongs to multiple workspaces acts within the currently selected workspace; workspace switching is handled by `switch_workspace.php`.

## Main pages

| Page | Purpose |
| --- | --- |
| `home.php` | Public Samadhan landing page. Signed-in visitors are redirected to the workspace. |
| `register.php` | Starts organization registration and email verification for the first workspace administrator. |
| `login.php` | Signs in using an email/password or a workspace invitation access code. |
| `welcome.php` | Workspace welcome page, onboarding guidance, and customer support contact options. |
| `index.php` | Ticket dashboard and ticket management interface. |
| `analytics.php` | Ticket and support analytics, date filters, charts, and CSV report form. |
| `organization-members.php` | Main-Admin workspace member and invitation management. |
| `support-inquiries.php` | Support-team inquiry inbox and customer inquiry/conversation view. Customers see their own requests. |
| `activity-log.php` | Workspace activity history for support staff. |
| `account-profile.php` | Signed-in account details and workspace memberships. |
| `account-security.php` | Password and sign-in settings. |
| `support.php` | Support contact information and guidance. |
| `ticket-activity.php` | Ticket activity timeline view. |
| `ticket_receipt.php` | Ticket receipt view and print/download actions. |
| `accept_invitation.php` | Invitation acceptance flow. |

Shared page elements live in `partials/navigation.php` and `partials/footer.php`.

## Application structure

```text
.
├── api/                         Authenticated AJAX/JSON and download endpoints
├── assets/
│   ├── css/                     Shared and page-specific stylesheets
│   ├── images/                  Brand marks and receipt/email graphics
│   ├── js/                      Dashboard, reports, analytics, and UI behavior
│   └── docs/                    User guide PDF
├── config/                      Runtime configuration readers (SMTP)
├── docker/                      Apache, PHP, and container startup configuration
├── database/
│   ├── schema.sql               Complete schema for a fresh installation
│   ├── migrations/              Incremental schema changes for existing installs
│   └── *.sql                    Feature-table scripts for upgrades
├── partials/                    Shared navigation and footer
├── uploads/
│   └── ticket_attachments/      Private ticket attachment storage
├── auth.php                     Session, login, role, and workspace helpers
├── db.php                       PDO MySQL connection
├── email_helper.php             PHPMailer wrapper and email layout
├── activity_helper.php          Activity-log helpers
├── support_inquiry_sla.php      Support inquiry SLA processing helpers
├── user_notifications_helper.php Notification helpers
└── README.md
```

Important browser scripts:

- `assets/js/app.js` — ticket dashboard, ticket details, conversations, attachments, feedback, and customer reopening behavior.
- `assets/js/analytics.js` — analytics filters and chart rendering.
- `assets/js/reports.js` — report summary behavior.
- `assets/js/organization-members.js` — workspace invitation/member actions.
- `assets/js/support-inquiries.js` — support inquiry interactions.
- `assets/js/admin-notifications.js` and `assets/js/user-notifications.js` — role-appropriate notification interfaces.
- `assets/js/ticket-activity.js` — ticket timeline behavior.
- `assets/js/page-transitions.js` — shared page transition effects.

Important API endpoints:

| Endpoint | Purpose |
| --- | --- |
| `api/tickets.php` | Ticket listing and ticket create/update/delete/status operations. |
| `api/stats.php` | Dashboard statistics. |
| `api/analytics_stats.php` | Date-filtered analytics and chart data. |
| `api/report_stats.php` | Report summary data. |
| `api/export_tickets.php` | Filtered ticket CSV download. |
| `api/ticket_messages.php` | Ticket conversation messages. |
| `api/ticket_timeline.php` / `api/ticket_activity.php` | Ticket timeline/activity data. |
| `api/reopen_ticket.php` | Customer ticket reopening with eligibility and ownership enforcement. |
| `api/attachments.php` / `download_attachment.php` | Attachment upload and authorized download. |
| `api/ticket_feedback.php` | Customer ticket feedback and ratings. |
| `api/support_inquiries.php` | Support inquiry creation, replies, and management. |
| `api/admin_notifications.php` / `api/user_notifications.php` | Support-team and customer notifications. |
| `api/organization_invitations.php` | Workspace invitations and membership access-code actions. |
| `api/activity.php` | Activity-log data. |

## Technology and dependencies

- **Server:** PHP 8.1 or newer.
- **Database:** MySQL or MariaDB using InnoDB and `utf8mb4`.
- **PHP extensions:** `pdo_mysql`, `fileinfo`, `mbstring`, and OpenSSL.
- **Browser:** HTML, CSS, JavaScript, jQuery, and Fetch/AJAX.
- **Charts:** Chart.js 4, loaded from a CDN on the analytics page.
- **Email:** PHPMailer 7.
- **PDF:** Dompdf 3.1.

PHP library dependencies and their locked versions are described in `composer.json` and `composer.lock`. Some browser libraries are loaded from CDNs, so the browser needs internet access for those resources.

## Local setup with XAMPP

1. Install XAMPP with PHP 8.1+ and MySQL/MariaDB. Enable the PHP extensions listed above.
2. Place the project in the Apache document root, for example `C:\xampp\htdocs\helpdesk_pro`.
3. Start Apache and MySQL from the XAMPP Control Panel.
4. Create an empty database named `helpdesk_pro` (or configure a different database name).
5. For a **new installation only**, import `database/schema.sql` into that empty database. This file creates the current schema and the default Samadhan workspace; it does not create demo accounts or sample tickets.
6. Install PHP libraries from the project directory:

   ```powershell
   composer install
   ```

7. For a direct localhost XAMPP request, `db.php` uses development defaults if no database environment variables are set: host `localhost`, port `3306`, database `helpdesk_pro`, user `root`, and an empty password. Configure your actual MySQL credentials if they differ. These development defaults are not suitable for production.
8. Open `http://localhost/helpdesk_pro/` in a browser.
9. Create the first administrator from a trusted command prompt using `create_users.php`. It is CLI-only, requires a password of at least 12 characters via standard input, and supports `admin` or `user` as its role. Follow the secure PowerShell example in [DEPLOYMENT.md](DEPLOYMENT.md); do not pass a password as a command-line argument.
10. Configure SMTP environment variables before using registration, invitations, or email notifications. The required variable names are listed in [DEPLOYMENT.md](DEPLOYMENT.md).

Do not import `database/schema.sql` into a database that already contains application data. Use the existing-install upgrade instructions below.

## Deploying to Render

Render deployment files are included: `Dockerfile`, `.dockerignore`, `docker/`, and `render.yaml`. The Blueprint creates a Docker web service, a persistent attachment disk, and a five-minute overdue-inquiry cron job.

This application requires **MySQL/MariaDB**, while Render's managed relational database is PostgreSQL. Keep the existing MySQL code by using an external managed MySQL/MariaDB database; do not connect this application to Render Postgres without a full database-driver, schema, and query migration.

1. Push the project to a Git repository accessible to Render. `.gitignore` excludes local attachments, dependency files, and local environment files; do not commit database/SMTP credentials or backups.
2. Create an external MySQL/MariaDB database and configure it to accept secure connections from the Render web and cron services. Import `database/schema.sql` only for a new empty database; for existing application data, back up and migrate/restore it according to [DEPLOYMENT.md](DEPLOYMENT.md).
3. In Render, create a new Blueprint from the repository and sync `render.yaml`. The services will remain unhealthy until you add the external database values and SMTP settings to the `samadhan-production` environment group in Render. The group is shared by the web and cron services; use the same database values for both. Add the provider's TLS CA certificate as a secret file if required.
4. After the services deploy, initialize the database if needed, create the first Main Admin, and verify sign-in, ticket creation, attachment upload/download, email, analytics, CSV reports, and overdue inquiry notifications.

The Blueprint attaches a 1 GB persistent disk to the web service for private ticket attachments; Render persistent disks require a paid web-service plan. The cron job only connects to the database and does not need the disk. Render builds Docker images, but Docker is not installed in this development workspace, so the image must be validated by Render's build before it can run.

## Database overview

The current schema is defined in `database/schema.sql`. Its main tables are:

| Table | Stored data |
| --- | --- |
| `organizations` | Workspace names and slugs. |
| `users` | Account name, email, password hash, home organization, and role. |
| `organization_memberships` | Per-workspace user role and hashed invitation access code. |
| `organization_invitations` | Pending/accepted invitations, token/access-code hashes, and expiry. |
| `tickets` | Ticket content, requester details, status, priority, assignment, and timestamps. |
| `ticket_messages` | Ticket conversation messages. |
| `ticket_attachments` | Attachment metadata; file contents are stored under `uploads/ticket_attachments/`. |
| `activity_logs` | Ticket and workspace activity history. |
| `ticket_feedback` | Customer rating and optional feedback for a ticket. |
| `support_inquiries` | Customer-initiated support requests and completion information. |
| `support_inquiry_messages` | Support request conversation thread. |
| `admin_notifications`, `support_inquiry_notifications`, `support_inquiry_overdue_notifications` | Support-team ticket, inquiry, and overdue notifications. |
| `user_notifications` | Customer notifications about support replies and ticket changes. |

Ticket statuses are `Open`, `In Progress`, `Resolved`, and `Closed`. Ticket priorities are `Low`, `Medium`, `High`, and `Critical`. Analytics and reports use stored ticket values and timestamps; customer-level access is scoped to the signed-in user.

## Security and operational notes

- `auth.php` starts the session and provides login, role, and workspace-membership helpers. Protected pages and APIs must enforce authorization server-side.
- Database access uses PDO prepared statements. `db.php` disables emulated prepares and does not display connection errors to visitors.
- Passwords, invitation tokens, and workspace access codes are stored as hashes. Invitation access codes grant workspace-specific access and should be treated as secrets.
- CSV export includes mitigation for spreadsheet formula injection.
- Attachment downloads are authorized by the application. Keep `uploads/ticket_attachments/` non-public; the bundled `.htaccess` protects it under Apache. Configure an equivalent restriction for other web servers.
- Set up HTTPS for deployed environments. Production requires a dedicated non-root database account and non-empty secrets.
- Configure the periodic support-inquiry SLA task using `process_overdue_support_requests.php`; the recommended five-minute schedule is documented in [DEPLOYMENT.md](DEPLOYMENT.md).
- Do not put database or SMTP secrets in source code, a public `.env` file, or command history.

## Development and validation

- Keep database changes in `database/migrations/` or the relevant feature SQL script, and update `database/schema.sql` when the change belongs in a fresh install.
- Use the existing server-rendered PHP pages, shared partials, APIs, and page-specific CSS/JavaScript. Preserve existing role and workspace authorization boundaries.
- PHP syntax can be checked from the project directory with:

  ```powershell
  C:\xampp\php\php.exe -l index.php
  ```

- Check changed PHP files individually with the same command. The project does not define a Composer test or lint script; validate affected behavior manually in a staging/local database.
- Test email delivery with `php test_email.php recipient@example.com` from a trusted terminal after SMTP is configured.

## Deployment and upgrade guide

For production configuration, secrets, first-admin creation, invitation behavior, SLA scheduling, staging checks, and exact existing-database migration order, follow [DEPLOYMENT.md](DEPLOYMENT.md).

For an existing installation, back up both the database and uploaded attachments before applying any migration. Migrations are versioned and ordered; some are intentionally one-time and must not be rerun. Do not replace an existing database with the fresh-install schema.
