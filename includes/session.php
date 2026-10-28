<?php
// Starts the session with a safer cookie.
// httponly: JavaScript can't read the cookie, so an injected script can't steal it.
// samesite : the browser doesn't send the cookie with forms from other websites.
// secure   : on HTTPS the cookie is only sent over an encrypted connection.

if (session_status() === PHP_SESSION_NONE) {
    session_name('akilliik_session');
    session_set_cookie_params([
        'lifetime' => 0, // deleted when the browser is closed
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    ini_set('session.use_strict_mode', '1'); // ignore session ids the server didn't create
    session_start();
}
