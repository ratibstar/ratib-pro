<?php
declare(strict_types=1);
$teams = $teams ?? [];
$territories = $territories ?? [];
$ownership_rules = $ownership_rules ?? [];
$members_by_team = $members_by_team ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_sales_teams')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/admin')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_admin_config'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_sales_teams'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/teams')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="row g-2">
                    <div class="col-md-4"><input class="form-control" name="code" placeholder="<?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-5"><input class="form-control" name="name" placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                </div>
            </form>
            <?php endif; ?>
            <?php foreach ($teams as $team): ?>
            <div class="border rounded p-3 mb-2">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($team['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    <span class="small text-muted">(<?php echo htmlspecialchars((string) ($team['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) ($team['member_count'] ?? 0); ?>)</span>
                </div>
                <ul class="small mb-2">
                    <?php foreach (($members_by_team[(int) $team['id']] ?? []) as $m): ?>
                    <li><?php echo htmlspecialchars(__('user'), ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int) ($m['user_id'] ?? 0); ?> — <?php echo htmlspecialchars(rateb_ui((string) ($m['role_code'] ?? 'member')), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($canManage)): ?>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/teams') . '/' . (int) $team['id'] . '/members'), ENT_QUOTES, 'UTF-8'); ?>" class="d-flex gap-2">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input class="form-control form-control-sm" name="user_id" type="number" min="1" placeholder="<?php echo htmlspecialchars(__('crm_user_id'), ENT_QUOTES, 'UTF-8'); ?>" required>
                    <input class="form-control form-control-sm" name="role_code" placeholder="<?php echo htmlspecialchars(__('role'), ENT_QUOTES, 'UTF-8'); ?>" value="member">
                    <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('add'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($teams === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>

        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_territories'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/teams/territories')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="row g-2">
                    <div class="col-md-3"><input class="form-control" name="code" placeholder="<?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-4"><input class="form-control" name="name" required placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-3"><input class="form-control" name="region" placeholder="<?php echo htmlspecialchars(__('region'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                </div>
            </form>
            <?php endif; ?>
            <ul class="list-group mb-4">
                <?php foreach ($territories as $t): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?php echo htmlspecialchars((string) (($t['code'] ?? '') . ' — ' . ($t['name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="small text-muted"><?php echo htmlspecialchars((string) ($t['region'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <?php endforeach; ?>
                <?php if ($territories === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>

            <h2 class="h5"><?php echo htmlspecialchars(__('crm_ownership_rules'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/teams/ownership-rules')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="row g-2">
                    <div class="col-md-4"><input class="form-control" name="rule_key" placeholder="<?php echo htmlspecialchars(__('rule_key'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="col-md-4"><input class="form-control" name="name" placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="col-md-2"><input class="form-control" name="owner_user_id" type="number" min="1" placeholder="<?php echo htmlspecialchars(__('crm_owner_user_id'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                </div>
            </form>
            <?php endif; ?>
            <ul class="list-group">
                <?php foreach ($ownership_rules as $r): ?>
                <li class="list-group-item">
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($r['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars(rateb_ui((string) ($r['rule_key'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo htmlspecialchars(rateb_ui((string) ($r['entity_type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo htmlspecialchars(!empty($r['is_enabled']) ? __('on') : __('off'), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if ($ownership_rules === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
