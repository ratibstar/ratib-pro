<?php
/** Compact ops strip — inspired by /home topbar, kept minimal for unified marketing site. */
$contact = $contact ?? null;
$phoneRaw = trim((string) ($contact['phone'] ?? '+966 599863868'));
$phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?: '966599863868';
?>
<div class="rateb-mkt-topbar">
    <div class="container">
        <div class="rateb-mkt-topbar__inner">
            <div class="rateb-mkt-topbar__left">
                <a href="tel:+<?php echo Rateb\App\Core\View::escape($phoneDigits); ?>" class="rateb-mkt-topbar__link" dir="ltr">
                    <i class="fas fa-phone-alt" aria-hidden="true"></i>
                    <span><?php echo Rateb\App\Core\View::escape($phoneRaw); ?></span>
                </a>
                <a href="https://wa.me/<?php echo Rateb\App\Core\View::escape($phoneDigits); ?>" class="rateb-mkt-topbar__link rateb-mkt-topbar__wa" target="_blank" rel="noopener noreferrer">
                    <span class="rateb-mkt-topbar__dot" aria-hidden="true"></span>
                    <?php echo __('cms_topbar_whatsapp'); ?>
                </a>
            </div>
            <div class="rateb-mkt-topbar__right">
                <span class="rateb-mkt-topbar__ops" dir="ltr"><?php echo __('cms_topbar_ops'); ?></span>
            </div>
        </div>
    </div>
</div>
