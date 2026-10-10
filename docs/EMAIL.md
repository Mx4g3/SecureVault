# PHPMailer setup

PHPMailer **7.1.1** is installed in `securevault/backend/vendor/phpmailer` from the official release:
https://github.com/PHPMailer/PHPMailer/releases/tag/v7.1.1

The required SMTP classes are bundled directly, following PHPMailer's documented manual installation method. Composer is not required. Upstream source, version, README and LGPL-2.1 license are included; the library files are unmodified.

## Configure your SMTP provider

1. Copy `securevault/backend/config/mail.example.php` to `mail.local.php` in the same folder.
2. Enter your provider's SMTP host, port, username, password/app password and verified sender address.
3. Use `tls` for STARTTLS (usually port 587) or `ssl` for SMTPS (usually port 465).
4. Set `enabled` to `true` when ready to send.

Keep credentials in the private local file, never in frontend code. The local settings file is ignored by Git and excluded from `SecureVault.zip`. Do not paste your password into chat. The SMTP provider may require a verified sender or app password; providers requiring OAuth need additional setup that is not included here.

## Use from backend code

```php
require_once __DIR__ . '/../includes/mailer.php';
$accepted = send_email(
    'recipient@example.com',
    'SecureVault notification',
    'Your requested notification text.'
);
```

An optional fourth argument supplies trusted HTML, with the third argument retained as the plain-text alternative. Escape user-controlled text before inserting it into HTML. The helper returns false for disabled configuration, invalid input or SMTP failure and logs only the exception class. It never exposes SMTP credentials or server responses to users. A true result means SMTP acceptance, not confirmed inbox delivery.

Registration and password sign-in now send verification codes through this helper. See VERIFICATION_EMAIL.md. Working SMTP is required before accounts can enter the workspace. Password reset and public arbitrary-email endpoints have not been added.

## Verification

Run `C:\xampp\php\php.exe tests\mailer_test.php` from the project root. Tests build real MIME messages using PHPMailer and check input rejection, TLS, and debug settings without connecting to an SMTP server. Actual delivery requires your provider settings and a separately requested send test.
