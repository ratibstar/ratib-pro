<?php /** @var array<string, mixed> $lead */ ?>
<div class="rateb-card"><div class="rateb-card-header"><?php echo __('cms_leads'); ?> #<?php echo (int)$lead['id']; ?></div>
<div class="rateb-card-body">
<p><strong><?php echo __('name'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($lead['name']); ?></p>
<p><strong><?php echo __('email'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($lead['email']); ?></p>
<p><strong><?php echo __('phone'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string)($lead['phone'] ?? '')); ?></p>
<p><strong><?php echo __('company'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string)($lead['company'] ?? '')); ?></p>
<p><strong><?php echo __('message'); ?>:</strong> <?php echo nl2br(Rateb\App\Core\View::escape((string)($lead['message'] ?? ''))); ?></p>
<form method="post" action="<?php echo rateb_url('admin/cms/leads/' . (int)$lead['id']); ?>">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
<div class="mb-3"><label class="form-label"><?php echo __('status'); ?></label>
<select name="status" class="form-select">
<?php foreach (['new','contacted','qualified','won','lost'] as $st) { ?>
<option value="<?php echo $st; ?>"<?php echo ($lead['status'] ?? '') === $st ? ' selected' : ''; ?>><?php echo __($st); ?></option>
<?php } ?>
</select></div>
<div class="mb-3"><label class="form-label"><?php echo __('cms_lead_note'); ?></label><textarea name="note" class="form-control" rows="3"></textarea></div>
<button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
<a href="<?php echo rateb_url('admin/cms/leads'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
</form>
<?php if (!empty($notes)) { ?>
<hr><h5><?php echo __('cms_lead_notes'); ?></h5>
<ul class="list-group"><?php foreach ($notes as $n) { ?>
<li class="list-group-item"><?php echo Rateb\App\Core\View::escape($n['note']); ?> <small class="text-muted"><?php echo Rateb\App\Core\View::escape($n['created_at']); ?></small></li>
<?php } ?></ul>
<?php } ?>
</div></div>
