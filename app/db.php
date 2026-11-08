<?php
/**
 * Database connection (PDO, real prepared statements).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['config'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['db_host'], (int)$c['db_port'], $c['db_name']);
        try {
            $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
            ]);
            // keep MySQL time in sync with PHP time zone
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            $msg = $c['debug'] ? $e->getMessage() : 'Please check app/config.local.php';
            exit('<h2 style="font-family:sans-serif">Database connection failed</h2><p style="font-family:sans-serif">'
                . htmlspecialchars($msg) . '</p>');
        }
    }
    return $pdo;
}

/** Run a query with parameters and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Fetch one row or null. */
function q1(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Fetch all rows. */
function qa(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Fetch a single value. */
function qv(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}
