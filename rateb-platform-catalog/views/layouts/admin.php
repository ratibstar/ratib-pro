<?php
/** @var string $locale */
/** @var string $dir */
/** @var string $pageKey */
/** @var list<array{key:string,route:string,icon:string,group:string,label:string}> $navItems */
/** @var list<string> $permissions */
/** @var bool $authenticated */
/** @var string $title */
/** @var string $release */
/** @var string $architecture */

$base = defined('RATEB_PLATFORM_CATALOG_BASE_URL') ? rtrim((string) RATEB_PLATFORM_CATALOG_BASE_URL, '/') : '';
$assetBase = $base !== '' ? $base : '';
$otherLang = $locale === 'ar' ? 'en' : 'ar';
$bootstrapCss = $dir === 'rtl'
    ? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css'
    : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$groups = [];
foreach ($navItems as $item) {
    $groups[$item['group']][] = $item;
}
$groupLabels = [
    'overview' => catalog__('nav_group_overview', $locale),
    'catalog' => catalog__('nav_group_catalog', $locale),
    'commerce' => catalog__('nav_group_commerce', $locale),
    'operations' => catalog__('nav_group_operations', $locale),
    'governance' => catalog__('nav_group_governance', $locale),
    'integrations' => catalog__('nav_group_integrations', $locale),
    'system' => catalog__('nav_group_system', $locale),
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($bootstrapCss, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/assets/css/admin.css">
</head>
<body class="admin-body"
      data-locale="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>"
      data-dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>"
      data-page="<?= htmlspecialchars((string) $pageKey, ENT_QUOTES, 'UTF-8') ?>"
      data-base="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>"
      data-authenticated="<?= !empty($authenticated) ? '1' : '0' ?>">
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-brand">
            <div class="admin-brand-mark">R</div>
            <div>
                <div class="admin-brand-title"><?= htmlspecialchars(catalog__('admin_panel', $locale), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="admin-brand-meta">v<?= htmlspecialchars((string) ($architecture ?? '1.3.1'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <nav class="admin-nav" aria-label="<?= htmlspecialchars(catalog__('admin_navigation', $locale), ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($groups as $groupKey => $items): ?>
                <div class="admin-nav-group">
                    <div class="admin-nav-group-label"><?= htmlspecialchars($groupLabels[$groupKey] ?? $groupKey, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $href = $assetBase . $item['route'] . '?lang=' . rawurlencode($locale);
                        $active = ($pageKey ?? '') === $item['key'];
                        ?>
                        <a class="admin-nav-link<?= $active ? ' is-active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" id="adminSidebarToggle" aria-label="<?= htmlspecialchars(catalog__('admin_toggle_menu', $locale), ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-list"></i>
            </button>
            <div class="admin-topbar-title">
                <?= htmlspecialchars(catalog__('nav_' . ($pageKey ?? 'dashboard'), $locale), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="admin-topbar-actions">
                <?php if (empty($authenticated)): ?>
                    <span class="badge text-bg-warning"><?= htmlspecialchars(catalog__('admin_guest_mode', $locale), ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <span class="badge text-bg-success"><?= htmlspecialchars(catalog__('admin_authenticated', $locale), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <a class="btn btn-outline-secondary btn-sm" href="?lang=<?= htmlspecialchars($otherLang, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(strtoupper($otherLang), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </header>

        <main class="admin-content">
            <div id="adminFlash" class="admin-flash" hidden></div>
            <?php require $contentView; ?>
        </main>

        <footer class="admin-footer">
            <span><?= htmlspecialchars(catalog__('app_name', $locale), ENT_QUOTES, 'UTF-8') ?></span>
            <span>release <?= htmlspecialchars((string) ($release ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </footer>
    </div>
</div>

<script>
window.RatebAdminConfig = {
    locale: <?= json_encode($locale, JSON_UNESCAPED_UNICODE) ?>,
    dir: <?= json_encode($dir, JSON_UNESCAPED_UNICODE) ?>,
    page: <?= json_encode((string) $pageKey, JSON_UNESCAPED_UNICODE) ?>,
    baseUrl: <?= json_encode($assetBase, JSON_UNESCAPED_UNICODE) ?>,
    authenticated: <?= !empty($authenticated) ? 'true' : 'false' ?>,
    permissions: <?= json_encode(array_values($permissions ?? []), JSON_UNESCAPED_UNICODE) ?>,
    i18n: {
        loading: <?= json_encode(catalog__('admin_loading', $locale), JSON_UNESCAPED_UNICODE) ?>,
        error: <?= json_encode(catalog__('admin_error', $locale), JSON_UNESCAPED_UNICODE) ?>,
        success: <?= json_encode(catalog__('admin_success', $locale), JSON_UNESCAPED_UNICODE) ?>,
        empty: <?= json_encode(catalog__('admin_empty', $locale), JSON_UNESCAPED_UNICODE) ?>,
        confirm: <?= json_encode(catalog__('admin_confirm', $locale), JSON_UNESCAPED_UNICODE) ?>,
        save: <?= json_encode(catalog__('admin_save', $locale), JSON_UNESCAPED_UNICODE) ?>,
        cancel: <?= json_encode(catalog__('admin_cancel', $locale), JSON_UNESCAPED_UNICODE) ?>,
        create: <?= json_encode(catalog__('admin_create', $locale), JSON_UNESCAPED_UNICODE) ?>,
        edit: <?= json_encode(catalog__('admin_edit', $locale), JSON_UNESCAPED_UNICODE) ?>,
        delete: <?= json_encode(catalog__('admin_delete', $locale), JSON_UNESCAPED_UNICODE) ?>,
        refresh: <?= json_encode(catalog__('admin_refresh', $locale), JSON_UNESCAPED_UNICODE) ?>,
        actions: <?= json_encode(catalog__('admin_actions', $locale), JSON_UNESCAPED_UNICODE) ?>,
        search: <?= json_encode(catalog__('admin_search_placeholder', $locale), JSON_UNESCAPED_UNICODE) ?>,
        no_api: <?= json_encode(catalog__('admin_no_api', $locale), JSON_UNESCAPED_UNICODE) ?>,
        unauthorized: <?= json_encode(catalog__('admin_unauthorized', $locale), JSON_UNESCAPED_UNICODE) ?>
    }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/assets/js/admin-api.js"></script>
<script src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/assets/js/admin-ui.js"></script>
<script src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/assets/js/admin-app.js"></script>
<?php
$pageScripts = [
    'dashboard' => 'dashboard.js',
    'products' => 'products.js',
    'categories' => 'categories.js',
    'brands' => 'brands.js',
    'suppliers' => 'suppliers.js',
    'families' => 'families.js',
    'attributes' => 'attributes.js',
    'collections' => 'collections.js',
    'channels' => 'channels.js',
    'pricing' => 'pricing.js',
    'media' => 'media.js',
    'import_export' => 'import-export.js',
    'search' => 'search.js',
    'change_requests' => 'change-requests.js',
    'workflow' => 'workflow.js',
    'seo' => 'seo.js',
    'versions' => 'versions.js',
    'duplicates' => 'duplicates.js',
    'saved_filters' => 'saved-filters.js',
    'erp_sync' => 'erp-sync.js',
    'webhooks' => 'webhooks.js',
    'queue' => 'queue.js',
    'audit_logs' => 'audit-logs.js',
    'health' => 'health.js',
    'settings' => 'settings.js',
];
if (isset($pageScripts[$pageKey])):
?>
<script src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/assets/js/pages/<?= htmlspecialchars($pageScripts[$pageKey], ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
</body>
</html>
