<?php
// Security headers sent with every page.

// Don't tell visitors which PHP version runs the site
header_remove('X-Powered-By');

// Don't let other websites show our pages inside a frame (clickjacking)
header('X-Frame-Options: DENY');
// Don't let the browser guess file types (a CV can't run as a script)
header('X-Content-Type-Options: nosniff');
// When a link opens another site, don't send it our full address
header('Referrer-Policy: same-origin');
// Turn off browser features the site doesn't use
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// Where the page may load things from: only this site and Google Fonts
header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src https://fonts.gstatic.com; "
    . "img-src 'self' data:; "
    . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
