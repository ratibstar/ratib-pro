<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/guard.php';

function db(): PDO
{
    static $pdo;
    if ($pdo) {
        return $pdo;
    }
    $c = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$c['host']};dbname={$c['database']};charset={$c['charset']}",
        $c['username'],
        $c['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}
