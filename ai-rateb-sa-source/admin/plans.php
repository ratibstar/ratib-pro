<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('finance.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    admin_require_permission('settings.manage');
    $code = (string) ($_POST['code'] ?? '');
    $allowed = ['free', 'starter', 'growth', 'pro', 'business'];
    $price = (float) ($_POST['price_sar'] ?? 0);
    $videos = (int) ($_POST['video_limit'] ?? 0);
    $seconds = (int) ($_POST['video_max_seconds'] ?? 0);
    $images = (int) ($_POST['image_limit'] ?? 0);
    $watermark = (string) ($_POST['watermark'] ?? '0') === '1' ? 1 : 0;
    $active = (string) ($_POST['is_active'] ?? '0') === '1' ? 1 : 0;
    if (in_array($code, $allowed, true) && $price >= 0 && $videos >= 0 && $seconds >= 0 && $images >= 0) {
        $pdo->prepare('UPDATE plans SET price_sar = ?, video_limit = ?, video_max_seconds = ?, image_limit = ?, watermark = ?, is_active = ? WHERE code = ?')->execute([
            number_format($price, 2, '.', ''),
            $videos,
            $seconds,
            $images,
            $watermark,
            $active,
            $code,
        ]);
        admin_audit($pdo, $adminActor, 'plan_update', 'plan', 0, 'admin', 'code=' . $code);
    }
    header('Location: /admin/plans.php');
    exit;
}

$plans = $pdo->query('SELECT * FROM plans ORDER BY sort_order, id')->fetchAll();
$canEdit = admin_actor_has($adminActor, 'settings.manage');
admin_start($adminLang === 'ar' ? 'الباقات والخطط' : 'Plans & packages');
admin_crud_toolbar([
    'screen' => 'plans',
    'can_manage' => $canEdit,
    'add_enabled' => false,
    'links' => [
        ['href' => '/admin/subscriptions.php', 'label' => $adminLang === 'ar' ? 'الاشتراكات' : 'Subscriptions', 'class' => 'quiet'],
    ],
]);
echo '<p class="note">' . admin_h(admin_t(
    'Catalog plan codes are fixed (free, starter, growth, pro, business). Edit limits and prices here — add new codes via a controlled migration only.',
    'رموز الباقات ثابتة في النظام. عدّل الأسعار والحدود هنا — إضافة باقة جديدة تتطلب ترحيلًا مضبوطًا فقط.'
)) . '</p>';
echo '<div class="cards">';
foreach ($plans as $plan) {
    $name = $adminLang === 'ar' ? (string) $plan['name_ar'] : (string) $plan['name_en'];
    admin_panel_open($name . ' · ' . (string) $plan['code']);
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="code" value="' . admin_h((string) $plan['code']) . '">';
    echo '<label>' . admin_h(admin_t('Price (SAR)', 'السعر (ر.س)')) . '<input name="price_sar" value="' . admin_h((string) $plan['price_sar']) . '"' . ($canEdit ? '' : ' disabled') . '></label>';
    echo '<label>' . admin_h(admin_t('Videos', 'فيديو')) . '<input name="video_limit" value="' . (int) $plan['video_limit'] . '"' . ($canEdit ? '' : ' disabled') . '></label>';
    echo '<label>' . admin_h(admin_t('Max seconds', 'أقصى ثواني')) . '<input name="video_max_seconds" value="' . (int) $plan['video_max_seconds'] . '"' . ($canEdit ? '' : ' disabled') . '></label>';
    echo '<label>' . admin_h(admin_t('Images', 'صور')) . '<input name="image_limit" value="' . (int) $plan['image_limit'] . '"' . ($canEdit ? '' : ' disabled') . '></label>';
    echo '<label>' . admin_h(admin_t('Watermark', 'علامة مائية')) . '<select name="watermark"' . ($canEdit ? '' : ' disabled') . '><option value="1"' . ((int) $plan['watermark'] === 1 ? ' selected' : '') . '>ON</option><option value="0"' . ((int) $plan['watermark'] === 0 ? ' selected' : '') . '>OFF</option></select></label>';
    echo '<label>' . admin_h(admin_t('Active', 'نشط')) . '<select name="is_active"' . ($canEdit ? '' : ' disabled') . '><option value="1"' . ((int) $plan['is_active'] === 1 ? ' selected' : '') . '>ON</option><option value="0"' . ((int) $plan['is_active'] === 0 ? ' selected' : '') . '>OFF</option></select></label>';
    if ($canEdit) {
        echo '<p class="full"><button type="submit" class="btn primary">' . admin_h(admin_t('Save plan', 'حفظ الباقة')) . '</button></p>';
    }
    echo '</form>';
    admin_panel_close();
}
echo '</div>';
admin_end();
