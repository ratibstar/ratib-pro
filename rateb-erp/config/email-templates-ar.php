<?php
declare(strict_types=1);

/** slug => [subject, body_html] — placeholders: {name} {date} {type} {id} {item} {qty} {no} {device} {reset_url} */
return [
    'welcome' => [
        'مرحباً بك في نظام رتب ERP',
        '<p>مرحباً بك في نظام رتب ERP.</p>',
    ],
    'password_reset' => [
        'نظام رتب ERP — إعادة تعيين كلمة المرور',
        '<p>مرحباً {name}،</p><p>اضغط لإعادة تعيين كلمة المرور:</p><p><a href="{reset_url}">{reset_url}</a></p>',
    ],
    'subscription_expiring' => [
        'اشتراكك على وشك الانتهاء',
        '<p>مرحباً {name}، ينتهي اشتراكك في {date}.</p>',
    ],
    'trial_expiring' => [
        'تجربتك على وشك الانتهاء',
        '<p>مرحباً {name}، تنتهي تجربتك في {date}.</p>',
    ],
    'approval_request' => [
        'اعتماد مطلوب: {type}',
        '<p>لديك طلب اعتماد معلق لـ {type} رقم {id}.</p>',
    ],
    'approval_completed' => [
        'تم الاعتماد',
        '<p>تم اعتماد {type} رقم {id}.</p>',
    ],
    'approval_rejected' => [
        'تم رفض الاعتماد',
        '<p>تم رفض {type} رقم {id}.</p>',
    ],
    'low_stock_alert' => [
        'مخزون منخفض: {item}',
        '<p>المخزون منخفض لـ {item} ({qty} متبقي).</p>',
    ],
    'expiry_alert' => [
        'تنبيه انتهاء الصلاحية: {item}',
        '<p>تنتهي صلاحية {item} في {date}.</p>',
    ],
    'contract_expiry_alert' => [
        'انتهاء العقد: {no}',
        '<p>ينتهي العقد {no} في {date}.</p>',
    ],
    'maintenance_due_alert' => [
        'صيانة مستحقة: {device}',
        '<p>صيانة {device} مستحقة في {date}.</p>',
    ],
    'warranty_expiry_alert' => [
        'انتهاء الضمان: {device}',
        '<p>ينتهي ضمان {device} في {date}.</p>',
    ],
];
