<?php
declare(strict_types=1);
$customer = $customer ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($customer['name'] ?? (__('crm_customer_360') . ' #' . (int) ($customer_id ?? 0))), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($customer['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($customer['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($customer['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="small mt-1">
                <?php echo htmlspecialchars(__('crm_lifecycle'), ENT_QUOTES, 'UTF-8'); ?>:
                <strong><?php echo htmlspecialchars(rateb_enum_label((string) ($customer['crm_lifecycle_stage'] ?? 'customer')), ENT_QUOTES, 'UTF-8'); ?></strong>
                · <?php echo htmlspecialchars(__('crm_activity_score'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int) ($customer['crm_activity_score'] ?? ($health['activity_score'] ?? 0)); ?>
                · <?php echo htmlspecialchars(__('crm_engagement_score'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int) ($customer['crm_engagement_score'] ?? ($health['engagement_score'] ?? 0)); ?>
                · <?php echo htmlspecialchars(__('crm_health_score'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int) ($customer['crm_health_score'] ?? ($health['health_score'] ?? 0)); ?>
                (<?php echo htmlspecialchars(rateb_enum_label((string) ($customer['crm_health_status'] ?? ($health['health_status'] ?? 'unknown'))), ENT_QUOTES, 'UTF-8'); ?>)
                · <?php echo htmlspecialchars(__('crm_renewal_risk'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars(rateb_enum_label((string) ($customer['crm_renewal_risk'] ?? ($health['renewal_risk'] ?? 'low'))), ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($customer['crm_at_risk'])): ?> · <span class="text-danger"><?php echo htmlspecialchars(__('crm_at_risk'), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
            </div>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>

    <?php if (!empty($canLifecycle)): ?>
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/customers') . '/' . (int) ($customer_id ?? 0) . '/lifecycle'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <h2 class="h6"><?php echo htmlspecialchars(__('crm_lifecycle'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <select class="form-select mb-2" name="to_stage" required>
                    <?php foreach (($lifecycle_stages ?? []) as $st): ?>
                    <option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($customer['crm_lifecycle_stage'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars(rateb_enum_label($st), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mb-2" name="reason" placeholder="<?php echo htmlspecialchars(__('reason'), ENT_QUOTES, 'UTF-8'); ?>">
                <button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
        <div class="col-lg-4">
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/customers') . '/' . (int) ($customer_id ?? 0) . '/ownership'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <h2 class="h6"><?php echo htmlspecialchars(__('crm_ownership'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <input class="form-control mb-2" name="owner_user_id" type="number" min="1" placeholder="<?php echo htmlspecialchars(__('crm_owner_user_id'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo (int) ($customer['crm_owner_user_id'] ?? 0) ?: ''; ?>">
                <select class="form-select mb-2" name="team_id">
                    <option value=""><?php echo htmlspecialchars(__('crm_sales_teams'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php foreach (($teams ?? []) as $t): ?>
                    <option value="<?php echo (int) $t['id']; ?>" <?php echo ((int) ($customer['crm_team_id'] ?? 0) === (int) $t['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select mb-2" name="territory_id">
                    <option value=""><?php echo htmlspecialchars(__('crm_territories'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php foreach (($territories ?? []) as $t): ?>
                    <option value="<?php echo (int) $t['id']; ?>" <?php echo ((int) ($customer['crm_territory_id'] ?? 0) === (int) $t['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
        <div class="col-lg-4">
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/customers') . '/' . (int) ($customer_id ?? 0) . '/renewal'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <h2 class="h6"><?php echo htmlspecialchars(__('crm_renewal_tracking'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <input class="form-control mb-2" type="date" name="renewal_due_at" value="<?php echo htmlspecialchars((string) ($customer['crm_renewal_due_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="small text-muted mb-2"><?php echo htmlspecialchars(__('crm_last_interaction'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars((string) ($customer['crm_last_interaction_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_lifecycle_history'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($lifecycle_history ?? []) as $ev): ?>
                <li class="list-group-item">
                    <div class="fw-semibold"><?php echo htmlspecialchars(rateb_enum_label((string) (($ev['from_stage'] ?? '—') . ' → ' . ($ev['to_stage'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars(rateb_log_title((string) ($ev['event_type'] ?? '')) . ' · ' . (string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($lifecycle_history ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_health_history'), ENT_QUOTES, 'UTF-8'); ?>
                <span class="small text-muted">(<?php echo htmlspecialchars((string) (($risk_trends['trend'] ?? 'stable')), ENT_QUOTES, 'UTF-8'); ?>)</span>
            </h2>
            <ul class="list-group mb-3">
                <?php foreach (($health_history ?? []) as $h): ?>
                <li class="list-group-item small">
                    <?php echo (int) ($h['health_score'] ?? 0); ?> · <?php echo htmlspecialchars((string) ($h['health_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars(__('crm_risk'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($h['renewal_risk'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    <div class="text-muted"><?php echo htmlspecialchars((string) ($h['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($health_history ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_engagement_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($engagement_timeline ?? []) as $e): ?>
                <li class="list-group-item small">
                    <?php echo htmlspecialchars((string) (($e['type'] ?? '') . ': ' . ($e['subject'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    <div class="text-muted"><?php echo htmlspecialchars((string) ($e['at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($engagement_timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
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
                    <div class="fw-semibold"><?php echo htmlspecialchars(rateb_log_title((string) ($row['event_type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
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
                    <div class="small text-muted"><?php echo htmlspecialchars(rateb_enum_label((string) ($row['workflow_status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(__('crm_expected_rev_short'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($row['expected_revenue'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($opportunities ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_quotations'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($quotations ?? []) as $row): ?>
                <li class="list-group-item">
                    <a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['quotation_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                    <div class="small text-muted"><?php echo htmlspecialchars(rateb_enum_label((string) ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($row['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($quotations ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_activities'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($activities ?? []) as $row): ?>
                <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars(rateb_enum_label((string) ($row['activity_type'] ?? '')) . ' · ' . rateb_enum_label((string) ($row['priority'] ?? '')) . ' · ' . (string) ($row['due_at'] ?? $row['activity_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
                <?php foreach (($tasks ?? []) as $row): ?>
                <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars(__('crm_task') . ': ' . (string) ($row['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars(rateb_enum_label((string) ($row['priority'] ?? '')) . ' · ' . (string) ($row['due_at'] ?? '') . ' · ' . rateb_enum_label((string) ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
                <?php if (($activities ?? []) === [] && ($tasks ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('crm_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($timeline ?? []) as $ev): ?>
                <li class="list-group-item">
                    <div class="fw-semibold"><?php echo htmlspecialchars(rateb_log_title((string) ($ev['title'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars(rateb_log_title((string) ($ev['event_type'] ?? '')) . ' · ' . (string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
