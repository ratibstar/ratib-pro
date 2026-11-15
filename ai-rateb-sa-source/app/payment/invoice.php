<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT r.*, u.name, u.email FROM payment_receipts r INNER JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.user_id = ? LIMIT 1');
$stmt->execute([$id, $userId]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('Not found');
}
$ar = ui_language() === 'ar';
$sar = number_format((int) $row['amount'] / 100, 2, '.', '');
$date = (string) $row['created_at'];
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="<?= $ar ? 'ar' : 'en' ?>" dir="<?= $ar ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $ar ? 'فاتورة RATEB AI' : 'RATEB AI Invoice' ?> — R<?= (int) $row['id'] ?></title>
<style>
body{font-family:Manrope,Tajawal,sans-serif;margin:0;padding:32px;background:#f4f6fb;color:#0f172a}
.card{max-width:640px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px}
h1{font-size:1.25rem;margin:0 0 4px}
.muted{color:#64748b;font-size:14px}
table{width:100%;border-collapse:collapse;margin:20px 0}
th,td{padding:10px 0;text-align:start;border-bottom:1px solid #e2e8f0}
.print{margin-top:16px}
@media print{.print{display:none}}
</style>
</head>
<body>
<article class="card">
<h1>RATEB AI</h1>
<p class="muted"><?= $ar ? 'فاتورة تجارية مبسّطة' : 'Commercial invoice (simplified)' ?></p>
<table>
<tr><th><?= $ar ? 'رقم الإيصال' : 'Receipt' ?></th><td>R<?= (int) $row['id'] ?></td></tr>
<tr><th><?= $ar ? 'التاريخ' : 'Date' ?></th><td><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></td></tr>
<tr><th><?= $ar ? 'العميل' : 'Customer' ?></th><td><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8') ?></td></tr>
<tr><th><?= $ar ? 'الباقة' : 'Package' ?></th><td><?= htmlspecialchars((string) $row['package_type'], ENT_QUOTES, 'UTF-8') ?></td></tr>
<tr><th><?= $ar ? 'المبلغ' : 'Amount' ?></th><td><strong><?= $sar ?> SAR</strong></td></tr>
<tr><th>Moyasar</th><td><?= htmlspecialchars((string) $row['moyasar_id'], ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>
<p class="muted"><?= $ar ? 'هذه وثيقة مرجعية وليست فاتورة ضريبية معتمدة.' : 'This is a reference document, not a tax-validated invoice.' ?></p>
<p class="print"><button type="button" onclick="window.print()"><?= $ar ? 'طباعة / PDF' : 'Print / PDF' ?></button> · <a href="/billing.php"><?= $ar ? 'الفواتير' : 'Billing' ?></a></p>
</article>
</body>
</html>