<?php
declare(strict_types=1);
/** @var array{pipeline:?array, stages:list, opportunities:list} $board */
$stages = $board['stages'] ?? [];
$opps = $board['opportunities'] ?? [];
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
    <?php if (empty($board['pipeline'])): ?>
        <p class="text-muted"><?php echo htmlspecialchars(__('crm_pipeline_empty'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php else: ?>
        <p class="text-muted"><?php echo htmlspecialchars((string) ($board['pipeline']['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="row g-3 flex-nowrap overflow-auto pb-2">
            <?php foreach ($stages as $stage): ?>
            <div class="col-10 col-md-4 col-xl-3">
                <div class="border rounded h-100">
                    <div class="px-3 py-2 border-bottom fw-semibold"><?php echo htmlspecialchars((string) ($stage['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="p-2">
                        <?php foreach ($opps as $opp): if ((int) ($opp['stage_id'] ?? 0) !== (int) $stage['id']) continue; ?>
                            <div class="border rounded p-2 mb-2 bg-white">
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($opp['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($opp['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
