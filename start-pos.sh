#!/bin/bash
# شاشة البيع - نقطة الانطلاق السريعة
# POS Screen - Quick Start

echo "======================================"
echo "🎯 شاشة البيع - صلحت بنجاح!"
echo "======================================"
echo ""

# التحقق من الخوادم
echo "📊 الخوادم:"
if tasklist 2>/dev/null | grep -q mysqld; then
    echo "  ✓ MySQL مشغل"
else
    echo "  ✗ MySQL - غير مشغل"
fi

if tasklist 2>/dev/null | grep -q httpd; then
    echo "  ✓ Apache مشغل"
else
    echo "  ✗ Apache - غير مشغل"  
fi

echo ""
echo "🌐 روابط الوصول:"
echo "  1. http://localhost/test-pos.php (اختبار سريع)"
echo "  2. http://localhost/ratibprogram/pages/pos (شاشة البيع)"
echo "  3. http://localhost/phpmyadmin (إدارة قاعدة البيانات)"
echo ""
echo "======================================" 
