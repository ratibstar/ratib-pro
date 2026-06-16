<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/rateb-control-sso.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/rateb-control-sso.php`.
 */
/**
 * Control panel → RATEB Pro passwordless handoff was removed.
 * RATEB Pro must authenticate only via `users` (see pages/login.php).
 *
 * @return bool always false
 */
function rateb_control_sso_establish_program_session(): bool
{
    return false;
}
