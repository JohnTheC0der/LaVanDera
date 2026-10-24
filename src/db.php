<?php
/** One shared PDO connection per request. Use:  $rows = db()->query('SELECT ...')->fetchAll(); */
if (!function_exists('db')) {
    function db()
    {
        static $pdo = null;
        if ($pdo === null) {
            $c   = config('db');
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '" . $c['timezone'] . "'");
        }
        return $pdo;
    }
}

/** " FOR UPDATE" on MySQL (row lock inside a transaction); empty on other drivers. */
function db_lock_clause()
{
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}
