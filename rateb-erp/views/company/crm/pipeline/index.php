<?php
declare(strict_types=1);
/** @var array{pipeline:?array, stages:list, opportunities:list} $board */
$stages = $board['stages'] ?? [];
$opps = $board['opportunities'] ?? [];
$forecast = $forecast ?? null;
$lossReasons = $lossReasons ?? [];
$pipelineId = (int) (($board['pipeline']['id'] ?? 0));
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_pipeline')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canManage)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/pipeline')), ENT_QUOTES, 'UTF-8'); ?>" class="d-flex gap-2">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input class="form-control" name="name" placeholder="<?php echo htmlspecialchars(__('crm_pipeline_name'), ENT_QUOTES, 'UTF-8'); ?>" required>
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($canForecast) && is_array($forecast)): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('crm_kpi_pipeline_value'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5 fw-semibold"><?php echo htmlspecialchars(number_format((float) ($forecast['total_amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('crm_expected_revenue'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5 fw-semibold"><?php echo htmlspecialchars(number_format((float) ($forecast['total_expected_revenue'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('crm_pipeline_health'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5 fw-semibold"><?php echo (int) (($health['score'] ?? 0)); ?> (<?php echo htmlspecialchars((string) ($health['grade'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>)</div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('crm_bottlenecks'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5 fw-semibold"><?php echo (int) (($health['bottleneck_count'] ?? count($bottlenecks ?? []))); ?></div></div></div>
    </div>
    <?php endif; ?>

    <?php if (empty($board['pipeline'])): ?>
        <p class="text-muted"><?php echo htmlspecialchars(__('crm_pipeline_empty'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php else: ?>
        <p class="text-muted"><?php echo htmlspecialchars((string) ($board['pipeline']['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="row g-3 flex-nowrap overflow-auto pb-2 mb-4">
            <?php foreach ($stages as $stage): ?>
            <div class="col-10 col-md-4 col-xl-3">
                <div class="border rounded h-100">
                    <div class="px-3 py-2 border-bottom">
                        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($stage['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($stage['probability_percent'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%<?php if (!empty($stage['expected_duration_days'])): ?> · <?php echo (int) $stage['expected_duration_days']; ?>d<?php endif; ?></div>
                    </div>
                    <div class="p-2">
                        <?php foreach ($opps as $opp): if ((int) ($opp['stage_id'] ?? 0) !== (int) $stage['id']) continue; ?>
                            <div class="border rounded p-2 mb-2 bg-white">
                                <div class="fw-semibold"><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $opp['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($opp['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></div>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($opp['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?> · ER <?php echo htmlspecialchars((string) ($opp['expected_revenue'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if (!empty($canManage)): ?>
                                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $opp['id'] . '/move-stage'), ENT_QUOTES, 'UTF-8'); ?>" class="mt-2">
                                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <select class="form-select form-select-sm mb-1" name="stage_id" required>
                                        <?php foreach ($stages as $st): ?>
                                        <option value="<?php echo (int) $st['id']; ?>" <?php echo ((int) $st['id'] === (int) ($opp['stage_id'] ?? 0)) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($st['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($lossReasons !== []): ?>
                                    <select class="form-select form-select-sm mb-1" name="loss_reason_id">
                                        <option value=""><?php echo htmlspecialchars(__('crm_loss_reason'), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php foreach ($lossReasons as $lr): ?>
                                        <option value="<?php echo (int) $lr['id']; ?>"><?php echo htmlspecialchars((string) ($lr['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary w-100" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($canManage) && $pipelineId > 0): ?>
        <div class="row g-3">
            <div class="col-lg-6">
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/pipeline/stages')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="pipeline_id" value="<?php echo $pipelineId; ?>">
                    <h2 class="h6"><?php echo htmlspecialchars(__('crm_pipeline_stage_manage'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <div class="row g-2">
                        <div class="col-md-4"><input class="form-control" name="name" placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-md-2"><input class="form-control" name="probability_percent" type="number" min="0" max="100" step="1" placeholder="%" value="20"></div>
                        <div class="col-md-3"><input class="form-control" name="expected_duration_days" type="number" min="1" placeholder="<?php echo htmlspecialchars(__('crm_stage_duration_days'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="sort_order" type="number" placeholder="#" value="0"></div>
                        <div class="col-md-6 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_won" value="1" id="is_won"><label class="form-check-label" for="is_won">won</label></div>
                        <div class="col-md-6 form-check"><input class="form-check-input" type="checkbox" name="is_lost" value="1" id="is_lost"><label class="form-check-label" for="is_lost">lost</label></div>
                        <div class="col-12"><button class="btn btn-outline-primary btn-sm" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                    </div>
                </form>
            </div>
            <div class="col-lg-6">
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/pipeline/loss-reasons')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <h2 class="h6"><?php echo htmlspecialchars(__('crm_loss_reason'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <div class="input-group">
                        <input class="form-control" name="name" required placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn btn-outline-secondary" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button>
                    </div>
                    <ul class="list-group list-group-flush mt-2">
                        <?php foreach ($lossReasons as $lr): ?>
                        <li class="list-group-item px-0"><?php echo htmlspecialchars((string) ($lr['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
