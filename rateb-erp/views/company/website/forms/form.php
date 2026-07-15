<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $form */
/** @var list<array<string,mixed>> $fields */
/** @var string $action */
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin" id="websiteFormBuilder"
     data-fields='<?php echo htmlspecialchars(json_encode($fields ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? 'Form'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" id="wbFormEditor">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="fields" id="wbFieldsJson" value="">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><label class="form-label">Slug</label><input class="form-control" name="slug" required value="<?php echo htmlspecialchars((string) ($form['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-3"><label class="form-label">Name EN</label><input class="form-control" name="name_en" value="<?php echo htmlspecialchars((string) ($form['name_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-3"><label class="form-label">Name AR</label><input class="form-control" name="name_ar" value="<?php echo htmlspecialchars((string) ($form['name_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="col-md-3 form-check mt-4"><input class="form-check-input" type="checkbox" name="crm_enabled" value="1" id="crmEn"<?php echo !isset($form['crm_enabled']) || !empty($form['crm_enabled']) ? ' checked' : ''; ?>><label class="form-check-label" for="crmEn">CRM lead routing</label></div>
        </div>
        <div id="wbFieldsList" class="mb-3"></div>
        <button type="button" class="btn btn-outline-secondary" id="wbAddField">+ Field</button>
        <button type="submit" class="btn btn-primary">Save form</button>
    </form>
</div>
<script src="<?php echo htmlspecialchars(rateb_asset('js/website-forms.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
