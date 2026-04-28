<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/migrations/run_add_12_countries.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/migrations/run_add_12_countries.php`.
 */
/**
 * Run add_12_countries migration and show control_countries contents.
 * Access: https://out.ratib.sa/config/migrations/run_add_12_countries.php
 * Must be logged in to Control Panel (session required).
 */
session_start();
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: text/html; charset=UTF-8');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    die('<h2>Error</h2><p>Control database not available. Run from Control Panel context.</p>');
}

if (empty($_SESSION['control_logged_in'])) {
    die('<h2>Login required</h2><p><a href="/pages/login.php?control=1">Log in to Control Panel</a> first, then run this script.</p>');
}

$countries = [
    ['Bangladesh', 'bangladesh', 1],
    ['Saudi Arabia', 'saudi_arabia', 2],
    ['Uganda', 'uganda', 3],
    ['Kenya', 'kenya', 4],
    ['Philippines', 'philippines', 5],
    ['Indonesia', 'indonesia', 6],
    ['Ethiopia', 'ethiopia', 7],
    ['Nigeria', 'nigeria', 8],
    ['Rwanda', 'rwanda', 9],
    ['Sri Lanka', 'sri_lanka', 10],
    ['Thailand', 'thailand', 11],
    ['Nepal', 'nepal', 12],
];

$chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
if (!$chk || $chk->num_rows === 0) {
    die('<h2>Error</h2><p>Table control_countries does not exist. Run control_tables_in_main_db.sql first.</p>');
}

$inserted = 0;
$stmt = $ctrl->prepare("INSERT IGNORE INTO control_countries (name, slug, is_active, sort_order) VALUES (?, ?, 1, ?)");
if ($stmt) {
    foreach ($countries as $c) {
        $stmt->bind_param('ssi', $c[0], $c[1], $c[2]);
        $stmt->execute();
        if ($ctrl->affected_rows > 0) $inserted++;
    }
    $stmt->close();
}

$res = $ctrl->query("SELECT id, name, slug, is_active, sort_order FROM control_countries ORDER BY sort_order ASC, name ASC");
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $res->close();
}

echo "<h2>Control Countries</h2>";
echo "<p>Inserted: <strong>$inserted</strong> new countries. Total: <strong>" . count($rows) . "</strong></p>";
echo "<table border='1' cellpadding='6'><tr><th>ID</th><th>Name</th><th>Slug</th><th>Active</th><th>Sort</th></tr>";
foreach ($rows as $r) {
    echo "<tr><td>{$r['id']}</td><td>{$r['name']}</td><td>{$r['slug']}</td><td>{$r['is_active']}</td><td>{$r['sort_order']}</td></tr>";
}
echo "</table>";
echo "<p><a href='/pages/control/control-panel-users.php'>Back to Control Panel Users</a></p>";
