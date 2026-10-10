<?php
// PHP development server only: equivalent to Apache's private-directory rules.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$decoded = rawurldecode(is_string($path) ? $path : '/');
if (preg_match('~(?:^|/)(?:backend|database|views|config|includes|storage|\.)~i', $decoded) || preg_match('~\.(?:sql|md|log|ini|json)$~i', $decoded)) {
    http_response_code(404); echo 'Not found.'; return true;
}
return false;
