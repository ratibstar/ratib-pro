<?php
declare(strict_types=1);

/**
 * Optional per-company APK (separate package) — not the default unified platform model.
 *
 * @var array<string,mixed> $brand
 * @var array<string,mixed> $appCard
 * @var string $cardApp
 * @var int $cardCid
 * @var string $csrf
 */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
?>
<details class="mb-3 pb-3 border-bottom" id="mobile-branded-advanced">
    <summary class="fw-semibold text-muted" style="cursor:pointer">
        <i class="fas fa-box-archive"></i> <?php echo $e(__('mobile_branded_advanced_title')); ?>
        <?php if (!empty($brand['requested'])) { ?>
            <span class="badge text-bg-warning ms-2"><?php echo $e(__('mobile_branded_advanced_active')); ?></span>
        <?php } ?>
    </summary>
    <div class="mt-3">
        <p class="small text-muted"><?php echo $e(__('mobile_branded_advanced_intro')); ?></p>
        <?php if (!empty($brand['requested'])) { ?>
            <div class="alert alert-warning py-2 small"><?php echo $e(__('mobile_branded_unified_conflict')); ?></div>
        <?php } ?>
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cardCid . '/branded')); ?>" class="d-inline"
                  <?php if ($brand['requested']) { ?>onsubmit="return confirm(<?php echo $e((string) json_encode(__('mobile_branded_cancel_confirm'), JSON_UNESCAPED_UNICODE)); ?>);"<?php } ?>>
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
                <input type="hidden" name="requested" value="<?php echo $brand['requested'] ? '0' : '1'; ?>">
                <?php if ($brand['requested']) { ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo $e(__('mobile_branded_cancel')); ?></button>
                <?php } else { ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $e(__('mobile_branded_request')); ?></button>
                <?php } ?>
            </form>
        </div>
        <?php if (!$brand['requested']) { ?>
            <div class="small text-muted"><?php echo $e(__('mobile_branded_hint')); ?></div>
        <?php } else { ?>
            <div class="d-flex align-items-center gap-3 mb-2">
                <?php if ($brand['icon'] !== '') { ?>
                    <img src="<?php echo $e($brand['icon']); ?>" alt="" width="48" height="48" class="rounded border bg-white" style="object-fit:contain">
                <?php } ?>
                <div class="small">
                    <div class="fw-semibold"><?php echo $e($brand['name']); ?></div>
                    <div class="text-muted font-monospace" dir="ltr"><?php echo $e($brand['package']); ?></div>
                </div>
                <div class="ms-auto">
                    <?php if ($brand['built']) { ?>
                        <span class="badge text-bg-success"><?php echo $e(sprintf(__('mobile_branded_ready'), $brand['version'])); ?></span>
                    <?php } elseif (!empty($brand['queued'])) { ?>
                        <span class="badge text-bg-primary"><?php echo $e(__('mobile_branded_state_queued')); ?></span>
                    <?php } else { ?>
                        <span class="badge text-bg-warning"><?php echo $e(__('mobile_branded_pending')); ?></span>
                    <?php } ?>
                </div>
            </div>
            <?php if (!empty($brand['queued']) && empty($brand['built'])) { ?>
                <div class="alert alert-primary py-2 small mb-2" role="status">
                    <i class="fas fa-list-check"></i> <?php echo $e(__('mobile_branded_build_queued_banner')); ?>
                    <a class="ms-1" href="<?php echo $e(rateb_url('admin/mobile-apps/branded-queue') . '?app=' . $cardApp); ?>"><?php echo $e(__('mobile_branded_queue_link')); ?></a>
                </div>
            <?php } ?>
            <?php if (!empty($brand['needs_build']) || !empty($brand['queued'])) { ?>
            <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cardCid . '/branded-build')); ?>" class="mb-2"
                  data-rateb-branded-build-form>
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
                <button type="submit" class="btn btn-sm btn-success w-100" data-rateb-branded-build-btn>
                    <i class="fas fa-hammer"></i> <?php echo $e(__('mobile_branded_build_btn')); ?>
                </button>
            </form>
            <div class="form-text mb-2"><?php echo $e(!empty($appCard['dispatchEnabled']) ? __('mobile_branded_build_hint_auto') : __('mobile_branded_build_hint_manual')); ?></div>
            <?php if (empty($appCard['dispatchEnabled'])) { ?>
                <a class="btn btn-sm btn-outline-secondary w-100 mb-2" href="https://github.com/ratibstar/ratib-pro/actions/workflows/mobile-branded-build.yml" target="_blank" rel="noopener">
                    <i class="fab fa-github"></i> <?php echo $e(__('mobile_branded_open_github_workflow')); ?>
                </a>
            <?php } ?>
            <?php } ?>
            <details class="small">
                <summary class="text-muted"><?php echo $e(__('mobile_branded_command')); ?></summary>
                <input class="form-control form-control-sm font-monospace mt-1" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($brand['command']); ?>">
            </details>
        <?php } ?>
    </div>
</details>
