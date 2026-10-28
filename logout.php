<?php
require __DIR__ . '/includes/auth.php';

$_SESSION = [];
// Also delete the session cookie in the browser
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/']);
session_destroy();
header('Location: index.php');
exit;
