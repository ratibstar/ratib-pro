<div class="admin-stat-grid" id="dashboardStats"></div>
<div class="admin-split">
    <section class="admin-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><?= htmlspecialchars(catalog__('nav_health', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:dashboard-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div id="dashboardHealth"></div>
    </section>
    <section class="admin-panel">
        <h2 class="h5 mb-3"><?= htmlspecialchars(catalog__('nav_queue', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
        <div id="dashboardQueue"></div>
    </section>
</div>
<div class="admin-panel mt-3">
    <h2 class="h5 mb-3"><?= htmlspecialchars(catalog__('nav_products', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
    <div id="dashboardProducts"></div>
</div>
