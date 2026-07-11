<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $item */
/** @var list<array<string,mixed>> $agencies */
/** @var string $action */
$isEdit = is_array($item);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
        <?php echo \Rateb\App\Core\Csrf::field(); ?>
        <div class="col-md-6">
            <label class="form-label" for="full_name"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="full_name" name="full_name" required
                   value="<?php echo htmlspecialchars((string) ($item['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="full_name_ar"><?php echo htmlspecialchars(__('name_ar'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="full_name_ar" name="full_name_ar"
                   value="<?php echo htmlspecialchars((string) ($item['full_name_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="email"><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?php echo htmlspecialchars((string) ($item['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="phone"><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="phone" name="phone"
                   value="<?php echo htmlspecialchars((string) ($item['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="nationality"><?php echo htmlspecialchars(__('nationality'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="nationality" name="nationality" maxlength="2"
                   value="<?php echo htmlspecialchars((string) ($item['nationality'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="job_title_target"><?php echo htmlspecialchars(__('job_title'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" id="job_title_target" name="job_title_target"
                   value="<?php echo htmlspecialchars((string) ($item['job_title_target'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="agency_id"><?php echo htmlspecialchars(__('recruitment_agencies'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select class="form-select" id="agency_id" name="agency_id">
                <option value="">—</option>
                <?php foreach ($agencies as $ag): ?>
                    <option value="<?php echo (int) $ag['id']; ?>" <?php echo ((int) ($item['agency_id'] ?? 0) === (int) $ag['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) ($ag['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="notes"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars((string) ($item['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates')), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
    </form>
</div>
