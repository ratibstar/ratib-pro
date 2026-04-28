<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/register-pro.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/register-pro.php`.
 */
/**
 * Short link for Pro agency registration - redirects to home.php (single registration form)
 */
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$base = dirname($scriptDir); // app root (one level up from /pages)
$base = ($base === '.' || $base === '\\') ? '' : $base;
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim($base, '/') . '/pages/home.php?open=register';
header('Location: ' . $url, true, 302);
exit;
