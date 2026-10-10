# Email verification and sign-in codes

## How it works

- Registration saves a pending account and sends a six-digit code to the submitted email address. The account cannot access the workspace until the code is confirmed.
- Every login checks the password, then sends a fresh email code. A correct password alone never grants access.
- Verifying a code confirms the email and establishes a new authenticated session. Existing accounts are not grandfathered in; existing password-only sessions are invalidated.
- Codes expire after 10 minutes, are single-use, and allow at most five attempts. Requesting a new code invalidates older ones.
- Sending is limited to once per minute per account and five requests per hour for the account or connection. Pending sign-in sessions expire after 30 minutes.
- Failed delivery does not activate a challenge. Users see a safe error and can retry within the limits. Code values, message bodies, and SMTP secrets are not logged or returned in HTML. Only password hashes of codes are stored.

## Required setup before users can sign in

Configure a real SMTP provider in `securevault/backend/config/mail.local.php` using the template described in `EMAIL.md`. Set `enabled` to true only after the host, port, encryption, username, password/app password and sender address are ready. Do not place secrets in chat or frontend files.

**SMTP is not configured in the supplied workspace.** Registration and sign-in now stop at verification with a delivery error until SMTP works. This is intentional: there is no code bypass or automatic activation. The old demo address `demo@securevault.test` cannot receive external email; create an account with an inbox you control once SMTP is configured. Pending registrations may recover through normal password sign-in after configuration is fixed.

The local launcher imports `securevault/database/verification.sql` automatically. For an existing Apache/hosted installation, import that migration and grant:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON securevault_db.verified_emails TO 'securevault_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON securevault_db.email_challenges TO 'securevault_app'@'127.0.0.1';
```

Adapt the account name/host to the hosting provider. Fresh installs can import the complete schema. Old challenges are pruned after seven days when new challenges are issued. No passwords or document records are changed by the migration.

## Testing evidence and limits

`tests/verification_test.php` contains 13 checks of the actual challenge service with an in-memory mail transport: hashing, expiry, wrong-account denial, reuse, attempt limits, resend invalidation, delivery failure and hourly throttling. `tests/verification_http_test.py` contains 14 HTTP checks for registration/login gating, CSRF, failed delivery, pending-access denial and session rotation.

The HTTP regression suites use `tests/prepare_otp.php` to simulate SMTP acceptance for reserved local QA accounts, then submit the code through the real web verification form. That fixture is CLI-only, restricted to the isolated port-3308 database and reserved QA identities, and never belongs in a web document root. It does not disable production verification. Run these tests with outbound mail disabled. Cleanup removes QA accounts and cascades their verification data.

Actual delivery and spam-folder behavior have not been verified: they require a configured SMTP provider and an authorized test recipient. SMTP acceptance is not proof of inbox receipt. Email codes strengthen this app's sign-in flow, but are not phishing-resistant authentication or a replacement for securing the mailbox itself. Password reset and email-address change flows remain out of scope.
