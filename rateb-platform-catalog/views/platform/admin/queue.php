<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_queue', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="queueStatus"></div>
</div>
<div class="admin-panel mt-3">
    <h2 class="h5">Job detail</h2>
    <form id="jobLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="job_id" id="jobId" placeholder="Job ID" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Load</button>
        <button type="button" class="btn btn-sm btn-outline-primary" id="jobReplayBtn">Replay</button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="jobCancelBtn">Cancel</button>
    </form>
    <div id="jobDetail"></div>
    <div id="jobItems" class="mt-3"></div>
</div>
