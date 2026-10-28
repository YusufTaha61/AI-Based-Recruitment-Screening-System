<?php
// CSRF protection: every form sends a secret token that only this site knows.
// A fake form on another website can't know it, so its request is rejected.

require_once __DIR__ . '/session.php';

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Hidden input to put inside every <form method="post">
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

// Call at the start of every POST handler
function verifyCsrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) {
        http_response_code(419);
        exit('Oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.');
    }
}
