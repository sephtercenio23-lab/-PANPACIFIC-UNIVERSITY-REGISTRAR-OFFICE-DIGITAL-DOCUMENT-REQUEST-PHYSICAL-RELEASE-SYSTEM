<?php
/**
 * actions/system.php - installation and health helpers.
 *
 * Port of the Apps Script function setupSystem(): it used to create the Google
 * Sheets and their default rows; here it creates the MySQL tables, indexes and
 * the same default rows (document catalog, administrator, staff placeholders).
 */

/**
 * Apps Script: setupSystem() -> 'Setup completed successfully.'
 *
 * Remote access is disabled by default (config: allow_remote_setup) because
 * running it from the browser is a maintenance task, not a public action.
 *
 * @return string
 */
function act_setupSystem()
{
    if (!(bool) config('allow_remote_setup', false)) {
        fail('Remote setup is disabled. Run setup.php from the hosting file manager instead.');
    }

    app_boot();
    schema_install();
    audit_log('', 'system', 'SETUP_RUN', '', 'installed', 'schema_install via API');

    return 'Setup completed successfully.';
}

/**
 * Basic health information (useful after deploying).
 *
 * @return array<string,mixed>
 */
function act_health()
{
    $installed = db_is_installed();

    return [
        'ok'               => $installed,
        'phpVersion'       => PHP_VERSION,
        'driver'           => (string) config('db.driver', 'mysql'),
        'installed'        => $installed,
        'documents'        => $installed ? count(document_types()) : 0,
        'staff'            => $installed ? count(act_getStaff()) : 0,
        'mailMode'         => (string) config('mail.mode', 'log'),
        'timezone'         => date_default_timezone_get(),
        'serverTime'       => app_now()->format('Y-m-d H:i:s'),
    ];
}
