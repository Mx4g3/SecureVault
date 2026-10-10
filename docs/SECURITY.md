# Security controls and source map

All paths below are relative to the project root. The complete runnable source is supplied in files, rather than duplicated into this document. `securevault/frontend/*.php` are thin public entry points that invoke the matching backend controller. Controllers prepare data and load templates from `securevault/frontend/views`. Only the frontend directory is served by the local launcher; its views directory is denied direct access.

| File | Responsibility and security control |
| --- | --- |
| `securevault/database/connection.php` | Central PDO connection; environment/private config; UTF-8; exceptions; native prepared statements; UTC database session. |
| `securevault/backend/config/local.example.php` | Configuration template with a placeholder, no working secret. |
| `securevault/backend/includes/bootstrap.php` | Security headers, safe uncaught-error response, HttpOnly/SameSite cookies, strict session mode, HTTPS Secure flag, private session storage, failure on session initialization error. |
| `securevault/backend/includes/security.php` | HTML escaping; scalar extraction; cryptographically random CSRF token and constant-time comparison; POST enforcement; strict positive integer IDs; field validation and allowlists. |
| `securevault/backend/includes/auth.php` | Reusable sign-in gate; reloads user identity from database; enforces idle and absolute session limits; does not trust request-supplied owner IDs. |
| `securevault/backend/includes/functions.php` | Prepared query helper; transaction rollback; safe audit metadata; owner-scoped record loading; uniform unavailable-record response. |
| `securevault/frontend/views/layout.php` | Shared semantic layout, safe rendering, escaped record titles and attributes, POST/CSRF sign-out, private navigation. |
| `securevault/frontend/views/document_form.php` | Reusable labeled create/edit form, token field, client hints. Server controllers remain authoritative. |
| `securevault/backend/controllers/index.php` | Routes based on server-verified authentication. |
| `securevault/backend/controllers/register.php` | Validates username/email/password, hashes password, handles duplicate email without SQL disclosure, transactional account/audit insertion. |
| `securevault/backend/controllers/login.php` | Prepared account lookup, password verification and rehash, dummy-hash path for unknown email, generic failures, DB-backed failure throttling, ID regeneration and token rotation. |
| `securevault/backend/controllers/logout.php` | POST + CSRF + auth; logs sign-out; destroys server session and expires session cookie. |
| `securevault/backend/controllers/dashboard.php` | All statistics, recent records and categories use the active user ID. |
| `securevault/backend/controllers/documents.php` | User-scoped prepared search with literal LIKE escaping; allowlisted filters; bounded search; pagination with integer-bound offset. SQL fragments come exclusively from fixed application strings. |
| `securevault/backend/controllers/add_document.php` | Server validation; session-supplied owner; prepared insert; transaction with audit. |
| `securevault/backend/controllers/view_document.php` | Validated ID + current user ownership query; escaped output; no mutation on GET. |
| `securevault/backend/controllers/edit_document.php` | Ownership checked on load and again under transaction lock; owner-scoped prepared update; CSRF and validation. |
| `securevault/backend/controllers/confirm_delete.php` | Ownership-protected confirmation page only; deletion requires a separate POST. |
| `securevault/backend/controllers/delete_document.php` | POST/CSRF/ID/ownership checks; owner-scoped delete and audit in one transaction; denies a failed row-count check. |
| `securevault/backend/controllers/activity.php` | Only the active user's latest 50 audit entries; escaped output; UTC timestamps. Unauthenticated failure events are not exposed in a user's timeline. |
| `securevault/database/schema.sql` | Unique email, owner foreign key, InnoDB transactions, enum constraints, indexes, separate throttling data. |
| `securevault/.htaccess` | Disables directory listing and blocks source SQL/documentation files. |
| `securevault/backend/.htaccess`, `securevault/database/.htaccess`, `securevault/frontend/views/.htaccess` | Deny web access to backend code, database files, and frontend templates under Apache. Backend config, includes and storage also retain their directory rules. |
| `securevault/frontend/assets/js/app.js` | Password visibility and description count only; no authentication or security decisions in JavaScript. |
| `securevault/frontend/assets/css/style.css`, `bootstrap.min.css`, `favicon.svg` | Local styling and graphics; no remote execution or tracking. |
| `tools/router.php` | Development-server private-path protection corresponding to Apache rules. |
| `tools/setup-local.php` | CLI-only provisioning; separate restricted app identity; creates private config. Never place tools in the web root. |
| `tools/seed-demo.php` | CLI-only, idempotent optional fictional dataset. Does not change an existing account. |
| `tools/cleanup-qa.php` | CLI-only test cleanup restricted to the isolated local database and reserved QA account pattern. |
| `Start-SecureVault.ps1` | Local loopback servers, isolated persistent database, private generated credentials. |
| `Stop-SecureVault.ps1` | Checks saved process identity/command line before stopping; graceful MariaDB shutdown. |
| `tests/*` | Repeatable HTTP and database verification for disposable local test accounts. |

## Request sequence

Browser → session initialization → authentication (private routes) → CSRF (writes) → server validation → ownership → bound SQL → transaction/audit → escaped response.

## Four principles for the lab

**Input validation:** A missing or oversized title, invalid enum, array-shaped value, or invalid ID cannot reach a record mutation. Browser limits are conveniences; server checks make the decision.

**Least privilege / access control:** Authentication identifies a user but does not grant access to every row. Record SQL uses both `id` and `user_id`. The database identity cannot create/drop tables, grant permissions, or edit audit rows.

**Fail securely:** Invalid CSRF returns 403; a foreign or nonexistent record returns 404; wrong HTTP method returns 405; validation returns 422; throttled login returns 429; unexpected errors return a generic 500. Unknown identity never becomes an authenticated session. Failed record transactions roll back.

**Defense in depth:** Server validation, prepared statements, owner filtering, password hashing, session controls, CSRF, HTML encoding, restrictive CSP, and database permissions overlap.

## Explicit limits

- Confidentiality labels do not implement encryption or public sharing.
- Password recovery and admin roles are outside this app's requested lab scope.
- Logging records selected events, not every page read. No document contents, passwords, tokens, or raw addresses are logged. Rate-limit identifiers are SHA-256 hashes of normalized email and connection address, not encryption.
- Failed-login throttling is appropriate for this local demonstration; it is not a distributed abuse-prevention system and shared-IP users share its limit.
- PHP source errors before bootstrap executes depend on server configuration; production PHP must disable `display_errors` globally too.
- Session files and database backups require host filesystem protection. Local Apache/PHP and database management remain administrator responsibilities.

## Private attachments

`backend/includes/uploads.php` validates upload errors, actual size, filename, extension, MIME/signature, image metadata and bounded Office package structure. Record writes and attachment replacement/removal share a transaction. `database/uploads.sql` is an additive migration with a cascading document foreign key. Binary data never enters the public filesystem. `backend/controllers/download.php` joins attachments to documents by both ID and owner, and forces attachment downloads with no-store and nosniff. A locked owner row serializes upload quota checks. See UPLOADS.md for the limits of file validation.

## Email verification

Registration and each login require a session-bound six-digit email code before authentication is established. See VERIFICATION_EMAIL.md for expiry, attempts, rate limits, delivery failure, migration and test scope. Legacy password-only sessions are rejected.
