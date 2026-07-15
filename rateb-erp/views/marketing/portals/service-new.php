<section class="rateb-portal-section rateb-svc-section" data-service-wizard>
    <div class="container">
        <h1><?php echo __('new_service_request') ?: 'New service request'; ?></h1>
        <form class="rateb-portal-form" method="post" action="<?php echo rateb_url('site/customer/services'); ?>" data-require-agreement>
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <?php $pkg = (string) ($prefill_package ?? ''); ?>
            <div class="rateb-portal-form__field">
                <label for="svc_service_type"><?php echo __('service_type') ?: 'Service type'; ?></label>
                <select id="svc_service_type" name="service_type">
                    <?php
                    $types = [
                        'recruitment' => __('service_type_recruitment') ?: 'Recruitment',
                        'domestic_worker' => __('service_type_domestic_worker') ?: 'Domestic Worker',
                        'workforce' => __('service_type_workforce') ?: 'Company Workforce',
                        'package' => __('service_type_package') ?: 'Package',
                        'other' => __('service_type_other') ?: 'Other',
                    ];
                    $sel = (string) ($prefill_type ?? 'recruitment');
                    foreach ($types as $val => $label) {
                        $selected = $sel === $val ? ' selected' : '';
                        echo '<option value="' . Rateb\App\Core\View::escape($val) . '"' . $selected . '>' . Rateb\App\Core\View::escape($label) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="rateb-portal-form__field">
                <label for="svc_package_code"><?php echo __('package') ?: 'Package'; ?></label>
                <select id="svc_package_code" name="package_code">
                    <option value=""><?php echo __('none') ?: 'None'; ?></option>
                    <?php foreach (($packages ?? []) as $code => $p) {
                        $selected = $pkg === (string) $code ? ' selected' : '';
                        echo '<option value="' . Rateb\App\Core\View::escape((string) $code) . '"' . $selected . '>'
                            . Rateb\App\Core\View::escape((string) ($p['label'] ?? $code)) . '</option>';
                    } ?>
                </select>
            </div>
            <div class="rateb-portal-form__field">
                <label for="svc_title"><?php echo __('title') ?: 'Title'; ?></label>
                <input id="svc_title" type="text" name="title" required maxlength="255">
            </div>
            <div class="rateb-portal-form__field">
                <label for="svc_description"><?php echo __('description') ?: 'Description'; ?></label>
                <textarea id="svc_description" name="description" rows="4"></textarea>
            </div>
            <div class="rateb-portal-form__field">
                <label for="svc_phone"><?php echo __('phone') ?: 'Phone'; ?></label>
                <input id="svc_phone" type="text" name="phone" maxlength="40">
            </div>
            <div class="rateb-portal-form__field">
                <label for="svc_priority"><?php echo __('priority') ?: 'Priority'; ?></label>
                <select id="svc_priority" name="priority">
                    <option value="normal"><?php echo __('normal') ?: 'Normal'; ?></option>
                    <option value="low"><?php echo __('low') ?: 'Low'; ?></option>
                    <option value="high"><?php echo __('high') ?: 'High'; ?></option>
                    <option value="urgent"><?php echo __('urgent') ?: 'Urgent'; ?></option>
                </select>
            </div>
            <div class="rateb-portal-form__field">
                <label for="svc_agreement_accepted">
                    <input id="svc_agreement_accepted" type="checkbox" name="agreement_accepted" value="1" required aria-required="true" data-agreement-check>
                    <?php echo __('accept_digital_agreement') ?: 'I accept the digital service agreement'; ?>
                </label>
            </div>
            <button type="submit" class="rateb-portal-btn"><?php echo __('submit') ?: 'Submit'; ?></button>
        </form>
    </div>
</section>
