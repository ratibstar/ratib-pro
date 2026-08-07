<?php
declare(strict_types=1);
$item = $item ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($item['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/contacts')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="border rounded p-3 mb-3">
        <div><strong><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div><strong><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['phone'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div><strong><?php echo htmlspecialchars(__('crm_companies'), ENT_QUOTES, 'UTF-8'); ?>:</strong>
            <?php if (!empty($item['crm_company_id'])): ?>
                <a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/companies') . '/' . (int) $item['crm_company_id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($item['crm_company_name'] ?? ('#' . (int) $item['crm_company_id'])), ENT_QUOTES, 'UTF-8'); ?></a>
            <?php else: ?>—<?php endif; ?>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_leads'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($leads ?? []) as $row): ?>
                <li class="list-group-item"><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['lead_no'] ?? $row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php endforeach; ?>
                <?php if (($leads ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-md-6">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_opportunities'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($opportunities ?? []) as $row): ?>
                <li class="list-group-item"><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['opportunity_no'] ?? $row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php endforeach; ?>
                <?php if (($opportunities ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
