<?php
/** @var array<string, mixed> $lead */
/** @var array<int, array<string, mixed>> $notes */
$typeKey = match ((string) ($lead['lead_type'] ?? '')) {
    'demo' => 'cms_lead_type_demo',
    'quote' => 'cms_lead_type_quote',
    'contact' => 'cms_lead_type_contact',
    default => '',
};
$typeLabel = $typeKey !== '' ? __($typeKey) : (string) ($lead['lead_type'] ?? '');
$defaultReplySubject = __('cms_lead_reply_subject_default', ['type' => $typeLabel]);
$isNew = ($lead['status'] ?? '') === 'new';
$mailReady = !empty($mailReady);
$mailLocalhost = !empty($mailLocalhost);
$mailDiag = is_array($mailDiag ?? null) ? $mailDiag : [];
$leadReturnPath = 'admin/cms/leads/' . (int) ($lead['id'] ?? 0);
?>
<?php if (!$mailReady) { ?>
<div class="alert alert-warning py-2 mb-3">
    <i class="fas fa-exclamation-triangle me-1"></i><?php echo __('cms_lead_smtp_not_ready'); ?>
    <a href="<?php echo rateb_url('admin/settings'); ?>" class="alert-link ms-1"><?php echo __('cms_lead_smtp_settings_link'); ?></a>
</div>
<?php } elseif ($mailLocalhost) { ?>
<div class="alert alert-danger py-2 mb-3">
    <i class="fas fa-server me-1"></i><?php echo __('cms_lead_smtp_localhost_warn'); ?>
    <a href="<?php echo rateb_url('admin/settings'); ?>" class="alert-link ms-1"><?php echo __('cms_lead_smtp_settings_link'); ?></a>
</div>
<?php } ?>
<?php if ($mailDiag !== []) { ?>
<div class="alert alert-secondary py-2 mb-3 small">
    <strong><?php echo __('cms_lead_smtp_status'); ?>:</strong>
    <?php echo __('mail_smtp_host'); ?> <code dir="ltr"><?php echo Rateb\App\Core\View::escape((string) ($mailDiag['host'] ?? '')); ?></code>
    · <?php echo __('mail_smtp_port'); ?> <code dir="ltr"><?php echo (int) ($mailDiag['port'] ?? 0); ?></code>
    · <?php echo !empty($mailDiag['ready']) ? __('mail_settings_ready') : __('mail_settings_incomplete'); ?>
</div>
<?php } ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="rateb-card">
            <div class="rateb-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span><?php echo __('cms_leads'); ?> #<?php echo (int) $lead['id']; ?></span>
                <?php if ($isNew) { ?>
                <span class="badge bg-danger"><?php echo __('new'); ?></span>
                <?php } ?>
            </div>
            <div class="rateb-card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><strong><?php echo __('name'); ?>:</strong><br><?php echo Rateb\App\Core\View::escape($lead['name']); ?></div>
                    <div class="col-md-6"><strong><?php echo __('email'); ?>:</strong><br><a href="mailto:<?php echo Rateb\App\Core\View::escape($lead['email']); ?>"><?php echo Rateb\App\Core\View::escape($lead['email']); ?></a></div>
                    <div class="col-md-6"><strong><?php echo __('phone'); ?>:</strong><br><?php echo ($lead['phone'] ?? '') !== '' ? rateb_phone_markup((string) $lead['phone']) : '—'; ?></div>
                    <div class="col-md-6"><strong><?php echo __('company'); ?>:</strong><br><?php echo Rateb\App\Core\View::escape((string) ($lead['company'] ?? '—')); ?></div>
                    <div class="col-md-6"><strong><?php echo __('lead_type'); ?>:</strong><br><span class="badge bg-secondary"><?php echo Rateb\App\Core\View::escape($typeLabel); ?></span></div>
                    <div class="col-md-6"><strong><?php echo __('created_at'); ?>:</strong><br><?php echo Rateb\App\Core\View::formatDate($lead['created_at'] ?? ''); ?></div>
                </div>
                <div class="p-3 rounded border bg-body-secondary mb-0">
                    <strong class="d-block mb-2"><?php echo __('message'); ?></strong>
                    <?php echo nl2br(Rateb\App\Core\View::escape((string) ($lead['message'] ?? ''))); ?>
                </div>
            </div>
        </div>

        <?php if (!empty($notes)) { ?>
        <div class="rateb-card mt-3">
            <div class="rateb-card-header"><?php echo __('cms_lead_notes'); ?></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($notes as $n) { ?>
                <li class="list-group-item">
                    <div class="small text-muted mb-1"><?php echo Rateb\App\Core\View::formatDate($n['created_at'] ?? ''); ?></div>
                    <div><?php echo nl2br(Rateb\App\Core\View::escape((string) ($n['note'] ?? ''))); ?></div>
                </li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
    </div>

    <div class="col-lg-5">
        <div class="rateb-card border-primary">
            <div class="rateb-card-header text-primary"><i class="fas fa-reply me-1"></i><?php echo __('cms_lead_reply_title'); ?></div>
            <div class="rateb-card-body">
                <form method="post" action="<?php echo rateb_url('admin/cms/leads/' . (int) $lead['id']); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="reply_subject"><?php echo __('subject'); ?></label>
                        <input type="text" name="reply_subject" id="reply_subject" class="form-control" value="<?php echo Rateb\App\Core\View::escape($defaultReplySubject); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reply_message"><?php echo __('cms_lead_reply_body'); ?></label>
                        <textarea name="reply_message" id="reply_message" class="form-control" rows="6" placeholder="<?php echo Rateb\App\Core\View::escape(__('cms_lead_reply_placeholder')); ?>"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-1"></i><?php echo __('cms_lead_reply_send'); ?></button>
                    <p class="small text-muted mt-2 mb-0"><?php echo __('cms_lead_reply_hint'); ?></p>
                </form>
                <?php if ($mailReady && trim((string) ($lead['email'] ?? '')) !== '') { ?>
                <form method="post" action="<?php echo rateb_url('admin/cms/leads/' . (int) $lead['id'] . '/test-mail'); ?>" class="border-top pt-2 mt-2">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-vial me-1"></i><?php echo __('cms_lead_smtp_test', ['email' => (string) ($lead['email'] ?? '')]); ?>
                    </button>
                </form>
                <?php } ?>
            </div>
        </div>

        <div class="rateb-card mt-3">
            <div class="rateb-card-header"><?php echo __('cms_lead_manage'); ?></div>
            <div class="rateb-card-body">
                <form method="post" action="<?php echo rateb_url('admin/cms/leads/' . (int) $lead['id']); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="lead_status"><?php echo __('status'); ?></label>
                        <select name="status" id="lead_status" class="form-select">
                            <?php foreach (['new', 'contacted', 'qualified', 'won', 'lost'] as $st) { ?>
                            <option value="<?php echo $st; ?>"<?php echo ($lead['status'] ?? '') === $st ? ' selected' : ''; ?>><?php echo __($st); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="lead_note"><?php echo __('cms_lead_note'); ?></label>
                        <textarea name="note" id="lead_note" class="form-control" rows="3" placeholder="<?php echo Rateb\App\Core\View::escape(__('cms_lead_note_internal')); ?>"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary"><?php echo __('save'); ?></button>
                        <a href="<?php echo rateb_url('admin/cms/leads'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
