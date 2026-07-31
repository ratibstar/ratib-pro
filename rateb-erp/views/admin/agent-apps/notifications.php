<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
/** @var list<array{id:int,name:string,email:string}> $users */
/** @var list<array{id:int,name:string}> $companies */
$rows = $rows ?? [];
$users = $users ?? [];
$companies = $companies ?? [];
$canManage = !empty($canManage);
$csrf = (string) ($csrf ?? '');
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$total = (int) ($total ?? 0);
?>
<div class="raa" data-raa="notifications">
    <header class="raa-hero raa-hero--compact">
        <div class="raa-hero__copy">
            <p class="raa-hero__eyebrow"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></p>
            <h1 class="raa-hero__title"><i class="fas fa-bell"></i> <?php echo Rateb\App\Core\View::escape(__('agent_apps_notifications')); ?></h1>
            <p class="raa-hero__lead"><?php echo Rateb\App\Core\View::escape(__('agent_apps_notifications_desc')); ?></p>
        </div>
        <a class="raa-hero__cta raa-hero__cta--ghost" href="<?php echo rateb_url('admin/agent-apps'); ?>">
            <i class="fas fa-arrow-right"></i> <?php echo Rateb\App\Core\View::escape(__('agent_apps_back_dashboard')); ?>
        </a>
    </header>

    <?php if ($canManage) { ?>
    <div class="rateb-card mb-3">
        <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape(__('agent_apps_send_notification')); ?></div>
        <div class="rateb-card-body">
            <form method="post" action="<?php echo Rateb\App\Core\View::escape((string) ($sendUrl ?? '')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('company')); ?></label>
                        <select name="company_id" class="form-select" required id="raa_notif_company">
                            <?php foreach ($companies as $c) { ?>
                            <option value="<?php echo (int) $c['id']; ?>"<?php echo $defaultCompanyId === (int) $c['id'] ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape((string) $c['name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_notif_mode')); ?></label>
                        <select name="mode" class="form-select" id="raa_notif_mode">
                            <option value="broadcast"><?php echo Rateb\App\Core\View::escape(__('agent_apps_notif_broadcast')); ?></option>
                            <option value="user"><?php echo Rateb\App\Core\View::escape(__('agent_apps_notif_user')); ?></option>
                        </select>
                    </div>
                    <div class="col-md-4" id="raa_notif_user_wrap">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('user')); ?></label>
                        <select name="user_id" class="form-select">
                            <option value="0">—</option>
                            <?php foreach ($users as $u) { ?>
                            <option value="<?php echo (int) $u['id']; ?>">
                                <?php echo Rateb\App\Core\View::escape(trim(($u['name'] ?? '') . ' · ' . ($u['email'] ?? ''))); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('title')); ?></label>
                        <input class="form-control" name="title" required maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('type')); ?></label>
                        <select name="type" class="form-select">
                            <?php foreach (['info', 'success', 'warning', 'error'] as $t) { ?>
                            <option value="<?php echo $t; ?>"><?php echo Rateb\App\Core\View::escape(__('agent_apps_notif_type_' . $t)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('agent_apps_notif_message')); ?></label>
                        <textarea class="form-control" name="message" rows="3" required></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?php echo Rateb\App\Core\View::escape(__('agent_apps_send_notification')); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function () {
        var mode = document.getElementById('raa_notif_mode');
        var wrap = document.getElementById('raa_notif_user_wrap');
        var company = document.getElementById('raa_notif_company');
        if (mode && wrap) {
            function sync() { wrap.style.display = mode.value === 'user' ? '' : 'none'; }
            mode.addEventListener('change', sync);
            sync();
        }
        if (company) {
            company.addEventListener('change', function () {
                var base = <?php echo json_encode(rateb_url('admin/agent-apps/notifications'), JSON_UNESCAPED_SLASHES); ?>;
                location.href = base + '?company_id=' + encodeURIComponent(company.value);
            });
        }
    })();
    </script>
    <?php } ?>

    <p class="small text-muted mb-2"><?php echo Rateb\App\Core\View::escape(__('total')); ?>: <strong class="rateb-ltr-num"><?php echo $total; ?></strong></p>
    <div class="rateb-card">
        <div class="rateb-card-body table-responsive p-0">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('title')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('type')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('date')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                <tr><td colspan="6" class="text-muted text-center py-4"><?php echo Rateb\App\Core\View::escape(__('agent_apps_list_empty')); ?></td></tr>
                <?php } ?>
                <?php foreach ($rows as $row) { ?>
                <tr>
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
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
