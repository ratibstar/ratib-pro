<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
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
    }
    header('Location: /admin/plans.php');
    exit;
}

$plans = $pdo->query('SELECT * FROM plans ORDER BY sort_order, id')->fetchAll();
admin_start($adminLang === 'ar' ? 'الباقات' : 'Plans');
foreach ($plans as $plan) {
    $name = $adminLang === 'ar' ? (string) $plan['name_ar'] : (string) $plan['name_en'];
    echo '<form method="post" class="inline"><strong>' . admin_h($name) . '</strong>';
    echo '<input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="code" value="' . admin_h((string) $plan['code']) . '">';
    echo '<input name="price_sar" value="' . admin_h((string) $plan['price_sar']) . '">';
    echo '<input name="video_limit" value="' . (int) $plan['video_limit'] . '">';
    echo '<input name="video_max_seconds" value="' . (int) $plan['video_max_seconds'] . '">';
    echo '<input name="image_limit" value="' . (int) $plan['image_limit'] . '">';
    echo '<select name="watermark"><option value="1"' . ((int) $plan['watermark'] === 1 ? ' selected' : '') . '>ON</option><option value="0"' . ((int) $plan['watermark'] === 0 ? ' selected' : '') . '>OFF</option></select>';
    echo '<select name="is_active"><option value="1"' . ((int) $plan['is_active'] === 1 ? ' selected' : '') . '>ON</option><option value="0"' . ((int) $plan['is_active'] === 0 ? ' selected' : '') . '>OFF</option></select>';
    echo '<button type="submit">' . admin_h($adminLang === 'ar' ? 'حفظ' : 'Save') . '</button></form>';
}
admin_end();
