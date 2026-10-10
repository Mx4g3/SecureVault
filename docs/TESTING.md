# Testing

## Attachment tests

Run `python tests/upload_test.py` while the local launcher is running. There are 36 checks, including real multipart uploads, download byte comparisons, wrong-owner denial, replacement/removal, quota boundaries and rollback. It temporarily creates about 100 MiB of disposable data to test the quota. Run `C:\xampp\php\php.exe tools\cleanup-qa.php` after the test to remove those test accounts and files. See `UPLOADS.md` for detailed coverage and setup.

## Automated checks

Start the app, then run from the project root:

```powershell
python tests\http_security_test.py
C:\xampp\php\php.exe tests\database_check.php
C:\xampp\php\php.exe tools\cleanup-qa.php
```

The first command exercises **52 HTTP checks**. It uses generated `qa_a_<timestamp>@example.test` / `qa_b_<timestamp>@example.test` accounts and writes `tests/results.json`. Run the database check before cleanup. That check verifies password hashes, safe audit content, and restricted database grants. Cleanup removes only generated QA accounts, their associated audit rows, and identified test login attempts in the isolated local database. An interrupted/failed test may leave disposable test accounts until cleanup is run.

The HTTP suite deliberately reaches the login limit, so run cleanup before signing in again from the same machine. It is restricted to localhost URLs. Tests must not be pointed at a production database.

## Coverage

| Area | Expected result |
| --- | --- |
| Register / duplicate email | Valid account succeeds; invalid input or duplicate fails without SQL disclosure. |
| Login / logout | Valid login works; ID changes after login; logout is POST-only and destroys the session. |
| Authentication | Anonymous dashboard request redirects to login. |
| Create / view / edit / delete | Owned record round trip succeeds; deletion needs confirmation and a token. |
| Search | Finds owned records and excludes the other account's matching records. |
| Validation | Blank/oversized titles, oversized descriptions, invalid enums and malformed IDs are rejected. |
| Authorization | Other account cannot view, edit, confirm deletion, submit updates or delete by ID. |
| CSRF | Missing or invalid tokens reject mutations and logout. |
| SQL injection | Sign-in cannot be bypassed; search treats injection syntax as literal data. |
| Stored XSS | Script-like title is encoded as text in the response. |
| Privacy | Private app directories and SQL source are not served by the local router. |
| Cookies and headers | HttpOnly, SameSite, no-store, anti-framing and CSP are present. |
| Throttling | Repeated failed sign-ins return 429. |
| Database checks | Password API verifies stored hashes; audit avoids payloads and secrets; app account cannot alter schema. |

## Unavailable-database test

`tests/failure_test.py` expects a second, temporary server on `127.0.0.1:8086` with `SV_DB_PORT` set to an unused port. It signs in through that server and requires a generic HTTP 500 without SQLSTATE, exception text, a local path or database account name. This does not stop or damage the main database. The temporary server was shut down after verification.

## Browser checks

Verify sign-in, dashboard, document detail and add form. Inspect a desktop layout and a narrow mobile layout. Confirm the navigation and add action remain usable; tables should scroll inside their container on small screens. Use a keyboard to navigate forms; inspect visible focus. Existing automated checks verify backend behavior even when browser form limits are bypassed.

## Manual checks to extend the lab

- Wait 30 minutes without activity and reopen a private page: expect sign-in.
- Inspect an HTTPS deployment: Secure cookie and HSTS should appear. This local HTTP demonstration cannot verify an HTTPS deployment.
- Add more than 10 records: confirm pagination and filters preserve their state.
- Try the same checks under Apache after copying to `htdocs`; the prepared runtime uses PHP's local server and router.

Passing checks is evidence for these tested cases, not a guarantee that all vulnerabilities are absent.
