# Verification results

## Final email-code check — October 10, 2026

All 13 challenge-service checks and 14 HTTP verification checks passed again. The final verification controller and view passed PHP syntax checks. Disposable QA accounts and their attachments were removed. The 36 attachment checks also passed after integration of the email-code flow on October 2. SMTP remains unconfigured; these checks simulated mail acceptance and do not establish real inbox delivery.

## Email codes — October 2, 2026

13 challenge-service checks and 14 HTTP verification checks passed, alongside the updated 52-check HTTP regression suite and 3 database checks. SMTP is unconfigured; service tests captured mail in memory, while HTTP fixtures simulated acceptance for reserved QA accounts. No real email was sent. Actual delivery is unverified. See VERIFICATION_EMAIL.md. Earlier demo sign-in evidence below predates mandatory email codes.
## File attachment verification — October 2, 2026
The upload release passed **36 attachment checks**, **52 existing HTTP checks**, and **3 database checks** (91 total). Attachment tests verify exact download bytes, owner isolation, multipart CSRF, extension/content mismatch rejection, empty/oversized uploads, ZIP-based Office formats, TXT/CSV/PNG, replacement/removal, rollback, the exact 10 MiB boundary, the 100 MiB quota, freed space after replacement, oversized request handling, and deletion. See `tests/upload-results.json`. Office fixtures test package validation, not compatibility with every Office document version. The quota rollback assertion was corrected to inspect the result count rather than the echoed search field; the complete rerun passed.
PHP syntax checks passed, and the new file picker was inspected in the running browser. Test accounts and attachments were removed after verification; existing user records were preserved. The outage test below was not rerun for this change.
## Folder reorganization verification
After separating `securevault/frontend`, `securevault/backend`, and `securevault/database`, all **52 HTTP checks and 3 database checks passed again**. All 41 PHP files passed syntax validation. The HTTP suite now explicitly checks that backend controllers, database schema, private configuration and frontend templates cannot be requested directly. Existing data and the localhost URL are preserved. The database-outage result below comes from the earlier complete verification.
Verified on October 1, 2026 using XAMPP PHP 8.2.12, MariaDB on an isolated local data directory, and the actual PHP web app.
- **52/52 HTTP integration checks passed.** See `tests/results.json`.
- **3/3 database checks passed.** See `tests/database-results.json`.
- **1/1 database-outage test passed.** See `tests/failure-results.json`.
- All application PHP files passed PHP syntax validation.
- Browser sign-in with the demo account opened the real dashboard and displayed its five database-backed sample records.
- Inspected dashboard at desktop and mobile breakpoints; inspected the add-document form and sign-in screen.
- Removed disposable QA accounts and stopped the temporary outage-test server. The main local app remains running.
Total: **56 passing automated checks**. These cover the cases listed in `TESTING.md`, not an independent security audit. Apache deployment, HTTPS and elapsed-time session expiration still require the manual checks described there.
The first test run identified an unwritable default XAMPP session path in this environment. The app now initializes its own protected session directory and denies requests if session initialization fails. A test assertion for the activity heading's capitalization was also corrected; the final complete rerun passed.
