<?php

use Rateb\App\Core\View;

/** @var array<string, mixed> $connection */
/** @var array<string, mixed> $company */
/** @var array<string, bool> $prerequisites */
/** @var array<int, string> $environments */
$connection = $connection ?? [];
$company = $company ?? [];
$prerequisites = $prerequisites ?? [];
$environments = $environments ?? ['developer', 'simulation', 'production'];
$canManage = $canManage ?? false;
$serialTemplate = (string) ($serialTemplate ?? '');
$selectedCompanyId = (int) ($selectedCompanyId ?? 0);
$portalUrl = (string) ($portalUrl ?? 'https://fatoora.zatca.gov.sa/');
$portalLink = '<a class="zatca-portal-link" href="' . View::escape($portalUrl) . '" target="_blank" rel="noopener noreferrer">'
    . __('zatca_fatoora_portal')
    . ' <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>';

$status = (string) ($connection['status'] ?? 'not_linked');
$environment = (string) ($connection['environment'] ?? 'developer');
$isLinked = $status === 'linked';
$isProduction = $environment === 'production';
$linkedAt = (string) ($connection['linked_at'] ?? '');

$statusTone = $isLinked ? 'is-linked' : ($status === 'failed' ? 'is-failed' : 'is-idle');
$statusIcon = $isLinked ? 'fa-circle-check' : ($status === 'failed' ? 'fa-circle-xmark' : 'fa-circle-info');

View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<link rel="stylesheet" href="<?php echo View::escape(rateb_asset('css/zatca-onboarding.css')); ?>">

<div class="rateb-card mb-4 zatca-hero">
    <div class="rateb-card-body d-flex align-items-center gap-3">
        <span class="zatca-hero-icon"><i class="fas fa-link" aria-hidden="true"></i></span>
        <div>
            <h1 class="h4 mb-1"><?php echo __('zatca_onboarding'); ?></h1>
            <p class="text-muted mb-0"><?php echo __('zatca_onboarding_subtitle'); ?></p>
        </div>
    </div>
</div>

<?php View::partial('ops-company-select', ['selectedCompanyId' => $selectedCompanyId]); ?>

<?php if ($selectedCompanyId < 1) { return; } ?>

<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('zatca_link_info'); ?></div>
    <div class="rateb-card-body">
        <div class="zatca-status <?php echo $statusTone; ?>">
            <div class="zatca-status-title">
                <i class="fas <?php echo $statusIcon; ?>" aria-hidden="true"></i>
                <span><?php echo __('zatca_link_status'); ?></span>
            </div>
            <div class="zatca-status-value"><?php echo __('zatca_status_' . $status); ?></div>
            <div class="zatca-status-meta">
                <span class="badge zatca-env-badge"><?php echo __('zatca_current_environment'); ?>: <?php echo __('zatca_env_' . $environment); ?></span>
                <?php if ($linkedAt !== '') { ?>
                <span class="zatca-status-date"><?php echo __('zatca_linked_at'); ?>: <?php echo View::escape($linkedAt); ?></span>
                <?php } ?>
            </div>
            <?php if ($status === 'failed' && !empty($connection['last_error'])) { ?>
            <div class="zatca-status-error"><?php echo View::escape((string) $connection['last_error']); ?></div>
            <?php } ?>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="rateb-card h-100 mb-0">
                    <div class="rateb-card-header"><?php echo __('zatca_company_data'); ?></div>
                    <div class="rateb-card-body p-0">
                        <table class="zatca-kv">
                            <tbody>
                            <tr>
                                <th><?php echo __('company_name'); ?></th>
                                <td><?php echo View::escape((string) ($company['name'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo __('vat_number'); ?></th>
                                <td class="<?php echo !empty($company['vat_valid']) ? 'zatca-ok' : 'zatca-warn'; ?>">
                                    <?php echo View::escape((string) ($company['vat_number'] ?? '')); ?>
                                    <i class="fas <?php echo !empty($company['vat_valid']) ? 'fa-check' : 'fa-triangle-exclamation'; ?>" aria-hidden="true"></i>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo __('email'); ?></th>
                                <td><?php echo View::escape((string) ($company['email'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo __('address'); ?></th>
                                <td><?php echo View::escape((string) ($company['address'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo __('city'); ?></th>
                                <td><?php echo View::escape((string) ($company['city'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo __('postal_code'); ?></th>
                                <td><?php echo View::escape((string) ($company['postal_code'] ?? '')); ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="rateb-card-footer">
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_app_url('accounting/zatca-settings'); ?>">
                            <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                            <span><?php echo __('zatca_edit_company_data'); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <form method="post"
                      action="<?php echo rateb_app_url('accounting/zatca-onboarding/link'); ?>"
                      class="rateb-card h-100 mb-0"
                      id="zatcaOnboardingForm"
                      data-env-url="<?php echo rateb_app_url('accounting/zatca-onboarding/environment'); ?>"
                      data-serial-url="<?php echo rateb_app_url('accounting/zatca-onboarding/serial'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo View::escape((string) ($csrf ?? '')); ?>">
                    <div class="rateb-card-header"><?php echo __('zatca_link_settings'); ?></div>
                    <div class="rateb-card-body">
                        <div class="mb-3">
                            <label class="form-label" for="zatcaEnvironment">
                                <?php echo __('zatca_environment'); ?> <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="zatcaEnvironment" name="environment" data-zatca-environment <?php echo $canManage ? '' : 'disabled'; ?>>
                                <?php foreach ($environments as $env) { ?>
                                <option value="<?php echo View::escape($env); ?>" <?php echo $env === $environment ? 'selected' : ''; ?>>
                                    <?php echo __('zatca_env_' . $env); ?>
                                </option>
                                <?php } ?>
                            </select>
                            <div class="form-text"><i class="fas fa-circle-info" aria-hidden="true"></i> <?php echo __('zatca_environment_hint'); ?></div>
                        </div>

                        <div class="zatca-note zatca-note-info<?php echo $isProduction ? ' d-none' : ''; ?>" data-zatca-note="sandbox">
                            <i class="fas fa-flask" aria-hidden="true"></i>
                            <span><?php echo __('zatca_sandbox_note'); ?></span>
                        </div>
                        <div class="zatca-note zatca-note-danger<?php echo $isProduction ? '' : ' d-none'; ?>" data-zatca-note="production">
                            <i class="fas fa-tower-broadcast" aria-hidden="true"></i>
                            <span><?php echo __('zatca_production_note'); ?></span>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label" for="zatcaEgsSerial">
                                <?php echo __('zatca_egs_serial'); ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <button type="button" class="btn btn-dark" data-zatca-generate-serial <?php echo $canManage ? '' : 'disabled'; ?>>
                                    <i class="fas fa-rotate" aria-hidden="true"></i>
                                    <span><?php echo __('generate'); ?></span>
                                </button>
                                <input type="text" class="form-control" id="zatcaEgsSerial" name="egs_serial" dir="ltr"
                                       value="<?php echo View::escape((string) ($connection['egs_serial'] ?? '')); ?>"
                                       <?php echo $canManage ? '' : 'readonly'; ?>>
                            </div>
                            <div class="form-text" dir="ltr"><?php echo View::escape($serialTemplate); ?></div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label" for="zatcaOtp">
                                <?php echo __('zatca_otp'); ?> <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="zatcaOtp" name="otp" dir="ltr"
                                   inputmode="numeric" autocomplete="one-time-code" maxlength="12"
                                   placeholder="123456" <?php echo $canManage ? '' : 'disabled'; ?>>
                            <div class="form-text"><?php echo __('zatca_otp_hint') . ' ' . $portalLink; ?></div>
                        </div>
                    </div>
                    <div class="rateb-card-footer d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" <?php echo $canManage ? '' : 'disabled'; ?>>
                            <i class="fas fa-link" aria-hidden="true"></i>
                            <span><?php echo __('zatca_start_link'); ?></span>
                        </button>
                    </div>
                </form>
                <?php if ($canManage && $isLinked) { ?>
                <form method="post" action="<?php echo rateb_app_url('accounting/zatca-onboarding/unlink'); ?>" class="mt-2">
                    <input type="hidden" name="_csrf" value="<?php echo View::escape((string) ($csrf ?? '')); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                        <i class="fas fa-link-slash" aria-hidden="true"></i>
                        <span><?php echo __('zatca_unlink'); ?></span>
                    </button>
                </form>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="rateb-card mb-4 zatca-certs">
    <div class="rateb-card-header"><?php echo __('zatca_certificates'); ?></div>
    <div class="rateb-card-body">
        <div class="row g-3">
            <?php
            $certs = [
                ['label' => __('zatca_compliance_certificate'), 'status' => (string) ($connection['compliance_status'] ?? 'none'), 'token' => (string) ($connection['compliance_certificate'] ?? ''), 'icon' => 'fa-certificate'],
                ['label' => __('zatca_production_certificate'), 'status' => (string) ($connection['production_status'] ?? 'none'), 'token' => (string) ($connection['production_certificate'] ?? ''), 'icon' => 'fa-shield-halved'],
            ];
            foreach ($certs as $cert) {
                $active = $cert['status'] === 'active';
                ?>
            <div class="col-lg-6">
                <div class="rateb-card h-100 mb-0">
                    <div class="rateb-card-header">
                        <i class="fas <?php echo $cert['icon']; ?>" aria-hidden="true"></i>
                        <span><?php echo View::escape($cert['label']); ?></span>
                    </div>
                    <div class="rateb-card-body p-0">
                        <table class="zatca-kv">
                            <tbody>
                            <tr>
                                <th><?php echo __('status'); ?></th>
                                <td>
                                    <span class="badge <?php echo $active ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo __($active ? 'zatca_cert_active' : 'zatca_cert_none'); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo __('zatca_certificate'); ?></th>
                                <td class="zatca-token" dir="ltr" title="<?php echo View::escape($cert['token']); ?>">
                                    <?php echo $cert['token'] !== '' ? View::escape(substr($cert['token'], 0, 46)) . '…' : '—'; ?>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('zatca_link_steps'); ?></div>
    <div class="rateb-card-body">
        <ol class="zatca-steps">
            <li class="<?php echo !empty($prerequisites['vat_number']) && !empty($prerequisites['legal_name']) && !empty($prerequisites['address']) ? 'is-done' : ''; ?>">
                <?php echo __('zatca_step_1'); ?>
            </li>
            <li class="<?php echo !empty($prerequisites['egs_serial']) ? 'is-done' : ''; ?>"><?php echo __('zatca_step_2'); ?></li>
            <li><?php echo __('zatca_step_3') . ' ' . $portalLink; ?></li>
            <li><?php echo __('zatca_step_4'); ?></li>
            <li class="<?php echo $isLinked ? 'is-done' : ''; ?>"><?php echo __('zatca_step_5'); ?></li>
        </ol>
        <div class="zatca-note zatca-note-warning mb-0">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span><?php echo __('zatca_otp_warning'); ?></span>
        </div>
    </div>
</div>

<script src="<?php echo View::escape(rateb_asset('js/zatca-onboarding.js')); ?>" defer></script>
