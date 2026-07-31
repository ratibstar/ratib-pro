<?php
declare(strict_types=1);

/** @var string $sectionKey */
/** @var array{title:string,icon:string,tone:string,desc:string} $sectionMeta */
/** @var string $listKind */
/** @var list<array<string,mixed>> $rows */
/** @var int $total */
$rows = $rows ?? [];
$total = (int) ($total ?? 0);
$sectionMeta = $sectionMeta ?? ['title' => 'agent_apps_section', 'icon' => 'fa-cube', 'tone' => 'blue', 'desc' => ''];
$listKind = (string) ($listKind ?? 'complaints');
$tone = (string) ($sectionMeta['tone'] ?? 'blue');
$filterStatus = (string) ($filterStatus ?? '');
$filterType = (string) ($filterType ?? '');
$pending = (int) ($pending ?? 0);
$avgLabel = (string) ($avgLabel ?? '0');
$canManage = !empty($canManage);
$csrf = (string) ($csrf ?? '');
$actionUrl = (string) ($actionUrl ?? rateb_url('admin/agent-apps/complaints/action'));
?>
<div class="raa" data-raa="list">
    <header class="raa-hero raa-hero--compact">
        <div class="raa-hero__copy">
            <p class="raa-hero__eyebrow"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></p>
            <h1 class="raa-hero__title">
                <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($sectionMeta['icon'] ?? 'fa-cube')); ?>"></i>
                <?php echo Rateb\App\Core\View::escape(__((string) $sectionMeta['title'])); ?>
            </h1>
            <p class="raa-hero__lead"><?php echo Rateb\App\Core\View::escape(__((string) ($sectionMeta['desc'] ?? ''))); ?></p>
        </div>
        <a class="raa-hero__cta raa-hero__cta--ghost" href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-soft-nav="1">
            <i class="fas fa-arrow-right"></i>
            <?php echo Rateb\App\Core\View::escape(__('agent_apps_back_dashboard')); ?>
        </a>
    </header>

    <?php if ($listKind === 'complaints') { ?>
    <form method="get" class="rateb-card mb-3 p-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small"><?php echo Rateb\App\Core\View::escape(__('status')); ?></label>
                <select name="status" class="form-select form-select-sm">
                    <option value=""><?php echo Rateb\App\Core\View::escape(__('all')); ?></option>
                    <?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $st) { ?>
                    <option value="<?php echo $st; ?>"<?php echo $filterStatus === $st ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__($st)); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small"><?php echo Rateb\App\Core\View::escape(__('type')); ?></label>
                <select name="type" class="form-select form-select-sm">
                    <option value=""><?php echo Rateb\App\Core\View::escape(__('all')); ?></option>
                    <option value="complaint"<?php echo $filterType === 'complaint' ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__('agent_apps_type_complaint')); ?></option>
                    <option value="inquiry"<?php echo $filterType === 'inquiry' ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__('agent_apps_type_inquiry')); ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary"><?php echo Rateb\App\Core\View::escape(__('filter')); ?></button>
            </div>
            <div class="col-md-3 text-md-end small text-muted">
                <?php echo Rateb\App\Core\View::escape(__('agent_apps_stat_complaints')); ?>:
                <strong class="rateb-ltr-num"><?php echo (int) $pending; ?></strong>
                · <?php echo Rateb\App\Core\View::escape(__('total')); ?>:
                <strong class="rateb-ltr-num"><?php echo (int) $total; ?></strong>
            </div>
        </div>
    </form>
    <?php } elseif ($listKind === 'requests') { ?>
    <form method="get" class="rateb-card mb-3 p-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small"><?php echo Rateb\App\Core\View::escape(__('status')); ?></label>
                <select name="status" class="form-select form-select-sm">
                    <option value=""><?php echo Rateb\App\Core\View::escape(__('all')); ?></option>
                    <?php foreach (['submitted', 'booked', 'paid', 'in_progress', 'completed', 'cancelled'] as $st) { ?>
                    <option value="<?php echo $st; ?>"<?php echo $filterStatus === $st ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__($st)); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary"><?php echo Rateb\App\Core\View::escape(__('filter')); ?></button>
            </div>
            <div class="col-md-5 text-md-end small text-muted">
                <?php echo Rateb\App\Core\View::escape(__('total')); ?>:
                <strong class="rateb-ltr-num"><?php echo (int) $total; ?></strong>
            </div>
        </div>
    </form>
    <?php } elseif ($listKind === 'ratings') { ?>
    <p class="small text-muted mb-2">
        <?php echo Rateb\App\Core\View::escape(__('agent_apps_stat_rating')); ?>:
        <strong class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($avgLabel); ?></strong>
        · <?php echo Rateb\App\Core\View::escape(__('total')); ?>:
        <strong class="rateb-ltr-num"><?php echo (int) $total; ?></strong>
    </p>
    <?php } else { ?>
    <p class="small text-muted mb-2">
        <?php echo Rateb\App\Core\View::escape(__('total')); ?>:
        <strong class="rateb-ltr-num"><?php echo (int) $total; ?></strong>
    </p>
    <?php } ?>

    <div class="rateb-card" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
        <div class="rateb-card-body table-responsive p-0">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <?php if ($listKind === 'complaints') { ?>
                <tr>
                    <th>#</th>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('hr_employees')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('type')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('date')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('notes')); ?></th>
                    <?php if ($canManage) { ?><th></th><?php } ?>
                </tr>
                <?php } elseif ($listKind === 'requests') { ?>
                <tr>
                    <th>#</th>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('title')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('type')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_payment_status')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('date')); ?></th>
                </tr>
                <?php } elseif ($listKind === 'ratings') { ?>
                <tr>
                    <th>#</th>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('code')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_stat_rating')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('notes')); ?></th>
                </tr>
                <?php } else { ?>
                <tr>
                    <th>#</th>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('title')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('type')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('date')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                </tr>
                <?php } ?>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                <tr>
                    <td colspan="8" class="text-muted text-center py-4">
                        <?php echo Rateb\App\Core\View::escape(__('agent_apps_list_empty')); ?>
                    </td>
                </tr>
                <?php } ?>
                <?php foreach ($rows as $row) { ?>
                <tr>
                    <?php if ($listKind === 'complaints') {
                        $st = (string) ($row['status'] ?? '');
                        $rid = (int) ($row['id'] ?? 0);
                        ?>
                    <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['request_no'] ?? $row['id'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '—')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['employee_name'] ?? ('#' . (int) ($row['employee_id'] ?? 0)))); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(__('agent_apps_type_' . (string) ($row['request_type'] ?? 'inquiry'))); ?></td>
                    <td><span class="badge text-bg-<?php echo $st === 'pending' ? 'warning' : ($st === 'approved' ? 'success' : 'secondary'); ?>"><?php echo Rateb\App\Core\View::escape(__($st)); ?></span></td>
                    <td class="rateb-ltr-num small"><?php echo Rateb\App\Core\View::escape((string) ($row['request_date'] ?? $row['created_at'] ?? '')); ?></td>
                    <td class="small"><?php echo Rateb\App\Core\View::escape(mb_substr((string) ($row['notes'] ?? ''), 0, 120)); ?></td>
                    <?php if ($canManage) { ?>
                    <td class="text-nowrap">
                        <?php if ($st === 'pending' && $rid > 0) { ?>
                        <form method="post" action="<?php echo Rateb\App\Core\View::escape($actionUrl); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <input type="hidden" name="id" value="<?php echo $rid; ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="return_status" value="<?php echo Rateb\App\Core\View::escape($filterStatus); ?>">
                            <input type="hidden" name="return_type" value="<?php echo Rateb\App\Core\View::escape($filterType); ?>">
                            <button type="submit" class="btn btn-sm btn-success"><?php echo Rateb\App\Core\View::escape(__('approve')); ?></button>
                        </form>
                        <form method="post" action="<?php echo Rateb\App\Core\View::escape($actionUrl); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <input type="hidden" name="id" value="<?php echo $rid; ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="return_status" value="<?php echo Rateb\App\Core\View::escape($filterStatus); ?>">
                            <input type="hidden" name="return_type" value="<?php echo Rateb\App\Core\View::escape($filterType); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Rateb\App\Core\View::escape(__('reject')); ?></button>
                        </form>
                        <?php } ?>
                    </td>
                    <?php } ?>
                    <?php } elseif ($listKind === 'requests') { ?>
                    <td class="rateb-ltr-num"><?php echo (int) ($row['id'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '—')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['title'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(__('agent_apps_svc_' . (string) ($row['service_type'] ?? 'recruitment'))); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(__((string) ($row['status'] ?? ''))); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(__((string) ($row['payment_status'] ?? ''))); ?></td>
                    <td class="rateb-ltr-num small"><?php echo Rateb\App\Core\View::escape((string) ($row['created_at'] ?? '')); ?></td>
                    <?php } elseif ($listKind === 'ratings') { ?>
                    <td class="rateb-ltr-num"><?php echo (int) ($row['id'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '—')); ?></td>
                    <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['code'] ?? '')); ?></td>
                    <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['overall_score'] ?? '—')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['workflow_status'] ?? '')); ?></td>
                    <td class="small"><?php echo Rateb\App\Core\View::escape(mb_substr((string) ($row['summary'] ?? ''), 0, 120)); ?></td>
                    <?php } else { ?>
                    <td class="rateb-ltr-num"><?php echo (int) ($row['id'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '—')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['title'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['type'] ?? '')); ?></td>
                    <td class="rateb-ltr-num small"><?php echo Rateb\App\Core\View::escape((string) ($row['created_at'] ?? '')); ?></td>
                    <td>
                        <?php if (!empty($row['is_read'])) { ?>
                        <span class="badge text-bg-secondary"><?php echo Rateb\App\Core\View::escape(__('agent_apps_notif_read')); ?></span>
                        <?php } else { ?>
                        <span class="badge text-bg-primary"><?php echo Rateb\App\Core\View::escape(__('agent_apps_notif_unread')); ?></span>
                        <?php } ?>
                    </td>
                    <?php } ?>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
