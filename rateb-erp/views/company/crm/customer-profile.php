<?php
declare(strict_types=1);
$customer = $customer ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($customer['name'] ?? (__('crm_customer_360') . ' #' . (int) ($customer_id ?? 0))), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($customer['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($customer['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($customer['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_companies'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($crm_companies ?? []) as $row): ?>
                <li class="list-group-item"><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/companies') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php endforeach; ?>
                <?php if (($crm_companies ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_contacts'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($contacts ?? []) as $row): ?>
                <li class="list-group-item"><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/contacts') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php endforeach; ?>
                <?php if (($contacts ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_orders_links'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($order_links ?? []) as $link): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <?php if (!empty($link['href'])): ?><a href="<?php echo htmlspecialchars((string) $link['href'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php else: ?><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    <span class="small text-muted"><?php echo htmlspecialchars($link['amount'] . ' ' . $link['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (($order_links ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('invoices'), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars(__('payments'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($invoice_links ?? []) as $link): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <?php if (!empty($link['href'])): ?><a href="<?php echo htmlspecialchars((string) $link['href'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php else: ?><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    <span class="small text-muted"><?php echo htmlspecialchars($link['amount'] . ' ' . $link['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <?php endforeach; ?>
                <?php foreach (($payment_links ?? []) as $link): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <?php if (!empty($link['href'])): ?><a href="<?php echo htmlspecialchars((string) $link['href'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php else: ?><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    <span class="small text-muted"><?php echo htmlspecialchars($link['amount'] . ' ' . $link['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (($invoice_links ?? []) === [] && ($payment_links ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_revenue_tracked'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($revenue_events ?? []) as $row): ?>
                <li class="list-group-item">
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) (($row['amount'] ?? '') . ' ' . ($row['currency_code'] ?? '') . ' · ' . ($row['period_key'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($revenue_events ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_opportunities'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($opportunities ?? []) as $row): ?>
                <li class="list-group-item">
                    <a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['opportunity_no'] ?? $row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · ER <?php echo htmlspecialchars((string) ($row['expected_revenue'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($opportunities ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_quotations'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($quotations ?? []) as $row): ?>
                <li class="list-group-item">
                    <a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['quotation_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($row['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($quotations ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_activities'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($activities ?? []) as $row): ?>
                <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) (($row['activity_type'] ?? '') . ' · ' . ($row['priority'] ?? '') . ' · ' . ($row['due_at'] ?? $row['activity_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
                <?php foreach (($tasks ?? []) as $row): ?>
                <li class="list-group-item"><div class="fw-semibold">Task: <?php echo htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) (($row['priority'] ?? '') . ' · ' . ($row['due_at'] ?? '') . ' · ' . ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
                <?php if (($activities ?? []) === [] && ($tasks ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($timeline ?? []) as $ev): ?>
                <li class="list-group-item">
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) (($ev['event_type'] ?? '') . ' · ' . ($ev['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
