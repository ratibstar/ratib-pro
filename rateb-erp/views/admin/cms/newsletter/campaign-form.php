<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $segments */
/** @var array<int, string> $audiences */
/** @var array<string, int>|null $campaignStats */
$id = (int) ($item['id'] ?? 0);
$audiences = $audiences ?? ['subscribers'];
$campaignStats = $campaignStats ?? null;
?>
<?php if ($id > 0 && is_array($campaignStats) && ($campaignStats['total'] ?? 0) > 0) { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('cms_campaign_delivery'); ?></div>
    <div class="rateb-card-body d-flex flex-wrap gap-3 small">
        <span><?php echo __('total'); ?>: <strong><?php echo (int) $campaignStats['total']; ?></strong></span>
        <span class="text-secondary"><?php echo __('pending'); ?>: <strong><?php echo (int) $campaignStats['pending'] + (int) $campaignStats['queued']; ?></strong></span>
        <span class="text-success"><?php echo __('cms_campaign_sent_count'); ?>: <strong><?php echo (int) $campaignStats['sent']; ?></strong></span>
        <span class="text-danger"><?php echo __('cms_campaign_failed_count'); ?>: <strong><?php echo (int) $campaignStats['failed']; ?></strong></span>
        <span class="text-warning"><?php echo __('cms_campaign_bounced_count'); ?>: <strong><?php echo (int) $campaignStats['bounced']; ?></strong></span>
        <span class="text-muted"><?php echo __('cms_campaign_unsubscribed_count'); ?>: <strong><?php echo (int) $campaignStats['unsubscribed']; ?></strong></span>
    </div>
</div>
<?php } ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('admin/cms/newsletter/campaign/save'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php if ($id > 0) { ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php } ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('subject_en'); ?></label>
                    <input class="form-control" name="subject_en" value="<?php echo Rateb\App\Core\View::escape((string) ($item['subject_en'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('subject_ar'); ?></label>
                    <input class="form-control" name="subject_ar" value="<?php echo Rateb\App\Core\View::escape((string) ($item['subject_ar'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('cms_campaign_audience'); ?></label>
                    <select class="form-select" name="audience">
                        <?php foreach ($audiences as $aud) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($aud); ?>"<?php echo (($item['audience'] ?? 'subscribers') === $aud) ? ' selected' : ''; ?>>
                            <?php echo __('cms_campaign_audience_' . $aud); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <div class="form-text small"><?php echo __('cms_campaign_audience_hint'); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('cms_segment'); ?></label>
                    <select class="form-select" name="segment_slug">
                        <option value="all"><?php echo __('cms_segment_all'); ?></option>
                        <?php foreach ($segments as $seg) {
                            $slug = (string) ($seg['slug'] ?? '');
                            ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($slug); ?>"<?php echo ($item['segment_slug'] ?? 'general') === $slug ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($slug); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-select" name="status">
                        <?php foreach (['draft', 'scheduled', 'sending', 'sent', 'failed'] as $st) { ?>
                        <option value="<?php echo $st; ?>"<?php echo ($item['status'] ?? 'draft') === $st ? ' selected' : ''; ?>><?php echo __($st); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label"><?php echo __('content_en'); ?></label>
                    <textarea class="form-control rateb-cms-wysiwyg" name="body_html_en" rows="8"><?php echo Rateb\App\Core\View::escape((string) ($item['body_html_en'] ?? '')); ?></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label"><?php echo __('content_ar'); ?></label>
                    <textarea class="form-control rateb-cms-wysiwyg" name="body_html_ar" rows="8"><?php echo Rateb\App\Core\View::escape((string) ($item['body_html_ar'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url('admin/cms/newsletter'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
