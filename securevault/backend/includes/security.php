<?php
declare(strict_types=1);

const DOCUMENT_TYPES = ['Academic', 'Personal', 'Financial', 'Medical', 'Legal', 'Work', 'Other'];
const CONFIDENTIALITIES = ['Public', 'Internal', 'Private'];

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function input(string $key, array $source): string { return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : ''; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): void { echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $token = input('csrf', $_POST);
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        audit('csrf_rejected', 'A request failed its form-token check.');
        abort_request(403, 'This form has expired. Return to the page and try again.');
    }
}
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        abort_request(405, 'This action requires a submitted form.');
    }
}
function positive_id(mixed $value): int
{
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value) || (float)$value > 2147483647) {
        abort_request(400, 'The record address is invalid.');
    }
    return (int)$value;
}
function validate_document(array $source): array
{
    $data = [];
    foreach (['title', 'document_type', 'description', 'confidentiality'] as $key) $data[$key] = input($key, $source);
    $errors = [];
    if (mb_strlen($data['title']) < 1 || mb_strlen($data['title']) > 120) $errors['title'] = 'Enter a title between 1 and 120 characters.';
    if (!in_array($data['document_type'], DOCUMENT_TYPES, true)) $errors['document_type'] = 'Choose a valid document type.';
    if (mb_strlen($data['description']) > 1000) $errors['description'] = 'Keep the description to 1,000 characters or fewer.';
    if (!in_array($data['confidentiality'], CONFIDENTIALITIES, true)) $errors['confidentiality'] = 'Choose a valid confidentiality level.';
    return [$data, $errors];
}
