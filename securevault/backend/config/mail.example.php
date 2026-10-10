<?php
// Copy to mail.local.php. Keep real SMTP credentials out of source control.
return [
    'enabled' => false,
    'host' => 'smtp.example.com',
    'port' => 587,
    'encryption' => 'tls', // tls (STARTTLS, typically 587) or ssl (SMTPS, typically 465)
    'username' => 'your-smtp-username',
    'password' => 'your-smtp-password',
    'from_email' => 'noreply@example.com',
    'from_name' => 'SecureVault',
];
