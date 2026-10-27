# Panpacific University — Registrar Office
## Digital Document Request & Physical Release System + SERVE Evaluation
### PHP + MySQL version (ready to deploy on InfinityFree)

This project is the **PHP/MySQL port of the Google Apps Script version**. The
registrar data no longer lives in a Google Sheet: every student, request,
document type, audit entry, notification and evaluation is stored in **one
centralized MySQL database** on your hosting account.

**The frontend is unchanged.** `index.html` keeps the same markup, the same CSS
and the same JavaScript functions (`start()`, `loadStaff()`, `finish()`,
`submitEvaluation`, ...). The only addition is one line that loads
`assets/serve-bootstrap.js`, which provides `google.script.run` on top of PHP:

```html
<script src="assets/serve-bootstrap.js"></script>
```

Because of that bridge the form still calls

```js
google.script.run.withSuccessHandler(fn).withFailureHandler(fn).submitEvaluation(payload);
```

and the bridge forwards it to `api.php` → PHP → MySQL. Nothing in the form's
logic, layout or styling had to change.

---

## 1. What is inside

```
/
├── index.html                 SERVE evaluation form (unchanged design + functions)
├── api.php                    the single JSON endpoint (replaces google.script.run)
├── setup.php                  installer + health checks  ← delete after installing
├── config.php                 database + branding + mail settings  ← EDIT THIS
├── .htaccess                  blocks config/SQL/docs, sets index.html as default
├── assets/
│   └── serve-bootstrap.js     google.script.run → fetch(api.php) bridge
├── inc/
│   ├── bootstrap.php          loads everything, turns warnings into exceptions
│   ├── helpers.php            config, formatting, ids, JSON responses
│   ├── database.php           one PDO connection + query helpers (prepared statements)
│   ├── mailer.php             SMTP client (STARTTLS 587) / mail() / log mode
│   ├── schema.php             database structure + default data (single source of truth)
│   ├── router.php             whitelisted actions → backend functions
│   └── actions/
│       ├── documents.php      getPublicConfig, getDocumentTypes
│       ├── otp.php            requestOTP, verifyOTP, sessions
│       ├── requests.php       submitRequest, getStudentRequests, audit, notifications
│       ├── evaluations.php    getStaff, submitEvaluation, getEvaluationSummary
│       └── system.php         setupSystem, health
├── database/
│   └── schema.mysql.sql       same tables for manual phpMyAdmin import
├── docs/
│   └── API.md                 endpoint reference with curl examples
└── storage/                   only used by the optional SQLite test mode
```

### Apps Script → PHP mapping

| Apps Script (`Code.gs`) | This project | Frontend caller |
|---|---|---|
| `doGet()` (serve `index`) | `index.html` + `api.php` | — |
| `setupSystem()` | `setup.php` / `act_setupSystem()` | — |
| `getPublicConfig()` | `inc/actions/documents.php` | future portal page |
| `getDocumentTypes()` | `inc/actions/documents.php` | future portal page |
| `requestOTP(email)` | `inc/actions/otp.php` | future portal page |
| `verifyOTP(token, code, …)` | `inc/actions/otp.php` | future portal page |
| `submitRequest(session, payload)` | `inc/actions/requests.php` | future portal page |
| `getStudentRequests(session)` | `inc/actions/requests.php` | future portal page |
| `getStaff()` | `inc/actions/evaluations.php` | **SERVE form** |
| `submitEvaluation(payload)` | `inc/actions/evaluations.php` | **SERVE form** |
| Sheets `Students/Requests/DocumentTypes/Users/AuditLogs/Notifications` | MySQL tables of the same name | — |
| `CacheService` (OTP + sessions + rate limit) | `otp_tokens`, `sessions`, `rate_limits` tables | — |
| `MailApp.sendEmail()` | `inc/mailer.php` (SMTP) | — |

Function names, argument order, return values and error messages are the same
as the Apps Script version, so the API can be used by the document request
pages exactly like before.

---

## 2. Deploy to InfinityFree (step by step)

### Step 1 — Create the hosting account and the database

1. Sign up at <https://infinityfree.com>, then **Create Account** → choose a free
   subdomain (e.g. `ppu-registrar.infinityfreeapp.com`).
2. In the client area press **Manage** next to the account → **MySQL Databases**.
3. Create a database, e.g. name `ppu`. InfinityFree will show something like:

   | Field | Example |
   |---|---|
   | MySQL Host Name | `sql105.infinityfree.com` |
   | MySQL Database | `if0_38000000_ppu` |
   | MySQL Username | `if0_38000000` |
   | MySQL Password | the password you typed |

   Write these four values down, you need them in Step 3.

### Step 2 — Upload the files

1. Client area → **Manage** → **File Manager** (or connect with FileZilla using
   the FTP details of the account).
2. Open the **`htdocs`** folder and delete the default `index2.html` /
   placeholder files.
3. Upload **everything in this repository** into `htdocs`, so that the folder
   looks like:

   ```
   htdocs/index.html
   htdocs/api.php
   htdocs/setup.php
   htdocs/config.php
   htdocs/assets/...
   htdocs/inc/...
   htdocs/database/...
   ```

   Keep the folder structure — the `inc/`, `assets/`, `database/` and `storage/`
   folders must stay where they are.

### Step 3 — Edit `config.php`

Open `htdocs/config.php` in the File Manager editor and fill in the four
database values from Step 1:

```php
'db' => [
    'driver'   => 'mysql',
    'host'     => 'sql105.infinityfree.com',
    'port'     => 3306,
    'name'     => 'if0_38000000_ppu',
    'user'     => 'if0_38000000',
    'pass'     => 'your-database-password',
    'charset'  => 'utf8mb4',
    ...
],
```

Optional but recommended:

```php
'setup_key' => 'my-private-setup-key',   // protects setup.php
'debug'     => false,                    // never true on the live site
```

> Prefer to keep credentials out of Git? Create `config.local.php` next to
> `config.php` (it is git-ignored) returning only the values you want to
> override; it is merged automatically.

### Step 4 — Run the installer

1. Open `https://your-subdomain.infinityfreeapp.com/setup.php`.
2. Every row must be green (`PHP version`, `PDO`, `pdo_mysql`, `OpenSSL`).
3. Press **Run installation** — it creates the 11 tables and inserts the
   default document catalog, the administrator account and the staff list.
4. **Delete `setup.php`** afterwards (important).

Prefer phpMyAdmin? You can import `database/schema.mysql.sql` instead of
pressing the install button; the result is identical.

### Step 5 — Open the form

Visit `https://your-subdomain.infinityfreeapp.com/` — the SERVE evaluation form
appears exactly as before. Select a student type, an e-mail, a staff member,
answer the ratings and submit. The reference (`PU-SERVE-2026-XXXXXX`) is stored
in the `evaluations` table.

### Step 6 — Free SSL

Control panel → **SSL Certificates** → request the free certificate, then
enable **HTTPS** for the domain. Students should not submit personal data over
plain HTTP.

---

## 3. Database (centralized)

11 tables, all created by `setup.php` or `database/schema.mysql.sql`:

| Table | Purpose |
|---|---|
| `students` | Registrar student records (unique e-mail, student number, type, campus) |
| `document_types` | Catalog: code, name, fee, typical days, requirements, active |
| `requests` | Every document request (status, stage, release method, representative, due date) |
| `users` | Registrar staff/admin accounts (used by OTP login) |
| `audit_logs` | Who did what and when (requests, evaluations, setup) |
| `notifications` | Every e-mail the system tried to send, with its delivery status |
| `otp_tokens` | 6-digit login codes (bcrypt-hashed, expire in 10 min) |
| `sessions` | Login sessions (expire in 1 hour) |
| `rate_limits` | 60-second OTP throttle |
| `staff` | Staff names shown in the evaluation dropdown |
| `evaluations` | SERVE evaluation answers, average rating, comments, reference |

Default data inserted on install:

* 11 document types (TOR, COE, COG, COGRA, GM, DIP, HD, CTC, COC, COA, OTH) with
  the same fees/days/requirements as `DEFAULT_DOCUMENTS` in `Code.gs`.
* Administrator user `admin@panpacificu.edu.ph`.
* **Three placeholder staff rows** — replace them with the real staff names
  (see section 6).

---

## 4. E-mail (read this before testing)

InfinityFree **blocks PHP `mail()`** and only allows outbound SMTP on **port
587 with STARTTLS**. Therefore `config.php` ships with:

```php
'mail' => ['mode' => 'log', ...],
```

* `log` — nothing is sent; each message is written to the `notifications` table.
  The form still works (the success screen appears), but students receive no
  confirmation e-mail.
* `smtp` — real delivery. Use a mailbox that allows SMTP, e.g. Gmail with an
  **app password**, Brevo, SMTP2GO, MailerSend or Resend:

```php
'mail' => [
    'mode'       => 'smtp',
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'registrar.office@panpacificu.edu.ph',
    'password'   => 'the-16-character-app-password',
    'from_email' => 'registrar.office@panpacificu.edu.ph',
    'from_name'  => 'Panpacific University Registrar',
    'timeout'    => 8,
],
```

`setup.php` has a **Send test message** button that reports whether the SMTP
account works. If e-mail delivery fails for a document request confirmation the
request is still saved (the notification row is marked `Failed`), but a failed
OTP e-mail makes the login fail with a clear message.

---

## 5. Security notes

* **SQL injection**: every query uses PDO prepared statements with bound
  parameters (`inc/database.php`).
* **Authentication**: OTP codes are stored as bcrypt hashes, expire after 10
  minutes, allow 5 attempts, and are throttled to one request per minute per
  address. Sessions live in the database and expire after 1 hour.
* **Error leakage**: `debug => false` returns a generic message and logs the
  details to the hosting error log; PHP warnings never break the JSON response.
* **Direct file access**: `.htaccess` blocks `config.php`, `*.sql`, `*.md` and
  everything inside `inc/`, `database/` and `storage/`.
* **XSS**: the form escapes output with its own `safe()` helper; the API only
  returns JSON (`Content-Type: application/json`, `X-Content-Type-Options:
  nosniff`).
* **setup.php**: protected by `setup_key` (and by `install.lock` after the first
  run). Delete it on the live site.

---

## 6. Change the staff list (important)

The SERVE form lists exactly the **active rows of the `staff` table**. Replace
the placeholders with the real staff before going live — phpMyAdmin → `staff`:

```sql
UPDATE staff SET Name = 'Juan Dela Cruz', Position = 'Registrar Staff' WHERE StaffID = 'STAFF-001';
UPDATE staff SET Name = 'Maria Santos',  Position = 'Registrar Staff' WHERE StaffID = 'STAFF-002';

-- add someone new
INSERT INTO staff (StaffID, Name, Position, Active, SortOrder)
VALUES ('STAFF-004', 'Ana Reyes', 'Registrar Staff', 1, 40);

-- hide someone without deleting their evaluations
UPDATE staff SET Active = 0 WHERE StaffID = 'STAFF-003';
```

Renaming a staff member does not change past evaluations (the name is stored
with every evaluation).

---

## 7. The API (for the document-request pages)

`api.php` accepts `POST` with a JSON body:

```json
{ "action": "getStaff", "args": [] }
```

and answers:

```json
{ "ok": true, "result": [ { "id": "STAFF-001", "name": "Registrar Staff 1", "position": "Registrar Office Staff" } ] }
```

Errors answer `{"ok": false, "error": {"name": "Error", "message": "..."}}`.
See **`docs/API.md`** for every action, argument and curl example
(`getPublicConfig`, `requestOTP`, `verifyOTP`, `submitRequest`,
`getStudentRequests`, `getEvaluationSummary`, `health`, …).

---

## 8. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `The database is not configured yet…` | `config.php` still has the placeholder host/name/user. Copy the real values from the control panel. |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong database username/password (they differ from your hosting login). |
| `Unknown database` | The database name must include the `if0_XXXXXXXX_` prefix. |
| Page loads but "Staff loading failed: Unknown action" | You uploaded an old `api.php`, or `inc/` files are missing. Re-upload the whole project. |
| Dropdown says "No active staff found" | The `staff` table is empty or every row has `Active = 0`. Insert the staff names. |
| Submit works but no e-mail arrives | `mail.mode` is `log`, or the SMTP credentials are wrong (test with `setup.php`). |
| `The server could not complete the request` | Set `'debug' => true` temporarily to see the exact error, then set it back to `false`. |
| HTTP 500 on every page | PHP version is too old (InfinityFree gives 8.3/8.4; PHP 8.0+ is required). |
| Changes do not appear | Free hosting caches static files; append `?v=2` to the URL or wait a minute. |

InfinityFree limits worth knowing: ~10 s PHP execution time (all our requests
finish in milliseconds), no cron jobs, no `mail()`, SMTP only on port 587, and
the database user can only connect from the hosting server (so no remote MySQL
from your PC — test locally with XAMPP or the SQLite mode below).

---

## 9. Local testing (optional)

**XAMPP**: copy the project to `htdocs`, create a MySQL database `ppu`, import
`database/schema.mysql.sql`, set the values in `config.php`
(`host = localhost`, `user = root`, `pass = ''`) and open
`http://localhost/project/setup.php`.

**No MySQL at all?** Temporarily switch the driver (never on the live site):

```php
'db' => [ 'driver' => 'sqlite', 'sqlite_path' => __DIR__ . '/storage/database.sqlite', ... ],
```

then run `setup.php`. SQLite needs the `pdo_sqlite` extension; everything else
behaves the same.

---

## 10. Credits

Panpacific University — Registrar Office.
SERVE: Student Evaluation of Registrar Service Excellence.
Form design and frontend logic retained from the original Apps Script
deployment; backend re-implemented in PHP 8 + MySQL for InfinityFree.
