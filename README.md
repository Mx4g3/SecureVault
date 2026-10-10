# SecureVault

A complete local PHP 8 / MariaDB application for the Unit 5 secure-coding laboratory. It manages **document records and optional private file attachments**. Bootstrap 5.3.3 is included locally, so the interface does not depend on a CDN at runtime.

## Folder structure

```text
securevault/
  frontend/           Public pages, HTML templates, CSS, JavaScript, and icons
    assets/
    views/            PHP templates; direct web access is blocked
  backend/            Request processing, authentication, validation, and sessions
    controllers/
    includes/
    config/           Private local database settings
    storage/
  database/           SQL schema and PDO database connection
    schema.sql
    connection.php
```

The frontend PHP entry points hand requests to backend controllers, which load the appropriate frontend template. The local launcher serves only `securevault/frontend`; backend and database files remain outside its public document root. Existing page URLs and saved records are preserved.

## Open the app

The prepared local version runs at **http://127.0.0.1:8085**.

From this folder, run:

```powershell
.\Start-SecureVault.ps1
```

If PowerShell blocks a downloaded script, inspect it first, then use a process-only invocation:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\Start-SecureVault.ps1
```

The launcher uses XAMPP at `C:\xampp` by default. For another installation:

```powershell
.\Start-SecureVault.ps1 -XamppPath 'D:\xampp'
```

It starts an isolated database on **127.0.0.1:3308** and PHP on **127.0.0.1:8085**. The database stays in `.runtime/mysql`; it does not modify XAMPP's existing database files. Generated local database credentials are in ignored files. Keep `.runtime` private and do not submit or publish it.

Stop the local servers with `Stop-SecureVault.ps1`. It verifies process ownership and asks MariaDB to shut down cleanly. Start the app again with the launcher; records persist.

### Email verification is required

Registration and every login now require an email code. Configure SMTP using **docs/VERIFICATION_EMAIL.md** before users can sign in. SMTP is currently unconfigured, so verification will show a delivery error. Existing password-only sessions are no longer accepted.

### Demo account

The prepared workspace includes five fictional sample records:

- Email: **demo@securevault.test**
- Password: **VaultDemo!2026**

The demo address cannot receive real email codes and is no longer usable for interactive sign-in. Its fictional records remain preserved. Use an account with an inbox you control after SMTP is configured. On a fresh installation, create the optional demo with:

```powershell
C:\xampp\php\php.exe tools\seed-demo.php
```

## Features

- Registration with validated username, email, password, and password confirmation.
- Sign-in and POST-only sign-out, session regeneration, 30-minute idle expiry and 8-hour maximum session lifetime.
- Dashboard counts, recent records, and category navigation.
- Create, view, edit, confirm deletion, and delete owned records.
- Search titles and descriptions, filter by type/confidentiality, and paginate results.
- Account-scoped security activity, including successful sign-ins and record changes.
- Responsive layout, keyboard focus, skip navigation, form labels, empty states, and safe error pages.
- Public / Internal / Private are **organizational labels**. Every record stays account-private regardless of label.

## Standard XAMPP / Apache setup

1. Copy **only the `securevault` folder** into `C:\xampp\htdocs\securevault`. Keep `tools`, `tests`, `.runtime`, and project documentation outside the Apache document root.
2. Start Apache and MySQL in XAMPP Control Panel.
3. In phpMyAdmin, import `securevault/database/schema.sql`. It creates `securevault_db` with `users`, `documents`, `security_logs`, `login_attempts`, and `document_files` tables. It does not drop existing tables.
4. Create a dedicated database account in phpMyAdmin's SQL tab. Replace the example password below with your own:

```sql
CREATE USER 'securevault_app'@'127.0.0.1' IDENTIFIED BY 'YOUR_OWN_DATABASE_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON securevault_db.users TO 'securevault_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON securevault_db.documents TO 'securevault_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON securevault_db.document_files TO 'securevault_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON securevault_db.verified_emails TO 'securevault_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON securevault_db.email_challenges TO 'securevault_app'@'127.0.0.1';
GRANT SELECT, INSERT ON securevault_db.security_logs TO 'securevault_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON securevault_db.login_attempts TO 'securevault_app'@'127.0.0.1';
```

5. Copy `securevault/backend/config/local.example.php` to `securevault/backend/config/local.php`. Set port `3306`, your database password, and the other database details. **Replace the prepared local port-3308 configuration when moving to standard XAMPP.** Environment variables with the same names can override this file.
6. Ensure PHP can write `securevault/backend/storage`. Sessions use `backend/storage/sessions`, created automatically. Apache must honor the supplied `.htaccess` files; verify `/securevault/backend/config/local.php`, `/securevault/database/schema.sql`, and `/securevault/frontend/views/login.php` return 403. For a virtual host, use `securevault/frontend` as its document root.
7. Open **http://localhost/securevault/frontend/** and register an account.

Requirements: PHP 8.1+ with `pdo_mysql`, `mbstring`, `session`, `fileinfo`, `zip`, `dom`, and MySQL/MariaDB with InnoDB. Tested here with PHP 8.2.12 and the installed XAMPP MariaDB. No Composer, Node build, or external API is needed.

## File uploads

Add or edit a document to attach PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, TXT, CSV, PNG, JPG/JPEG or WebP files. One file per record, up to 10 MB; 100 MB per account. Record details offer a protected Download file button. Editing can replace or remove the file; deleting a record deletes its attachment. Files are private database binary values, not public URLs. See **docs/UPLOADS.md** for setup, validation and limitations.

## Email support

PHPMailer 7.1.1 is bundled in `securevault/backend/vendor/phpmailer`, with its upstream LGPL-2.1 license. The reusable helper is `securevault/backend/includes/mailer.php`. Follow **docs/EMAIL.md** to configure your SMTP provider using the private `mail.local.php` file. SMTP sending is disabled until configured. Registration and login require email codes, so users cannot complete sign-in until SMTP works. Run `tests/mailer_test.php` with PHP to verify message generation without sending emails.

## Security design

See **docs/SECURITY.md** for the file-by-file implementation map, and **docs/DEFENSE.md** for a three-minute presentation. **docs/TESTING.md** describes manual and automated tests and their limits.

Core controls:

1. Validate server-side: field lengths, types, allowlists, positive integer IDs, matching passwords.
2. Bind dynamic SQL values through PDO prepared statements; match both document ID and authenticated owner.
3. Hash passwords through PHP's password API; never store plain passwords.
4. Require CSRF tokens for every state-changing form, including login, registration, and logout.
5. Escape untrusted HTML output; set restrictive content security and anti-framing headers.
6. Deny failed requests and show a generic error reference. Internal diagnostics record only exception class and correlation reference, never SQL parameter data.
7. Commit account/record mutations together with their audit events. A logging failure rolls back those mutations.
8. Throttle unsuccessful login attempts by hashed account identity or connection address: 10 failures in a rolling 15 minutes. Forwarded headers are not trusted.

This is a lab implementation, not an independently audited production password/document service. Record contents are not encrypted at rest. Local HTTP does not encrypt traffic; both local servers bind to loopback. Any public deployment requires HTTPS, protected backups, operational monitoring, abuse controls, and a separate deployment review. HTTPS automatically enables Secure cookies and HSTS. Do not publish the demo account or local credentials.

## Test accounts for your defense

Register two new accounts through the app, for example `student_a@example.test` and `student_b@example.test`, with different usernames and passwords of your choice. Add one record as A, note its record ID, then sign in as B and try that URL. A 404 is expected for another user's record, exactly as for a nonexistent record. Do not use personal records or real passwords during attack demonstrations.

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Generic error on sign-in | Start the database; check your private local configuration and the internal PHP log. The public error intentionally hides database details. |
| Form expired | Reload the form. Confirm `storage` is writable and cookies are enabled. A sign-in or expired session rotates the token. |
| Too many attempts | Wait 15 minutes. The automated suite deliberately exercises this limit; its cleanup tool removes its own test identities. |
| Port 8085 or 3308 occupied | Stop the conflicting app or an earlier SecureVault session; do not run duplicate servers. |
| Access denied to MySQL | Check account host `127.0.0.1`, assigned privileges, password, and port. Avoid using root as the web application account. |
| PHP source appears as text | Apache/PHP is not configured; use the supplied launcher or enable PHP in XAMPP. |
| Private source file can be downloaded | Do not use that deployment. Enable Apache `.htaccess` rules or the supplied PHP router. Never serve this folder with a plain static-file server. |
| Missing database driver / mbstring | Enable `pdo_mysql` / `mbstring` in XAMPP's PHP configuration and restart PHP. |

## Attribution

Application source and original SVG/CSS visuals were created for this project with OpenAI Codex assistance using the supplied *SecureVault Full App Concept and Instructions* as the requirements reference. Interface styling uses Bootstrap 5.3.3, © The Bootstrap Authors, MIT license; its license is included in `securevault/frontend/assets/css/BOOTSTRAP-LICENSE.txt`. No external images, fonts, or runtime trackers are used.
