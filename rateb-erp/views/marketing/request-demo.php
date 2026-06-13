<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto">
<form method="post" action="<?php echo rateb_url('site/demo'); ?>" class="rateb-mkt-form">
<input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
<div class="mb-3"><label class="form-label"><?php echo __('name'); ?></label><input type="text" name="name" class="form-control" required></div>
<div class="mb-3"><label class="form-label"><?php echo __('email'); ?></label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label class="form-label"><?php echo __('phone'); ?></label><input type="text" name="phone" class="form-control" required></div>
<div class="mb-3"><label class="form-label"><?php echo __('company'); ?></label><input type="text" name="company" class="form-control" required></div>
<div class="mb-3"><label class="form-label"><?php echo __('message'); ?></label><textarea name="message" class="form-control" rows="4"></textarea></div>
<button type="submit" class="btn btn-primary btn-lg"><?php echo __('cms_request_demo'); ?></button>
</form>
</div></section>
