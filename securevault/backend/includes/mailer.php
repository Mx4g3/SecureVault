<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

function mail_settings(): array
{
    $path = __DIR__ . '/../config/mail.local.php';
    $settings = is_file($path) ? require $path : [];
    if (!is_array($settings)) throw new RuntimeException('Invalid mail configuration.');
    return array_replace(require __DIR__ . '/../config/mail.example.php', $settings);
}

/** Build a message without opening a network connection. HTML must be trusted template output. */
function build_email(array $settings, string $recipient, string $subject, string $text, ?string $html = null): PHPMailer
{
    if (($settings['enabled'] ?? false) !== true) throw new RuntimeException('Email sending is not configured.');
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($settings['from_email'] ?? '', FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid email address.');
    if ($subject === '' || strlen($subject) > 200 || preg_match('/[\r\n\x00]/', $subject)) throw new InvalidArgumentException('Invalid email subject.');
    if (trim($text) === '') throw new InvalidArgumentException('A plain-text message is required.');
    if (!in_array($settings['encryption'] ?? '', ['tls', 'ssl'], true)) throw new InvalidArgumentException('SMTP encryption is required.');
    $host = $settings['host'] ?? '';
    if (!is_string($host) || !preg_match('/^[a-zA-Z0-9.-]+$/D', $host)) throw new InvalidArgumentException('Invalid SMTP host.');
    $port = filter_var($settings['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false || empty($settings['username']) || empty($settings['password'])) throw new InvalidArgumentException('Incomplete SMTP configuration.');

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = $port;
    $mail->SMTPAuth = true;
    $mail->Username = $settings['username'];
    $mail->Password = $settings['password'];
    $mail->SMTPSecure = $settings['encryption'];
    $mail->SMTPDebug = 0;
    $mail->Timeout = 15;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    // Retain certificate verification; never accept self-signed certificates silently.
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
    $mail->setFrom($settings['from_email'], $settings['from_name'] ?? 'SecureVault');
    $mail->addAddress($recipient);
    $mail->Subject = $subject;
    $mail->isHTML($html !== null);
    $mail->Body = $html ?? $text;
    if ($html !== null) $mail->AltBody = $text;
    return $mail;
}

/** SMTP acceptance does not guarantee delivery to the recipient's inbox. */
function send_email(string $recipient, string $subject, string $text, ?string $html = null): bool
{
    try {
        return build_email(mail_settings(), $recipient, $subject, $text, $html)->send();
    } catch (Throwable $error) {
        // Do not disclose recipients, message content, credentials or SMTP responses.
        error_log('SecureVault email operation failed [' . get_class($error) . ']');
        return false;
    }
}
