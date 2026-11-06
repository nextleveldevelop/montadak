<?php
/** Small mysqli wrapper: every query is a prepared statement. */

function db()
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? (int) DB_PORT : 3306);
        $conn->set_charset('utf8mb4');
        $conn->query("SET time_zone = '+00:00'"); // all stored times are UTC
    }
    return $conn;
}

function db_query($sql, $types = '', array $params = [])
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** All rows as an array of associative arrays. */
function db_all($sql, $types = '', array $params = [])
{
    $stmt = db_query($sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** First row or null. */
function db_row($sql, $types = '', array $params = [])
{
    $rows = db_all($sql, $types, $params);
    return $rows ? $rows[0] : null;
}

/** First column of the first row, or null. */
function db_val($sql, $types = '', array $params = [])
{
    $row = db_row($sql, $types, $params);
    return $row ? reset($row) : null;
}

/** Runs INSERT/UPDATE/DELETE, returns affected rows. */
function db_exec($sql, $types = '', array $params = [])
{
    $stmt = db_query($sql, $types, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function db_insert_id()
{
    return (int) db()->insert_id;
}

/** Runs $fn inside a transaction; rolls back and rethrows on any error. */
function db_transaction(callable $fn)
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $result = $fn();
        $conn->commit();
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
