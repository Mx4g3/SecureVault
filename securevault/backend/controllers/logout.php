<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_post(); require_auth(); csrf_check();
audit('logout', 'The user signed out.');
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
redirect('login.php');
