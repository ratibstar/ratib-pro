<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $items */
/** @var array<string,int> $board */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_leads')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads/board')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_lead_board'), ENT_QUOTES, 'UTF-8'); ?></a>
            <?php if (!empty($canCreate)): ?>
            <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads/create')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_lead_create'), ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-4"><input class="form-control" name="q" value="<?php echo htmlspecialchars((string) ($q ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3">
            <select class="form-select" name="status">
                <option value=""><?php echo htmlspecialchars(__('all'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($statuses ?? []) as $st): ?>
                <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($status ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars(rateb_enum_label($st), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('contact'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['lead_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['contact_name'] ?? $row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars(rateb_enum_label((string) ($row['workflow_status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
