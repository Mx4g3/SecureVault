# Three-minute SecureVault demonstration and defense

This is a spoken script and recording checklist. Use fictional accounts, files and document details. The app currently requires a real email code at registration and every login. Configure SMTP and test delivery before recording the signup/sign-in portions; see `docs/EMAIL.md`. Do not show a real password, email code, uploaded personal file, or SMTP setting in the video. If you cannot configure SMTP, state that email delivery is pending and demonstrate the remaining flows using a test session already prepared by your instructor; do not bypass verification in application code.

## Prepare before recording

1. Start SecureVault and confirm the dashboard loads. Follow the local setup steps in `README.md`.
2. Configure SMTP using `docs/EMAIL.md`. Register two fictional accounts whose mailboxes you can access, and verify each account. Create a sample document and attachment as Account A; note its document ID. Do not use the built-in demo account for sign-in because `demo@securevault.test` cannot receive a code.
3. Prepare two browser profiles, or use one browser and sign out between accounts. Have the document URL for Account A ready. Keep source files open at the code paths mentioned below.
4. For the invalid-input step, prepare a harmless unsupported file (for example `notes.exe`) and a blank document form. Do not submit real documents.

## Timed recording script

### 0:00–0:20 — Introduce the program

**Show:** SecureVault dashboard, then the documents page.

**Say:** “My project is SecureVault, a PHP and MySQL document manager. Each account can save document details, attach supported files, and download its own files. I’ll show input validation, account ownership, and what happens when a request is invalid.”

### 0:20–0:40 — Show verification and sign-in protection

**Show:** Sign out, sign in with one prepared account, and enter the emailed code. Keep the code itself out of the recording. If email delivery has not been configured, skip the attempted code and explicitly say that live email delivery still needs SMTP setup.

**Say:** “A correct password starts verification; it does not open the account by itself. Codes expire after ten minutes, allow five attempts, and can only be used once. The registration and sign-in flow is in `backend/controllers/register.php`, `backend/controllers/login.php`, and `backend/controllers/verify_email.php`; the code checks are in `backend/includes/verification.php`.”

### 0:40–1:10 — Demonstrate validation and safe output

**Show:** Open Add document, submit an empty title, and show the field error. Then try selecting or submitting the prepared unsupported file; show the friendly rejection. If time allows, show a saved record detail page.

**Say:** “The browser helps explain the form, but the server validates it again. `backend/includes/security.php` checks title length and allowlisted categories. `backend/includes/uploads.php` checks the filename, actual file size, detected content type, and file signature or Office package structure. `e()` escapes text before it becomes HTML, which prevents a document title from being treated as page code.”

### 1:10–1:45 — Demonstrate ownership checks

**Show:** Sign in as Account B and open Account A’s prepared document URL. Show the unavailable-record response. Then return to B’s own documents page.

**Say:** “The second account cannot view the first account’s record. In `backend/includes/functions.php`, `owned_document()` looks up both the record ID and the signed-in user ID. The download query in `backend/controllers/download.php` applies the same ownership rule to attachments. Knowing a record number does not grant access.”

### 1:45–2:15 — Explain the principles in the code

**Show:** Briefly point to the relevant functions in the editor or use a prepared code view.

**Say:** “Four secure coding principles are visible here. First, input validation: `validate_document()` and `validated_upload()` reject values and files outside the rules. Second, least privilege and access control: queries scope records to the signed-in account, and the database is configured for a limited application account. Third, fail securely: CSRF, identity, input and ownership failures stop the operation. Fourth, defense in depth: prepared SQL, password hashing, session renewal, CSRF tokens, output escaping and response headers protect different parts of the request.”

### 2:15–2:45 — Answer Question 2: secure failure

**Show:** The invalid-title or unsupported-file message and the unavailable-record response. If the instructor asks about unexpected server errors, show `backend/includes/bootstrap.php`.

**Say:** “When input is invalid, SecureVault returns a field error or rejects the request before saving it. A missing or incorrect form token is denied. If the record belongs to another account, the app returns the same unavailable response used for a nonexistent record. If an unexpected server error occurs, `bootstrap.php` hides technical details from the page, logs a short reference and error class, and tells the user the request was not approved. Database changes and their audit entries run in a transaction, so a failure rolls them back together.”

### 2:45–3:00 — Close

**Show:** Return to the dashboard or activity page.

**Say:** “The demo shows four principles in working code: validate input, limit access to the owner, reject unsafe requests, and use several protections together. This is a class project and has not had an independent security audit.”

## Direct answers for the instructor

### Question 1: Which principles did you implement, and where?

“I implemented at least four. **Input validation** is in `securevault/backend/includes/security.php` for document fields and IDs, and `securevault/backend/includes/uploads.php` for uploaded files. **Least privilege and access control** are in the owner-scoped queries in `securevault/backend/includes/functions.php` and `securevault/backend/controllers/download.php`; the database application user is granted only the required table operations. **Fail securely** is in `securevault/backend/includes/bootstrap.php` and the `abort_request()` helper in `securevault/backend/includes/functions.php`, which deny bad requests and hide internal errors. **Defense in depth** combines PDO prepared statements in `securevault/database/connection.php`, password hashing and email verification in the authentication controllers, CSRF checks and HTML escaping in `security.php`, owner checks, session controls and security headers. Each layer covers a different failure.”

### Question 2: How does the program fail securely?

“It validates values on the server even if a browser check is bypassed. Invalid fields or unsupported files are rejected with a clear message and are not saved. Missing CSRF tokens, invalid IDs, expired verification codes, too many attempts and unauthorised record access stop the request. Database operations use transactions so incomplete changes roll back. For unexpected exceptions, the app shows a generic error and reference, while the log records limited diagnostic details without displaying SQL, credentials, request bodies or private paths. When identity or ownership cannot be established, access is denied.”

## Attribution

“The application logic and original interface for this class project were developed for SecureVault with assistance from OpenAI Codex, based on the supplied project requirements. PHPMailer is bundled under its upstream LGPL-2.1 license; Bootstrap is included under its MIT license. Their licenses and attribution are in `securevault/backend/vendor/phpmailer/LICENSE` and `securevault/frontend/assets/css/BOOTSTRAP-LICENSE.txt`. I did not copy an unattributed tutorial solution.”

If you add code from another source, add a source comment beside that code naming the author and linking the original source when available. Follow that source's license. Do not claim original authorship for code you copied.

## After recording

Watch the video once to make sure no password, email code, private document, database credential or SMTP secret appears. Attach the recording link and your course/name information to the class submission. This guide does not create or submit the video for you.
