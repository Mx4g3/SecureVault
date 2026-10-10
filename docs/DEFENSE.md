# Three-minute live defense

Use fictional data and two test accounts on your own local app. Prepare both accounts and note a record ID before recording.

## 0:00–0:25 — Introduce the app

“SecureVault is a personal document record manager. Users register and sign in, then create, search, view, edit and delete their own document details. Records can include a private attachment. Uploads are validated and downloads verify record ownership.”

Show the dashboard, then open My documents.

## 0:25–1:35 — Explain four principles

“First, input validation. In `backend/includes/security.php`, the server checks required titles, length limits, positive IDs and allowlisted document types and confidentiality levels. HTML form rules help users, but they are not the final security check.”

“Second, least privilege and access control. In `backend/includes/functions.php`, `owned_document` selects by both the record ID and the signed-in user's ID. The update and delete controllers repeat this ownership condition. The app's database account has only the privileges it needs.”

“Third, fail securely. If identity, ownership or CSRF validation fails, the app stops the request. Unexpected exceptions produce a generic message and an internal reference. Record mutations and their audit entries use a transaction, so they either succeed together or roll back.”

“Fourth, defense in depth. The app combines password hashes, prepared SQL, regenerated sessions, CSRF tokens, output encoding, owner checks and browser security headers. A record ID by itself never grants access.”

## 1:35–2:35 — Demonstrate secure failure

1. Open Add document. Submit a blank title to show user feedback. Explain that the automated tests also bypass browser checks and receive server-side HTTP 422 responses.
2. Show the test report's SQL-injection sign-in result. The email field's browser validation prevents typing an invalid email through a normal submission, so the automated request deliberately sends `' OR '1'='1` directly. It receives the generic failed-login response and does not open a session.
3. Sign in as account B and open account A's `view_document.php?id=...` URL. Show “This record is unavailable.” Explain that edit and delete enforce the same ownership restriction.

Do not disable security controls to make a demonstration easier. If recording browser-only evidence of injection, use Developer Tools only on this local test app to remove the email field's client type constraint; the server should still reject it. The repeatable HTTP test is simpler and less error-prone.

## 2:35–3:00 — Answer the secure-failure question

“The app does not assume an unexpected request is safe. It checks input, session identity, form token and ownership before mutation. Invalid requests are denied. Technical failures produce a safe error instead of exposing SQL, passwords or internal paths. The result of uncertainty is denial, not accidental access.”

Close with the activity timeline showing record creation, update and sign-in events.

## Suggested evidence for your submission

Capture registration validation, the dashboard, an add form, owned records, relevant source lines, and an unauthorized-record response. Use `tests/results.json`, `tests/database-results.json`, and `tests/failure-results.json` as supporting test evidence. Add your own recording link and course/name information to the final course submission. No recording or submission is fabricated by this project.
