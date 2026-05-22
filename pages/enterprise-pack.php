<?php
/**
 * Enterprise document packs — print-ready HTML (browser Save as PDF).
 * /enterprise-pack/?pack=profile|architecture|procurement|partners|api
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ratib-public-base-url.php';
require_once __DIR__ . '/../includes/ratib-public-cms.php';

$baseUrl = ratib_public_site_base_url();
$pack = isset($_GET['pack']) ? trim((string) $_GET['pack']) : 'index';

$packs = [
    'profile' => [
        'title' => 'RATEB — Executive Company Profile',
        'subtitle' => ratib_brand_expansion(),
        'body' => 'RATEB is enterprise workforce program infrastructure for regulated, cross-border recruitment programs. Dashboard, workforce tracking, compliance checkpoints, and finance-grade events on one multi-tenant stack.',
    ],
    'architecture' => [
        'title' => 'RATEB — Enterprise Architecture Brief',
        'subtitle' => 'Platform layers · tenant isolation · event delivery',
        'body' => 'Seven-layer model: experience, orchestration, tracking, business modules, governance, commercial, and data. Separate agency databases, RBAC, replay-safe workflows, and GPS tracking.',
    ],
    'procurement' => [
        'title' => 'RATEB — Procurement One-Pager',
        'subtitle' => 'Procurement-ready posture',
        'body' => 'TLS 1.3, RBAC, audit trails, SLA visibility, webhook integrity, and documented engagement process for ministries and enterprise buyers.',
    ],
    'partners' => [
        'title' => 'RATEB — Agency Partnership Deck',
        'subtitle' => 'Agency operations workspace',
        'body' => 'Multi-agency corridors, branded domains, stage graphs, worker records, and reports for sending-country programs.',
    ],
    'api' => [
        'title' => 'RATEB — API Overview',
        'subtitle' => 'Integration standards',
        'body' => 'Tenant-scoped APIs, idempotent writes, rate limits, notification webhooks, and finance-linked event hooks.',
    ],
];

if ($pack === 'index' || !isset($packs[$pack])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>RATEB Enterprise Packs</title></head><body style="font-family:system-ui;background:#0a0e1a;color:#e8edf7;padding:2rem">';
    echo '<h1>RATEB Enterprise Document Packs</h1><p>Open a pack, then use <strong>Print → Save as PDF</strong>.</p><ul>';
    foreach (array_keys($packs) as $k) {
        echo '<li><a style="color:#38bdf8" href="?pack=' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($packs[$k]['title'], ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    echo '</ul></body></html>';
    exit;
}

$p = $packs[$pack];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        @media print { .no-print { display: none; } }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0c1020; color: #e8edf7; margin: 0; padding: 2.5rem; }
        h1 { font-size: 1.75rem; margin: 0 0 0.25rem; color: #c4b5fd; }
        h2 { font-size: 1rem; font-weight: 500; color: #94a3b8; margin: 0 0 1.5rem; }
        p { line-height: 1.6; max-width: 40rem; }
        .tag { font-size: 0.75rem; letter-spacing: 0.08em; text-transform: uppercase; color: #38bdf8; }
        .btn { display: inline-block; margin-top: 1.5rem; padding: 0.5rem 1rem; background: #7c3aed; color: #fff; text-decoration: none; border-radius: 6px; }
    </style>
</head>
<body>
    <p class="tag no-print">Print → Save as PDF · <?php echo htmlspecialchars(ratib_brand_category(), ENT_QUOTES, 'UTF-8'); ?></p>
    <h1><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <h2><?php echo htmlspecialchars($p['subtitle'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <p><?php echo htmlspecialchars($p['body'], ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>Contact:</strong> info@out.ratib.sa · out.ratib.sa</p>
    <a class="btn no-print" href="#" onclick="window.print();return false;">Print / Save PDF</a>
    <a class="btn no-print" href="?">All packs</a>
</body>
</html>
