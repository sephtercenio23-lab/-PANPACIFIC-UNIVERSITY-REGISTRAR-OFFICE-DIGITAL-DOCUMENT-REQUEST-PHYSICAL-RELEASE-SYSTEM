# Panpacific Registrar Portal (PHP + MySQL) - InfinityFree deployment

1. InfinityFree panel > MySQL Databases > create a database. Note host, db name, user, password.
2. phpMyAdmin > select the database > Import > upload `schema.sql`.
3. Copy `config.sample.php` to `config.php` and fill in DB details, SITE_URL, MAIL_FROM.
4. Put `logo.png` and `background.jpg` into `assets/` (copy them from the original project).
5. Upload everything (File Manager or FTP) into `htdocs/`.
6. Open your site. Login as admin: admin@panpacificu.edu.ph (needs a working email or DEV_MODE=true).
7. Email: InfinityFree's mail() is unreliable. For real delivery, create a free Brevo or Resend account,
   set MAIL_DRIVER to 'brevo' or 'resend', and put the API key in MAIL_API_KEY (the sender must be verified there).
8. Before real use set DEV_MODE to false.
9. Admin > Programs: replace placeholder programs with the official list from your teacher.

Roles: Student (requester), Registrar (approver), Admin (approver + manage documents, programs, staff).
Test: student login -> request -> My Requests; staff login -> change status -> check notifications/audit logs.
