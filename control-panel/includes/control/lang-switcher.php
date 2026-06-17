<?php
/**
 * Language switcher — control panel only.
 */
$cpCurrent = function_exists('cp_locale') ? cp_locale() : 'en';
$cpEnUrl = function_exists('cp_lang_switch_url') ? cp_lang_switch_url('en') : '?lang=en';
$cpArUrl = function_exists('cp_lang_switch_url') ? cp_lang_switch_url('ar') : '?lang=ar';
?>
<div class="cp-lang-switcher" role="group" aria-label="<?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('lang.switch') : 'Language', ENT_QUOTES, 'UTF-8'); ?>">
    <a href="<?php echo htmlspecialchars($cpEnUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cp-lang-btn<?php echo $cpCurrent === 'en' ? ' active' : ''; ?>" lang="en">EN</a>
    <a href="<?php echo htmlspecialchars($cpArUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cp-lang-btn<?php echo $cpCurrent === 'ar' ? ' active' : ''; ?>" lang="ar">ع</a>
</div>
