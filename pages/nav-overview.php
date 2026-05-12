<?php
/**
 * Single-page map of public header navigation: mega menu + platform pills (expanded, clickable).
 * Open: /pages/nav-overview.php
 */
require_once __DIR__ . '/../includes/config.php';

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;

require_once __DIR__ . '/../includes/ratib-home-public-nav-bootstrap.php';
require_once __DIR__ . '/../includes/ratib-mega-nav-config.php';
require_once __DIR__ . '/../includes/ratib-mega-nav-resolve.php';

$ratibNavPrefix = '';
$pageTitle = 'Navigation map — ' . trim((string) ($ratibHome['home.meta.page_title'] ?? 'RATIB'));

$h = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

$megaItems = ratib_mega_nav_config();

$platformPills = [
    ['label' => (string) ($ratibHome['home.nav.platform'] ?? 'Platform'), 'href' => $ratibNavPrefix . '#platform'],
    ['label' => (string) $ratibNavProductTourLabel, 'href' => $ratibNavPrefix . $ratibNavProductTourHref],
    ['label' => trim((string) ($ratibHome['home.nav.product'] ?? '')) ?: (string) ($ratibHome['home.nav.features'] ?? 'Product'), 'href' => $ratibNavPrefix . '#features'],
    ['label' => trim((string) ($ratibHome['home.nav.pricing'] ?? '')) ?: (string) ($ratibHome['home.nav.programs'] ?? 'Pricing'), 'href' => $ratibNavPrefix . '#programs'],
    ['label' => trim((string) ($ratibHome['home.nav.partners'] ?? '')) ?: (string) ($ratibHome['home.nav.agencies'] ?? 'Partners'), 'href' => $ratibNavPrefix . '#agencies'],
    ['label' => (string) ($ratibHome['home.nav.contact'] ?? ''), 'href' => $ratibNavPrefix . '#contact'],
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <?php ratib_home_nav_emit_sync_guard_style(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $h($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $h($baseUrl); ?>/css/pages/home-public.css?v=<?php echo $h($ratibHomePublicCssQuery); ?>">
    <link rel="stylesheet" href="<?php echo $h($baseUrl); ?>/css/pages/ratib-mega-nav.css?v=<?php echo $h($ratibMegaNavCssQuery); ?>">
    <style>
      .ratib-nav-map-wrap { max-width: min(1160px, 96vw); margin: 0 auto; padding: 1.25rem 1rem 3rem; }
      .ratib-nav-map-wrap h1 { font-size: 1.5rem; margin: 0 0 0.5rem; color: var(--r-text); }
      .ratib-nav-map-wrap > p.lead { color: var(--r-muted); margin: 0 0 1.5rem; line-height: 1.5; }
      .ratib-nav-map-section { margin-bottom: 2rem; }
      .ratib-nav-map-section h2 { font-size: 1.15rem; margin: 0 0 0.75rem; color: #e2e8f0; border-bottom: 1px solid rgba(255,255,255,.08); padding-bottom: 0.35rem; }
      .ratib-nav-map-flat { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: stretch; margin-bottom: 1rem; }
      .ratib-nav-map-flat a,
      .ratib-nav-map-card a { display: inline-flex; flex-direction: column; gap: 0.15rem; padding: 0.6rem 0.75rem; border-radius: 10px; border: 1px solid rgba(139, 92, 246, 0.35); background: rgba(15, 23, 42, 0.65); color: #e2e8f0; text-decoration: none; font-weight: 600; font-size: 0.88rem; max-width: 100%; }
      .ratib-nav-map-flat a:hover,
      .ratib-nav-map-card a:hover { border-color: rgba(167, 139, 250, 0.55); background: rgba(124, 98, 200, 0.15); }
      .ratib-nav-map-flat small,
      .ratib-nav-map-card small { font-weight: 500; color: #94a3b8; font-size: 0.72rem; line-height: 1.3; }
      .ratib-nav-map-mega { display: grid; gap: 1rem; }
      .ratib-nav-map-mega-one { border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 1rem; background: rgba(15, 23, 42, 0.45); }
      .ratib-nav-map-mega-one > h3 { margin: 0 0 0.75rem; font-size: 1rem; color: #c4b5fd; }
      .ratib-nav-map-columns { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
      .ratib-nav-map-col h4 { margin: 0 0 0.5rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
      .ratib-nav-map-cards { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.45rem; }
      .ratib-nav-map-card .ti { font-weight: 700; color: #f1f5f9; font-size: 0.9rem; }
      .ratib-nav-map-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
      .ratib-nav-map-table th, .ratib-nav-map-table td { text-align: left; padding: 0.45rem 0.6rem; border-bottom: 1px solid rgba(255,255,255,.06); }
      .ratib-nav-map-table th { color: #94a3b8; font-weight: 600; }
      .ratib-nav-map-table td a { color: #a5b4fc; word-break: break-all; }
      .ratib-nav-map-table code { font-size: 0.75rem; color: #cbd5e1; }
    </style>
</head>
<body class="ratib-saas-home" data-ratib-page="nav-overview">

<?php include __DIR__ . '/../includes/ratib-home-public-chrome-top.php'; ?>

    <main class="ratib-main">
        <div class="ratib-nav-map-wrap ratib-container">
            <h1>Navigation map</h1>
            <p class="lead">Same header as the homepage, plus every mega-menu card and platform pill listed below so you can open destinations in <strong>this tab</strong>. For the full landing experience, go to <a href="<?php echo $h($baseUrl . '/pages/home.php'); ?>">home.php</a>.</p>

            <section class="ratib-nav-map-section" aria-labelledby="nav-map-flat">
                <h2 id="nav-map-flat">Top row — quick links &amp; sign-in</h2>
                <div class="ratib-nav-map-flat">
                    <?php foreach ($megaItems as $entry):
                        $type = (string) ($entry['type'] ?? '');
                        if ($type === 'mega') {
                            continue;
                        }
                        if ($type === 'signin'):
                            $href = ratib_mega_nav_resolve_href('customer_portal', $baseUrl, $ratibNavPrefix);
                            $lab = (string) ($entry['label'] ?? 'Sign In');
                            ?>
                    <a href="<?php echo $h($href); ?>"><span><?php echo $h($lab); ?></span><small>customer_portal · opens client portal</small></a>
                            <?php
                            continue;
                        endif;
                        if ($type === 'link'):
                            $hk = (string) ($entry['href_key'] ?? 'platform');
                            $href = ratib_mega_nav_resolve_href($hk, $baseUrl, $ratibNavPrefix);
                            $lab = (string) ($entry['label'] ?? '');
                            $desc = (string) ($entry['desc'] ?? '');
                            ?>
                    <a href="<?php echo $h($href); ?>"><span><?php echo $h($lab); ?></span><small><?php echo $h($hk . ($desc !== '' ? ' · ' . $desc : '')); ?></small></a>
                            <?php
                        endif;
                    endforeach; ?>
                </div>
            </section>

            <section class="ratib-nav-map-section" aria-labelledby="nav-map-mega">
                <h2 id="nav-map-mega">Mega menus — all columns &amp; cards</h2>
                <div class="ratib-nav-map-mega">
                    <?php foreach ($megaItems as $entry):
                        $type = (string) ($entry['type'] ?? '');
                        if ($type !== 'mega') {
                            continue;
                        }
                        $mid = (string) ($entry['id'] ?? '');
                        $mlabel = (string) ($entry['label'] ?? '');
                        $columns = $entry['columns'] ?? [];
                        if (!is_array($columns)) {
                            $columns = [];
                        }
                        ?>
                    <div class="ratib-nav-map-mega-one" id="nav-map-mega-<?php echo $h($mid); ?>">
                        <h3><i class="fas fa-bars-staggered me-1" aria-hidden="true"></i><?php echo $h($mlabel); ?></h3>
                        <div class="ratib-nav-map-columns">
                            <?php foreach ($columns as $col):
                                if (!is_array($col)) {
                                    continue;
                                }
                                $cheading = (string) ($col['heading'] ?? '');
                                $citems = $col['items'] ?? [];
                                if (!is_array($citems)) {
                                    $citems = [];
                                }
                                ?>
                            <div>
                                <?php if ($cheading !== ''): ?><h4><?php echo $h($cheading); ?></h4><?php endif; ?>
                                <ul class="ratib-nav-map-cards">
                                    <?php foreach ($citems as $it):
                                        if (!is_array($it)) {
                                            continue;
                                        }
                                        $hk = (string) ($it['href_key'] ?? 'platform');
                                        $ihref = ratib_mega_nav_resolve_href($hk, $baseUrl, $ratibNavPrefix);
                                        $title = (string) ($it['title'] ?? '');
                                        $desc = (string) ($it['desc'] ?? '');
                                        ?>
                                    <li class="ratib-nav-map-card">
                                        <a href="<?php echo $h($ihref); ?>">
                                            <span class="ti"><?php echo $h($title); ?></span>
                                            <small><?php echo $h($hk); ?><?php echo $desc !== '' ? ' · ' . $h($desc) : ''; ?></small>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ratib-nav-map-section" aria-labelledby="nav-map-pills">
                <h2 id="nav-map-pills">Platform icon row (same labels as header)</h2>
                <table class="ratib-nav-map-table">
                    <thead><tr><th>Label</th><th>Destination</th></tr></thead>
                    <tbody>
                        <?php foreach ($platformPills as $row): ?>
                        <tr>
                            <td><?php echo $h($row['label']); ?></td>
                            <td><a href="<?php echo $h($row['href']); ?>"><?php echo $h($row['href']); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>

    <script src="<?php echo $h($baseUrl); ?>/js/pages/ratib-mega-nav.js?v=<?php echo $h($ratibMegaNavJsQuery); ?>"></script>
</body>
</html>
