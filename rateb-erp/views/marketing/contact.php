<?php /** @var array<string, mixed>|null $contact */ /** @var array<int, array<string, mixed>> $offices */ use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container"><div class="row g-4">
<div class="col-lg-5">
<?php if ($contact) { ?>
<p><i class="fas fa-envelope"></i> <?php echo Rateb\App\Core\View::escape((string) ($contact['email'] ?? '')); ?></p>
<p><i class="fas fa-phone"></i> <?php echo Rateb\App\Core\View::escape((string) ($contact['phone'] ?? '')); ?></p>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($contact, 'address')); ?></p>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($contact, 'working_hours')); ?></p>
<?php } ?>
<?php if (!empty($offices)) { ?>
<h3 class="h5 mt-4"><?php echo __('cms_offices'); ?></h3>
<?php foreach ($offices as $office) { ?>
<div class="rateb-mkt-office-card mb-3">
<strong><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($office, 'name')); ?></strong>
<p class="mb-1"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($office, 'address')); ?></p>
<?php if (!empty($office['phone'])) { ?><p class="mb-1"><i class="fas fa-phone"></i> <?php echo Rateb\App\Core\View::escape((string) $office['phone']); ?></p><?php } ?>
<?php if (!empty($office['map_url'])) { ?><a href="<?php echo Rateb\App\Core\View::escape((string) $office['map_url']); ?>" target="_blank" rel="noopener"><?php echo __('cms_view_map'); ?></a><?php } ?>
</div>
<?php } ?>
<?php } ?>
</div>
<div class="col-lg-7">
<form method="post" action="<?php echo rateb_url('site/contact'); ?>" class="rateb-mkt-form">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
<div class="mb-3"><label class="form-label"><?php echo __('name'); ?></label><input type="text" name="name" class="form-control" required></div>
<div class="mb-3"><label class="form-label"><?php echo __('email'); ?></label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label class="form-label"><?php echo __('phone'); ?></label><input type="text" name="phone" class="form-control"></div>
<div class="mb-3"><label class="form-label"><?php echo __('company'); ?></label><input type="text" name="company" class="form-control"></div>
<div class="mb-3"><label class="form-label"><?php echo __('message'); ?></label><textarea name="message" class="form-control" rows="4"></textarea></div>
<button type="submit" class="btn btn-primary"><?php echo __('cms_send'); ?></button>
</form>
</div>
</div></div></section>
