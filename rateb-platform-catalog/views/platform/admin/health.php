<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_health', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="admin-split">
        <section>
            <h2 class="h6">/health</h2>
            <div id="healthLiveness"></div>
        </section>
        <section>
            <h2 class="h6">/ready</h2>
            <div id="healthReady"></div>
        </section>
    </div>
</div>
