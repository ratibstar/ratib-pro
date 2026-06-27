<?php
$envFile = '/home/admin/domains/dev.rateb.sa/public_html/.env';
$vars = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $vars[trim($k)] = trim($v, " \t\"'");
}
$pass = $vars['DB_PASS'] ?? '';
$users = ['admin_rateb_dev', 'admin_rateb', 'admin'];
foreach ($users as $u) {
    $m = @new mysqli('127.0.0.1', $u, $pass, 'admin_rateb_dev', 3306);
    if ($m->connect_error) {
        echo $u . '=FAIL:' . $m->connect_error . PHP_EOL;
        continue;
    }
    echo $u . '=OK' . PHP_EOL;
    $m->close();
}
