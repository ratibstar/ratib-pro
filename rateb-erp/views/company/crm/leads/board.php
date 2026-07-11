<?php
declare(strict_types=1);
/** @var array<string, list<array<string,mixed>>> $byStatus */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_lead_board')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 flex-nowrap overflow-auto pb-2">
        <?php foreach (($statuses ?? []) as $st): ?>
        <div class="col-10 col-md-4 col-xl-3">
            <div class="border rounded bg-light-subtle h-100">
                <div class="px-3 py-2 border-bottom fw-semibold"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?>
                    <span class="badge text-bg-dark"><?php echo (int) (($board[$st] ?? 0)); ?></span>
                </div>
                <div class="p-2" style="min-height:12rem;">
                    <?php foreach (($byStatus[$st] ?? []) as $row): ?>
                        <a class="d-block border rounded bg-white p-2 mb-2 text-decoration-none text-body" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars((string) ($row['lead_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
