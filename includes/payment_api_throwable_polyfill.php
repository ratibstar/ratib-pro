<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/payment_api_throwable_polyfill.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/payment_api_throwable_polyfill.php`.
 */

declare(strict_types=1);

/**
 * Safe to require after payment_api_guards.php even if guards is an older copy without
 * payment_api_root_throwable (partial deploy). Uses require_once from API entrypoints.
 */
if (!function_exists('payment_api_root_throwable')) {
    function payment_api_root_throwable(Throwable $e): Throwable
    {
        $root = $e;
        while ($root->getPrevious() instanceof Throwable) {
            $root = $root->getPrevious();
        }
        return $root;
    }
}
