<?php
/**
 * setup.php - one time installer / health check.
 *
 * 1. Upload the project to htdocs.
 * 2. Fill in the MySQL values in config.php.
 * 3. Open https://your-site/ setup.php and press "Run installation".
 * 4. Delete setup.php when you are done.
 */

require __DIR__ . '/inc/bootstrap.php';

$messages = [];
$errors = [];
$installed = false;
$setupKey = '';
$locked = false;

try {
    app_boot();
    $setupKey = (string) config('setup_key', '');
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$lockFile = __DIR__ . '/install.lock';

/* ------------------------------------------------------------------ actions */
if (empty($errors) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = isset($_POST['do']) ? (string) $_POST['do'] : '';
    $providedKey = isset($_POST['key']) ? (string) $_POST['key'] : '';

    $keyOk = $setupKey !== '' && hash_equals($setupKey, $providedKey);

    try {
        $installed = db_is_installed();
    } catch (Throwable $e) {
        $installed = false;
        $errors[] = $e->getMessage();
    }

    $allowed = $keyOk || ($setupKey === '' && !$installed && !is_file($lockFile));
    if (!$allowed) {
        $errors[] = $setupKey === ''
            ? 'Installation is locked. Delete install.lock (or set setup_key in config.php) to run the installer again.'
            : 'Wrong setup key. Enter the value of setup_key in config.php.';
    } elseif ($action === 'install') {
        try {
            $messages = schema_install();
            @file_put_contents($lockFile, "Installed: " . date('c') . PHP_EOL);
            $installed = true;
            $messages[] = 'Setup completed successfully.';
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    } elseif ($action === 'testmail') {
        $target = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
        if (!is_valid_email($target)) {
            $errors[] = 'Enter a valid e-mail address for the test message.';
        } else {
            $result = mail_send($target, 'PPU Registrar test message', "This is a test message from your Registrar portal.\r\n\r\nIf you can read this, outgoing e-mail works.");
            notification_log('', $target, 'SETUP_TEST', 'PPU Registrar test message', 'Setup test message', $result['status']);
            if ($result['ok']) {
                $messages[] = 'Test message handled: status "' . $result['status'] . '".'
                    . ($result['status'] === 'Logged' ? ' The mail mode is "log", so nothing was actually sent. Switch mail.mode to "smtp" in config.php.' : '');
            } else {
                $errors[] = 'Test message failed: ' . $result['error'];
            }
        }
    }
}

/* ------------------------------------------------------------- diagnostics */
$checks = [];
$checks['PHP version (8.0+)'] = [version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION];
$checks['PDO extension'] = [extension_loaded('pdo'), extension_loaded('pdo') ? 'loaded' : 'missing'];
$checks['MySQL driver (pdo_mysql)'] = [extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'loaded' : 'missing'];
$checks['OpenSSL (SMTP TLS)'] = [extension_loaded('openssl'), extension_loaded('openssl') ? 'loaded' : 'missing'];
$checks['JSON extension'] = [extension_loaded('json'), extension_loaded('json') ? 'loaded' : 'missing'];
$checks['config.php readable'] = [is_file(__DIR__ . '/config.php'), is_file(__DIR__ . '/config.php') ? 'found' : 'missing'];

if (empty($errors)) {
    try {
        db();
        $checks['Database connection'] = [true, 'connected to ' . config('db.name') . ' on ' . config('db.host')];
    } catch (Throwable $e) {
        $checks['Database connection'] = [false, $e->getMessage()];
    }
}

try {
    $installed = empty($errors) ? db_is_installed() : $installed;
} catch (Throwable $e) {
    $installed = false;
}
$checks['Tables installed'] = [$installed, $installed ? 'yes' : 'not yet'];

$definition = schema_definition();
$missingTables = [];
foreach (array_keys($definition['tables']) as $table) {
    try {
        if (!db_table_exists($table)) {
            $missingTables[] = $table;
        }
    } catch (Throwable $e) {
        $missingTables[] = $table;
    }
}

$placeholderStaff = [];
if ($installed) {
    try {
        $placeholderStaff = db_all("SELECT Name FROM staff WHERE Name LIKE 'Registrar Staff %' ORDER BY SortOrder");
    } catch (Throwable $e) {
        $placeholderStaff = [];
    }
}

$mailMode = (string) config('mail.mode', 'log');
$locked = is_file($lockFile) && $setupKey === '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup · Panpacific University Registrar</title>
<style>
  :root{--navy:#263f77;--green:#406f27;--ink:#1F2933;--muted:#6B7785;--line:#E3E9EF;--bg:#F6F8FB}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font-family:ui-sans-serif,system-ui,"Segoe UI",sans-serif;line-height:1.5}
  .topline{height:5px;background:linear-gradient(90deg,var(--green) 0 14%,var(--navy) 14% 64%,var(--navy) 64%)}
  header{background:var(--navy);color:#fff;padding:22px 24px}
  header h1{margin:0;font-size:18px}
  header p{margin:4px 0 0;color:#C7D3E4;font-size:12px}
  main{max-width:900px;margin:24px auto 60px;padding:0 18px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:18px;box-shadow:0 10px 30px rgba(16,42,67,.05)}
  h2{margin:0 0 14px;font-size:15px;color:var(--navy);letter-spacing:.02em;text-transform:uppercase}
  table{width:100%;border-collapse:collapse;font-size:13px}
  td{padding:8px 6px;border-bottom:1px solid #F0F3F7;vertical-align:top}
  td:last-child{text-align:right;color:var(--muted)}
  .ok{color:var(--green);font-weight:700}
  .bad{color:#B42318;font-weight:700}
  .msg{border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:10px;white-space:pre-wrap}
  .msg.ok{background:#EAF5EF;color:#1B5E3F;border:1px solid #C9E6D6;font-weight:400}
  .msg.err{background:#FDECEC;color:#8E1F1F;border:1px solid #F5C7C7}
  .msg.warn{background:#FFF7E6;color:#7A4A00;border:1px solid #F2DFB4}
  label{display:block;font-size:12px;font-weight:700;margin:12px 0 6px}
  input{width:100%;padding:11px 12px;border:1px solid #D5DEE7;border-radius:10px;font:inherit}
  button{margin-top:14px;background:var(--navy);color:#fff;border:0;border-radius:10px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer}
  button.ghost{background:#fff;color:var(--navy);border:1px solid #C9D6E4}
  .row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
  code{background:#F2F5F9;padding:2px 6px;border-radius:6px;font-size:12px}
  .steps{font-size:13px;padding-left:18px}
  .steps li{margin-bottom:6px}
  a{color:var(--navy)}
</style>
</head>
<body>
<div class="topline"></div>
<header>
  <h1>Panpacific University · Registrar Office</h1>
  <p>Centralized Database Setup — Digital Document Request &amp; SERVE Evaluation</p>
</header>
<main>

  <?php foreach ($errors as $error): ?>
    <div class="msg err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endforeach; ?>
  <?php foreach ($messages as $message): ?>
    <div class="msg ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endforeach; ?>

  <?php if ($installed && !$errors): ?>
    <div class="msg ok"><strong>Database ready.</strong> The portal and the evaluation form can now use the central database.</div>
  <?php endif; ?>

  <div class="card">
    <h2>Environment checks</h2>
    <table>
      <?php foreach ($checks as $name => $result): ?>
        <tr>
          <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
          <td class="<?= $result[0] ? 'ok' : 'bad' ?>"><?= htmlspecialchars((string) $result[1], ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if ($missingTables && $installed === false): ?>
      <p style="font-size:12px;color:#6B7785;margin:12px 0 0;">Missing tables: <?= htmlspecialchars(implode(', ', $missingTables), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Install / repair tables</h2>
    <p style="font-size:13px;color:#4B5563;margin:0 0 6px;">
      Creates every table, index and the default rows (document catalog, administrator account, staff list).
      Running it again is safe — existing data is never deleted.
    </p>
    <form method="post" autocomplete="off">
      <input type="hidden" name="do" value="install">
      <?php if ($setupKey !== ''): ?>
        <label for="key">Setup key (config.php → setup_key)</label>
        <input id="key" name="key" type="password" placeholder="Enter your setup key">
      <?php endif; ?>
      <button type="submit">Run installation</button>
    </form>
    <?php if ($locked): ?>
      <div class="msg warn" style="margin-top:14px;">
        The installer is locked because <code>install.lock</code> exists. Set <code>setup_key</code> in config.php
        (or delete install.lock) if you need to run it again.
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Outgoing e-mail</h2>
    <p style="font-size:13px;color:#4B5563;margin:0;">
      Current mode: <code><?= htmlspecialchars($mailMode, ENT_QUOTES, 'UTF-8') ?></code>.
      InfinityFree does not allow <code>mail()</code>; use <code>smtp</code> with port 587 and a real mailbox
      (Gmail app password, Brevo, SMTP2GO …). In <code>log</code> mode messages are only stored in the
      <code>notifications</code> table.
    </p>
    <form method="post" autocomplete="off">
      <input type="hidden" name="do" value="testmail">
      <?php if ($setupKey !== ''): ?>
        <label for="key2">Setup key</label>
        <input id="key2" name="key" type="password" placeholder="Enter your setup key">
      <?php endif; ?>
      <label for="email">Send a test message to</label>
      <input id="email" name="email" type="email" placeholder="your.name@panpacificu.edu.ph">
      <button type="submit" class="ghost">Send test message</button>
    </form>
  </div>

  <?php if ($placeholderStaff): ?>
    <div class="card">
      <h2>Staff directory</h2>
      <div class="msg warn">
        The staff list still contains placeholder names:
        <?= htmlspecialchars(implode(', ', array_column($placeholderStaff, 'Name')), ENT_QUOTES, 'UTF-8') ?>.
        Replace them with the real registrar staff (phpMyAdmin → <code>staff</code> table, or run the SQL below),
        otherwise students will evaluate the wrong names.
      </div>
      <pre style="background:#F7F9FC;border:1px solid #E3E9EF;border-radius:10px;padding:12px;font-size:12px;overflow:auto;">UPDATE staff SET Name = 'Juan Dela Cruz', Position = 'Registrar Staff' WHERE StaffID = 'STAFF-001';
INSERT INTO staff (StaffID, Name, Position, Active, SortOrder) VALUES ('STAFF-004', 'Maria Santos', 'Registrar Staff', 1, 40);</pre>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Next steps</h2>
    <ol class="steps">
      <li>Delete <code>setup.php</code> from the server when the checks are green.</li>
      <li>Open the site root — the SERVE evaluation form uses this database through <code>api.php</code>.</li>
      <li>Keep <code>debug =&gt; false</code> in config.php for the live site.</li>
      <li>Enable free SSL in the InfinityFree control panel so the form is served over HTTPS.</li>
    </ol>
    <p style="margin:14px 0 0;"><a href="./">Open the portal →</a></p>
  </div>

</main>
</body>
</html>
