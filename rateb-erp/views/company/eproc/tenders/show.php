<?php
declare(strict_types=1);
/** @var array<string, mixed> $item */
/** @var list<array<string, mixed>> $bids */
/** @var list<array<string, mixed>> $timeline */
/** @var list<string> $transitions */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($item['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <span class="badge text-bg-secondary"><?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/tenders')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="border rounded p-3 mb-3">
                <div><?php echo nl2br(htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                <div class="mt-2"><strong><?php echo htmlspecialchars(__('budget'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars(number_format((float) ($item['budget_amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($item['currency_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong><?php echo htmlspecialchars(__('opens_at'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['opens_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong><?php echo htmlspecialchars(__('closes_at'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['closes_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <h2 class="h5"><?php echo htmlspecialchars(__('eproc_bids'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive border rounded mb-3">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(__('profile_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(__('currency'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($bids ?? []) as $bid): ?>
                        <tr>
                            <td><?php echo (int) ($bid['profile_id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float) ($bid['bid_amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($bid['currency_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($bid['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($bids ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($canUpdate)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/tenders') . '/' . (int) $item['id'] . '/bids'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
                <?php if (function_exists('csrf_field')): ?>
                    <?php echo csrf_field(); ?>
                <?php elseif (isset($csrf)): ?>
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <?php else: ?>
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <input type="hidden" name="tender_id" value="<?php echo (int) $item['id']; ?>">
                <h2 class="h6"><?php echo htmlspecialchars(__('eproc_bid_create'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label"><?php echo htmlspecialchars(__('profile_id'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="number" name="profile_id" class="form-control" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="number" step="0.01" name="bid_amount" required class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo htmlspecialchars(__('currency'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="text" name="currency_code" class="form-control" maxlength="3" value="<?php echo htmlspecialchars((string) ($item['currency_code'] ?? 'SAR'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="text" name="notes" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <div class="col-lg-4">
            <?php if (!empty($canUpdate) && ($transitions ?? []) !== []): ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h6"><?php echo htmlspecialchars(__('workflow'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/tenders') . '/' . (int) $item['id'] . '/transition'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (function_exists('csrf_field')): ?>
                        <?php echo csrf_field(); ?>
                    <?php elseif (isset($csrf)): ?>
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="expected_version" value="<?php echo (int) ($item['version'] ?? 1); ?>">
                    <select name="to_status" class="form-select mb-2" required>
                        <?php foreach (($transitions ?? []) as $tr): ?>
                        <option value="<?php echo htmlspecialchars($tr, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tr, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="reason" class="form-control mb-2" placeholder="<?php echo htmlspecialchars(__('reason'), ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
            </div>
            <?php endif; ?>
            <h2 class="h6"><?php echo htmlspecialchars(__('eproc_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group list-group-flush border rounded">
                <?php foreach (($timeline ?? []) as $ev): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
