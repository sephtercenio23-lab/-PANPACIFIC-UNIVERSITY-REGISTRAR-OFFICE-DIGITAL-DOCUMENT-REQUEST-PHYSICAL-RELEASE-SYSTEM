<?php
// Copy this file to config.php and fill in your InfinityFree details.
define('DB_HOST', 'sqlXXX.infinityfree.com');
define('DB_NAME', 'if0_XXXXXXX_registrar');
define('DB_USER', 'if0_XXXXXXX');
define('DB_PASS', 'your-db-password');
define('SITE_URL', 'https://yoursite.infinityfreeapp.com');
define('UNIVERSITY', 'Panpacific University');
define('OFFICE', 'Registrar Office');

// Mail: 'mail' (PHP mail(), default), 'brevo' or 'resend' (HTTPS API via cURL)
define('MAIL_DRIVER', 'mail');
define('MAIL_FROM', 'registrar@yoursite.infinityfreeapp.com');
define('MAIL_FROM_NAME', 'Panpacific University Registrar');
define('MAIL_API_KEY', '');

// DEV_MODE shows the OTP on screen. TURN OFF in production.
define('DEV_MODE', true);
