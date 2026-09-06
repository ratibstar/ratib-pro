<?php
declare(strict_types=1);
$agencyId = isset($agencyId) ? (int) $agencyId : 0;
$agencyName = isset($agencyName) ? (string) $agencyName : '';
$safeName = htmlspecialchars($agencyName !== '' ? $agencyName : ('Agency #' . $agencyId), ENT_QUOTES, 'UTF-8');
$safeId = htmlspecialchars((string) $agencyId, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>الوكالة معلّقة — Agency suspended</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background: #111827; color: #f9fafb; margin: 0; }
        .box { max-width: 40rem; margin: 12vh auto; padding: 2rem; background: #1f2937; border-radius: 12px; }
        h1 { margin: 0 0 0.75rem; font-size: 1.4rem; }
        p { line-height: 1.6; color: #d1d5db; }
        .en { direction: ltr; text-align: left; margin-top: 1.5rem; border-top: 1px solid #374151; padding-top: 1.25rem; }
        .meta { font-size: 0.9rem; color: #9ca3af; }
    </style>
</head>
<body>
    <main class="box">
        <h1>هذه الوكالة معلّقة</h1>
        <p>تم إيقاف نظام رتب ERP لهذه الوكالة بسبب حالة الاشتراك. لا يمكن استخدام اللوحة حتى يتم إلغاء التعليق من إدارة الوكالات أو تجديد الاشتراك.</p>
        <p class="meta">الوكالة: <?php echo $safeName; ?> — المعرف: <?php echo $safeId; ?></p>
        <div class="en">
            <h1>This agency is suspended</h1>
            <p>RATEB ERP is blocked for this tenant while Control Panel status is Suspended. Unsuspend or renew the subscription to restore access.</p>
        </div>
    </main>
</body>
</html>
