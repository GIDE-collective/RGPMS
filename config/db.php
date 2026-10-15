<?php
/**
 * RG PMS - Database connection (PDO)
 */
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:2rem;max-width:40rem;margin:3rem auto;border:1px solid #dc3545;border-radius:.5rem;color:#842029;background:#f8d7da">'
                . '<h3>Database connection failed</h3><p>Please check <code>config/config.php</code> and confirm the '
                . 'database <code>' . htmlspecialchars(DB_NAME) . '</code> exists (import <code>sql/schema.sql</code>).</p>'
                . '<p style="font-size:.85rem">' . htmlspecialchars($e->getMessage()) . '</p></div>');
        }
    }
    return $pdo;
}
