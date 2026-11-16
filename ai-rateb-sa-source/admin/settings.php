<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/platform_settings_schema.php';

admin_require_permission('settings.manage');

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_settings'])) {
    verify_csrf();
    if (!admin_actor_is_owner($adminActor)) {
        $notice = 'owner_only';
    } else {
        ensure_platform_settings_schema($pdo);
        $publicOn = isset($_POST['public_site_enabled']) ? '1' : '0';
        $maintOn = isset($_POST['maintenance_mode']) ? '1' : '0';
        platform_set_setting($pdo, 'public_site_enabled', $publicOn, (int) ($adminUser['id'] ?? 0));
        platform_set_setting($pdo, 'maintenance_mode', $maintOn, (int) ($adminUser['id'] ?? 0));
        platform_set_setting($pdo, 'maintenance_message_en', trim((string) ($_POST['maintenance_message_en'] ?? '')), (int) ($adminUser['id'] ?? 0));
        platform_set_setting($pdo, 'maintenance_message_ar', trim((string) ($_POST['maintenance_message_ar'] ?? '')), (int) ($adminUser['id'] ?? 0));
        admin_audit($pdo, $adminActor, 'platform_site_settings', 'platform_settings', 0, 'owner', 'public=' . $publicOn . ';maintenance=' . $maintOn);
        $notice = 'saved';
    }
}

ensure_platform_settings_schema($pdo);
$publicOn = platform_setting_bool($pdo, 'public_site_enabled', true);
$maintOn = platform_setting_bool($pdo, 'maintenance_mode', false);
$msgEn = platform_setting($pdo, 'maintenance_message_en', '') ?? '';
$msgAr = platform_setting($pdo, 'maintenance_message_ar', '') ?? '';

admin_start(admin_t('System', 'النظام'));
admin_crud_toolbar([
    'screen' => 'settings',
    'can_manage' => admin_actor_is_owner($adminActor),
    'add_enabled' => false,
    'links' => [
        ['href' => '/admin/plans.php', 'label' => admin_t('Plans', 'الباقات')],
        ['href' => '/admin/usage.php', 'label' => admin_t('Usage', 'الاستخدام')],
    ],
]);
if ($notice === 'saved') {
    admin_flash_box('saved', 'ok');
} elseif ($notice === 'owner_only') {
    admin_flash_box('owner_only', 'bad');
}
if (admin_actor_is_owner($adminActor)) {
    admin_panel_open(admin_t('Public website', 'الموقع العام'), admin_t(
        'Applies to the marketing homepage (/) and registration pages. Customer dashboard, HR, and /admin stay available.',
        'يُطبَّق على الصفحة الرئيسية (/) وصفحات التسجيل. لوحة العملاء وHR و/admin تبقى متاحة.'
    ));
    echo '<section class="stats"><article class="stat"><b><span class="pill ' . ($publicOn ? 'ok' : 'bad') . '">' . admin_h($publicOn ? admin_t('On', 'مفعّل') : admin_t('Off', 'معطّل')) . '</span></b><span>' . admin_h(admin_t('Public site', 'الموقع العام')) . '</span></article>';
    echo '<article class="stat"><b><span class="pill ' . ($maintOn ? 'warn' : 'ok') . '">' . admin_h($maintOn ? admin_t('On', 'مفعّل') : admin_t('Off', 'معطّل')) . '</span></b><span>' . admin_h(admin_t('Maintenance', 'الصيانة')) . '</span></article></section>';
    echo '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="site_settings" value="1">';
    echo '<p class="full"><label><input type="checkbox" name="public_site_enabled"' . ($publicOn ? ' checked' : '') . '> ' . admin_h(admin_t('Public website enabled', 'تفعيل الموقع العام')) . '</label></p>';
    echo '<p class="full"><label><input type="checkbox" name="maintenance_mode"' . ($maintOn ? ' checked' : '') . '> ' . admin_h(admin_t('Maintenance mode', 'وضع الصيانة')) . '</label></p>';
    echo '<label class="full">' . admin_h(admin_t('Maintenance message (English)', 'رسالة الصيانة (إنجليزي)')) . '<textarea name="maintenance_message_en" rows="2">' . admin_h($msgEn) . '</textarea></label>';
    echo '<label class="full">' . admin_h(admin_t('Maintenance message (Arabic)', 'رسالة الصيانة (عربي)')) . '<textarea name="maintenance_message_ar" rows="2">' . admin_h($msgAr) . '</textarea></label>';
    echo '<p class="full"><button type="submit" class="btn primary">' . admin_h(admin_t('Save site settings', 'حفظ إعدادات الموقع')) . '</button></p></form>';
    echo '<p class="note">' . admin_h(admin_t(
        'Preview: open https://ai.rateb.sa/ in a private window after saving. Logged-in users reach /dashboard directly.',
        'معاينة: افتح https://ai.rateb.sa/ في نافذة خاصة بعد الحفظ. المستخدمون المسجّلون يدخلون /dashboard مباشرة.'
    )) . '</p>';
    admin_panel_close();
}
$truth = admin_billing_truth();
echo '<section class="cards">';
foreach ([
    [admin_t('Plan limits', 'حدود الباقة'), $truth['limits']],
    [admin_t('Spendable wallet', 'الرصيد القابل للصرف'), $truth['wallet']],
    [admin_t('Collected payments', 'المدفوعات المحصّلة'), $truth['payment']],
    [admin_t('What this console does not change', 'ما لا تغيّره هذه الوحدة'), $truth['reconcile']],
] as [$label, $text]) {
    echo '<article class="card"><h2>' . admin_h($label) . '</h2><p>' . admin_h($text) . '</p></article>';
}
echo '</section>';
echo '<p class="note">' . admin_h(admin_t(
    'Plan prices and subscription replacement stay on the Plans and Subscriptions pages. Provider secrets are not shown here.',
    'أسعار الباقات واستبدال الاشتراك يبقيان في صفحتي الباقات والاشتراكات. أسرار المزوّدين لا تُعرض هنا.'
)) . '</p>';
echo '<p><a class="btn" href="/admin/plans.php">' . admin_h(admin_t('Open plans', 'فتح الباقات')) . '</a> <a class="btn" href="/admin/usage.php#providers">' . admin_h(admin_t('Provider status', 'حالة المزوّدين')) . '</a></p>';
admin_end();
