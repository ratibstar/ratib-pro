<?php
declare(strict_types=1);

/** @var string $formApp all|hr|erp|customer */
$formApp = \Rateb\App\Services\AgentAppsOpsService::normalizeTargetApp((string) ($formApp ?? 'all'));
?>
<select name="target_app" class="form-select" required>
    <?php foreach (\Rateb\App\Services\AgentAppsOpsService::targetApps() as $ta) { ?>
    <option value="<?php echo Rateb\App\Core\View::escape($ta); ?>"<?php echo $formApp === $ta ? ' selected' : ''; ?>>
        <?php echo Rateb\App\Core\View::escape(__('mobile_apps_target_' . $ta)); ?>
    </option>
    <?php } ?>
</select>
