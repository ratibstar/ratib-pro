<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$id = (int) ($argv[1] ?? 80);
$db = db();
$c = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
$c->execute([$id]);
$row = $c->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "missing\n";
    exit(1);
}
foreach (['id', 'user_id', 'title', 'status', 'product', 'description', 'goal', 'audience'] as $k) {
    if (array_key_exists($k, $row)) {
        echo $k . '=' . substr((string) $row[$k], 0, 200) . PHP_EOL;
    }
}
echo "---outputs---\n";
$o = $db->prepare('SELECT id, output_type, approval_status, LEFT(content, 120) AS preview FROM campaign_outputs WHERE campaign_id = ? ORDER BY id DESC LIMIT 12');
$o->execute([$id]);
foreach ($o->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo "---media---\n";
$m = $db->prepare('SELECT id, kind, source, approval_status, created_at FROM campaign_media WHERE campaign_id = ? ORDER BY id DESC LIMIT 10');
$m->execute([$id]);
foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo "---credits---\n";
$u = $db->prepare('SELECT id, credits_balance, email FROM users WHERE id = ?');
$u->execute([(int) $row['user_id']]);
echo json_encode($u->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . PHP_EOL;
