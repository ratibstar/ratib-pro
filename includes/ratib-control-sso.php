<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/ratib-control-sso.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/ratib-control-sso.php`.
 */
/**
 * Control panel → Ratib Pro passwordless handoff was removed.
 * Ratib Pro must authenticate only via `users` (see pages/login.php).
 *
 * @return bool always false
 */
function ratib_control_sso_establish_program_session(): bool
{
    return false;
}
