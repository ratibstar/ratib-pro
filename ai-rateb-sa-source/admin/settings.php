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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feature_settings'])) {
    verify_csrf();
    if (!admin_actor_is_owner($adminActor)) {
        $notice = 'owner_only';
    } else {
        ensure_platform_settings_schema($pdo);
        $bits = [];
        foreach (array_keys(rateb_feature_catalog()) as $featureKey) {
            $on = isset($_POST[$featureKey]) ? '1' : '0';
            platform_set_setting($pdo, $featureKey, $on, (int) ($adminUser['id'] ?? 0));
            $bits[] = $featureKey . '=' . $on;
        }
        admin_audit($pdo, $adminActor, 'platform_feature_settings', 'platform_settings', 0, 'owner', implode(';', $bits));
        $notice = 'saved';
    }
}

ensure_platform_settings_schema($pdo);
$publicOn = platform_setting_bool($pdo, 'public_site_enabled', true);
$maintOn = platform_setting_bool($pdo, 'maintenance_mode', false);
$msgEn = platform_setting($pdo, 'maintenance_message_en', '') ?? '';
$msgAr = platform_setting($pdo, 'maintenance_message_ar', '') ?? '';
$featureFlags = rateb_features($pdo);

admin_start(admin_t('System', 'النظام'));
admin_operations_nav_toolbar($adminActor, [
    ['href' => '/admin/plans.php', 'label' => admin_t('Plans', 'الباقات'), 'class' => 'quiet'],
]);
if ($notice === 'saved') {
    admin_flash_box('saved', 'ok');
} elseif ($notice === 'owner_only') {
    admin_flash_box('owner_only', 'bad');
}
if (admin_actor_is_owner($adminActor)) {
    admin_panel_open(admin_t('Public website', 'الموقع العام'), admin_t(
        'Public site off: marketing pages only. Maintenance: locks the whole customer site; only /admin stays available.',
        'إيقاف الموقع العام: صفحات التسويق فقط. الصيانة: تقفل موقع العملاء بالكامل؛ /admin فقط يبقى متاحًا.'
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
        'Preview: open https://ai.rateb.sa/ and /dashboard in a private window after saving. During maintenance both show the lock screen; only /admin works.',
        'معاينة: افتح https://ai.rateb.sa/ و/dashboard في نافذة خاصة بعد الحفظ. أثناء الصيانة الاثنان يعرضان شاشة القفل؛ /admin فقط يعمل.'
    )) . '</p>';
    admin_panel_close();

    admin_panel_open(admin_t('Customer features', 'ميزات العملاء'), admin_t(
        'Turning a feature off hides it completely from the campaign workspace and blocks its APIs. Not button-only.',
        'إيقاف الميزة يخفيها بالكامل من مساحة الحملة ويمنع واجهاتها البرمجية. ليس إخفاء أزرار فقط.'
    ));
    echo '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="feature_settings" value="1">';
    foreach (rateb_feature_catalog() as $featureKey => $meta) {
        $on = !empty($featureFlags[$featureKey]);
        $label = admin_t((string) $meta['en'], (string) $meta['ar']);
        $note = admin_t((string) $meta['note_en'], (string) $meta['note_ar']);
        echo '<p class="full"><label><input type="checkbox" name="' . admin_h($featureKey) . '"' . ($on ? ' checked' : '') . '> <strong>' . admin_h($label) . '</strong></label>';
        echo '<br><span class="note">' . admin_h($note) . ' · <span class="pill ' . ($on ? 'ok' : 'bad') . '">' . admin_h($on ? admin_t('Visible', 'ظاهر') : admin_t('Hidden', 'مخفي')) . '</span></span></p>';
    }
    echo '<p class="full"><button type="submit" class="btn primary">' . admin_h(admin_t('Save feature visibility', 'حفظ ظهور الميزات')) . '</button></p></form>';
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
