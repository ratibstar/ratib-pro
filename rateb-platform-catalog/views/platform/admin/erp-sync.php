<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_erp_sync', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="erpSyncForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="company_id" id="erpCompanyId" placeholder="Company ID" required>
        <input class="form-control form-control-sm" name="since" id="erpSince" placeholder="since (ISO datetime)">
        <input class="form-control form-control-sm" name="limit" id="erpLimit" placeholder="limit" value="100">
        <button type="submit" class="btn btn-sm btn-primary">Load sync</button>
    </form>
    <div id="erpSyncResult"></div>
</div>
