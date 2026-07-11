<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
        <?php echo \Rateb\App\Core\Csrf::field(); ?>
        <div class="col-md-6">
            <label class="form-label" for="name"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="name" name="name" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="code"><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="code" name="code">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="country_code"><?php echo htmlspecialchars(__('country'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="country_code" name="country_code" maxlength="2">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="email"><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="email" class="form-control" id="email" name="email">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="phone"><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="phone" name="phone">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
    </form>
</div>
