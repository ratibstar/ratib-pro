<?php
declare(strict_types=1);

/** @var string $cashierLabel */
/** @var bool $hasEnrollment */
?>
<div class="rateb-pos__biometric-gate" data-pos-biometric-gate>
    <div class="rateb-pos__biometric-card">
        <h1><?php echo __('pos_biometric_gate_title'); ?></h1>
        <p><?php echo \Rateb\App\Pos\Support\PosView::escape($cashierLabel); ?></p>
        <?php if (!$hasEnrollment): ?>
        <p class="rateb-pos__hint"><?php echo __('pos_biometric_not_enrolled'); ?></p>
        <div class="rateb-pos__biometric-actions">
            <button type="button" class="rateb-pos__biometric-btn rateb-pos__biometric-btn--register" data-pos-bio-register><?php echo __('pos_biometric_register'); ?></button>
        </div>
        <p class="rateb-pos__hint rateb-pos__hint--subtle"><?php echo __('pos_biometric_register_settings_hint'); ?></p>
        <?php else: ?>
        <div class="rateb-pos__biometric-actions">
            <button type="button" class="rateb-pos__biometric-btn" data-pos-bio-fingerprint><?php echo __('pos_biometric_scan'); ?></button>
            <button type="button" class="rateb-pos__biometric-btn rateb-pos__biometric-btn--face" data-pos-bio-face><?php echo __('pos_biometric_face'); ?></button>
        </div>
        <?php endif; ?>
        <p class="rateb-pos__hint" data-pos-bio-status role="status" aria-live="polite"></p>
    </div>
</div>
