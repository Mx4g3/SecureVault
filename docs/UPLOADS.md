# Private file attachments

Each document may contain one optional attachment. Use **Add document** or **Edit document**, choose a file, then save. Open the record and choose **Download file** to retrieve it. Editing can replace an attachment or remove it permanently; deleting a record permanently removes its attachment too.

Supported extensions: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, CSV, PNG, JPG, JPEG and WebP. Maximum 10 MiB per file and 100 MiB per account (shown as MB in the app). Files must be nonempty. General archives, executable files, SVG, and macro-enabled Office extensions are not accepted. TXT/CSV must be UTF-8 text.

## Storage and access

Files are stored as private binary values in the `document_files` database table, separate from document metadata. No user-supplied filename becomes a filesystem path, and no upload is served as executable PHP. Downloads join attachments to records by both the record ID and signed-in owner. Public/Internal/Private labels never grant access to another user.

Attachments, metadata changes and audit events commit or roll back together. A cascading foreign key removes file bytes on record deletion. Quota checks lock the owner row to serialize simultaneous uploads. Attachment replacement accounts for the previous file's size. Database backups now include uploaded files and must be kept private.

## Setup

The local launcher imports the additive migration, grants the app access to the attachment table, enables ZIP support for its PHP process, and sets upload limits. Existing document records and accounts are preserved.

For Apache/XAMPP:

1. For a fresh database, import `securevault/database/schema.sql`. For an existing installation, import `securevault/database/uploads.sql`.
2. Grant the existing application user the required permissions:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON securevault_db.document_files TO 'securevault_app'@'127.0.0.1';
```

3. In the PHP configuration used by Apache, enable `fileinfo` and `zip`; set `upload_max_filesize=10M` and `post_max_size=12M`. Ensure the upload temporary directory is writable and outside the web root. Restart Apache.
4. Set MySQL/MariaDB `max_allowed_packet=32M` in its configuration and restart that database server. The isolated launcher sets this value at runtime for its own database.

## Validation and limits

Validation checks PHP's upload status, actual byte length, filename, allowed extension and content signatures/MIME where applicable. Images are checked with image metadata parsing. Office Open XML containers are inspected without extracting them; the main part and content type must match the extension. Excessive entry counts, expansion ratios, uncompressed size, encrypted entries, path traversal entries and common macro/embedded-object entries are rejected.

This is format screening, **not antivirus scanning** or a guarantee that downloaded documents are safe to open. Legacy DOC/PPT/XLS checks identify the shared OLE container; they do not deeply validate the application-specific streams or detect macros. PDF validation checks the header and detected MIME type, not every PDF object. Active content in documents may still exist. Files are forced to download with `application/octet-stream`, `attachment`, `nosniff` and no-store instead of rendering inline. The app does not encrypt files at rest.

The approach follows the layered principles in the [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html). There is no public upload endpoint and no public file-sharing feature.

## Verification

Run `python tests/upload_test.py` against the running local app. It exercises actual multipart uploads and downloads, including PDF, synthetic DOCX/PPTX/XLSX containers, PNG, TXT/CSV, wrong-owner requests, bad formats, CSRF, empty/oversized files, 10 MiB boundaries, replacement/removal, 100 MiB quota and rollback, and deletion. Its generated accounts/files are disposable. After testing, run `C:\xampp\php\php.exe tools/cleanup-qa.php` to remove the reserved QA accounts and their attachments.

Package fixtures verify container handling; they are not a compatibility test for every version of Microsoft Office. This test does not send user files anywhere outside the local application.
