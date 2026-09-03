<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $item */
$isEdit = is_array($item ?? null);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="col-lg-7">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="mb-3"><label class="form-label"><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="title" required value="<?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label"><?php echo htmlspecialchars(__('contact'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="contact_name" value="<?php echo htmlspecialchars((string) ($item['contact_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-6"><label class="form-label"><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars((string) ($item['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-6"><label class="form-label"><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="phone" value="<?php echo htmlspecialchars((string) ($item['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-6"><label class="form-label"><?php echo htmlspecialchars(__('priority'), ENT_QUOTES, 'UTF-8'); ?></label>
                <select class="form-select" name="priority">
                    <?php foreach (['low','normal','high','urgent'] as $p): ?>
                    <option value="<?php echo $p; ?>" <?php echo (($item['priority'] ?? 'normal') === $p) ? 'selected' : ''; ?>><?php echo htmlspecialchars(rateb_enum_label($p), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <div class="mb-3 mt-3"><label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars((string) ($item['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></div>
        <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/leads')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
    </form>
</div>
