<?php
/**
 ***********************************************************************
 PANPACIFIC UNIVERSITY
 REGISTRAR OFFICE
 DIGITAL DOCUMENT REQUEST & PHYSICAL RELEASE SYSTEM  +  SERVE EVALUATION
 CENTRALIZED MySQL / PHP BACKEND (InfinityFree-ready)
 ***********************************************************************
 EDIT THE DATABASE SETTINGS BELOW BEFORE UPLOADING TO INFINITYFREE.

 Where do I get these values?
   InfinityFree -> Client Area -> Manage -> MySQL Databases
   Example values you will see there:
       MySQL Host Name : sql105.infinityfree.com
       MySQL Database  : if0_38000000_ppu
       MySQL Username  : if0_38000000
       MySQL Password  : the password you typed when creating the database

 Optional: instead of editing this file you may create "config.local.php"
 (it is git-ignored) that returns an array which is merged over this one.
 ***********************************************************************
 */

return [

    /* =========================
       DATABASE (centralized)
       ========================= */
    'db' => [
        // 'mysql' for InfinityFree / XAMPP / any MySQL server.
        // 'sqlite' is only for offline local testing (see README).
        'driver'   => 'mysql',
        'host'     => 'sqlXXX.infinityfree.com',   // <-- CHANGE ME
        'port'     => 3306,
        'name'     => 'if0_XXXXXXXX_ppu',          // <-- CHANGE ME
        'user'     => 'if0_XXXXXXXX',              // <-- CHANGE ME
        'pass'     => 'YOUR_DATABASE_PASSWORD',    // <-- CHANGE ME
        'charset'  => 'utf8mb4',
        // Used only when driver = 'sqlite'
        'sqlite_path' => __DIR__ . '/storage/database.sqlite',
    ],

    /* =========================
       BRANDING (same values as the Apps Script CONFIG)
       ========================= */
    'university_name' => 'Panpacific University',
    'office_name'     => 'Registrar Office',
    'brand_blue'      => '#263f77',
    'brand_green'     => '#406f27',
    'timezone'        => 'Asia/Manila',

    /* =========================
       LOGIN / OTP
       ========================= */
    'otp_expiry_seconds'     => 600,   // 10 minutes
    'session_expiry_seconds' => 3600,  // 1 hour
    'otp_max_attempts'       => 5,
    'otp_rate_limit_seconds' => 60,
    'university_email_domain' => 'panpacificu.edu.ph',

    // Set to true ONLY on a test deployment: it returns the OTP code in the
    // API response so you can log in when e-mail is not configured yet.
    'dev_show_otp' => false,

    /* =========================
       OUTGOING E-MAIL
       InfinityFree does NOT allow PHP mail() and blocks ports other than 587
       for SMTP. Use an external SMTP account (Gmail app password, Brevo,
       SMTP2GO, MailerSend, Resend ...) with port 587 + TLS.
       mode: 'smtp' | 'mail' | 'log'
         'smtp' -> real delivery through the SMTP server below
         'mail' -> PHP mail() (works on paid hosting / XAMPP, not InfinityFree)
         'log'  -> nothing is sent; messages are stored in the notifications
                   table so you can still see what would have been sent
       ========================= */
    'mail' => [
        'mode'       => 'log',
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'encryption' => 'tls',        // 'tls' (587) or 'ssl' (465)
        'username'   => '',           // e.g. registrar.office@panpacificu.edu.ph
        'password'   => '',           // app password, NOT your normal password
        'from_email' => 'no-reply@panpacificu.edu.ph',
        'from_name'  => 'Panpacific University Registrar',
        'timeout'    => 6,            // seconds (InfinityFree kills PHP at ~10s)
    ],

    /* =========================
       SETUP / MAINTENANCE
       ========================= */
    // Key required by setup.php to (re)install tables. Change it to any
    // private string of your own. Leave '' to allow setup only while the
    // database has no tables yet (first install).
    'setup_key' => '',

    // Allow the setupSystem API action to run remotely (default: no).
    'allow_remote_setup' => false,

    // Show detailed errors in API responses. Keep false in production.
    'debug' => false,
];
