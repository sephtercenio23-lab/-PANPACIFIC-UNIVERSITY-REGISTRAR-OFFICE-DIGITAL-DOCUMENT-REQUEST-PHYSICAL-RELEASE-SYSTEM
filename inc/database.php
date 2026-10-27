<?php
/**
 * database.php - single PDO connection + tiny query helpers.
 *
 * Every query in the project goes through these helpers and uses bound
 * parameters, so user input can never be injected into SQL.
 */

/**
 * Shared PDO connection.
 *
 * @return PDO
 */
function db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = strtolower((string) config('db.driver', 'mysql'));
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        if ($driver === 'sqlite') {
            $path = (string) config('db.sqlite_path');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $host    = (string) config('db.host');
            $port    = (int) config('db.port', 3306);
            $name    = (string) config('db.name');
            $charset = (string) config('db.charset', 'utf8mb4');

            if ($host === '' || $name === '' || (string) config('db.user') === '') {
                fail('The database is not configured yet. Open config.php and fill in the MySQL host name, database name, username and password from your InfinityFree control panel, then run setup.php.');
            }

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
            $pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), $options);
        }
    } catch (PDOException $e) {
        if ((bool) config('debug', false)) {
            fail('Database connection failed: ' . $e->getMessage());
        }
        fail('The central database is temporarily unavailable. Please try again in a moment.');
    }

    return $pdo;
}

/**
 * Run a statement and return the PDOStatement.
 *
 * @param string $sql
 * @param array  $params
 * @return PDOStatement
 */
function db_query($sql, array $params = [])
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

/** All rows as associative arrays. */
function db_all($sql, array $params = [])
{
    return db_query($sql, $params)->fetchAll();
}

/** First row or null. */
function db_one($sql, array $params = [])
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Single scalar value (first column of the first row). */
function db_value($sql, array $params = [], $default = null)
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? $default : $value;
}

/** Execute an INSERT/UPDATE/DELETE, returns affected rows. */
function db_exec($sql, array $params = [])
{
    return db_query($sql, $params)->rowCount();
}

/** Does a table exist in the current database? */
function db_table_exists($table)
{
    $driver = strtolower((string) config('db.driver', 'mysql'));

    try {
        if ($driver === 'sqlite') {
            $row = db_one("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        } else {
            $row = db_one('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
        }
    } catch (Exception $e) {
        return false;
    }

    return $row !== null;
}

/** Is the central database installed and reachable? */
function db_is_installed()
{
    try {
        return db_table_exists('evaluations') && db_table_exists('document_types');
    } catch (Exception $e) {
        return false;
    }
}
