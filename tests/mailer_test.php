<?php
declare(strict_types=1);
require_once __DIR__ . '/../securevault/backend/includes/mailer.php';
$settings = require __DIR__ . '/../securevault/backend/config/mail.example.php';
$settings['enabled'] = true;
$checks = 0;
function verify(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++; echo 'PASS ' . $message . PHP_EOL;
}
$mail = build_email($settings, 'recipient@example.test', 'SecureVault test', 'Plain text body', '<p>HTML body</p>');
verify($mail->Mailer === 'smtp' && $mail->SMTPAuth, 'SMTP with authentication configured');
verify($mail->SMTPSecure === 'tls' && $mail->SMTPOptions['ssl']['verify_peer'], 'TLS and certificate checks enabled');
verify($mail->SMTPDebug === 0, 'SMTP debug output disabled');
verify($mail->preSend(), 'Multipart message generated without network access');
$mime = $mail->getSentMIMEMessage();
verify(str_contains($mime, 'multipart/alternative') && str_contains($mime, 'Plain text body') && str_contains($mime, 'HTML body'), 'HTML and plain-text parts present');
$plain = build_email($settings, 'recipient@example.test', 'Plain test', 'Text only');
verify($plain->preSend() && str_contains($plain->getSentMIMEMessage(), 'text/plain'), 'Plain-text message supported');
foreach (['disabled', 'recipient', 'subject', 'encryption', 'port', 'host', 'body'] as $case) {
    $config = $settings; $recipient = 'recipient@example.test'; $subject = 'Test'; $body = 'Body';
    if ($case === 'disabled') $config['enabled'] = false;
    if ($case === 'recipient') $recipient = "bad@example.test\r\nBcc: other@example.test";
    if ($case === 'subject') $subject = "Test\r\nBcc: other@example.test";
    if ($case === 'encryption') $config['encryption'] = '';
    if ($case === 'port') $config['port'] = 0;
    if ($case === 'host') $config['host'] = 'smtp.example.test;other.test';
    if ($case === 'body') $body = '';
    $rejected = false;
    try { build_email($config, $recipient, $subject, $body); } catch (InvalidArgumentException | RuntimeException $error) { $rejected = true; }
    verify($rejected, 'Rejected ' . $case);
}
echo "$checks mailer checks passed. No messages sent.\n";
