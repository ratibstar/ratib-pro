<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/agency-request.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/agency-request.php`.
 */
/**
 * Public: Agency registration — redirects to home.php (single registration + payment form).
 * Keeps one page for both: home Register button and control panel "Register Pro".
 */
require_once __DIR__ . '/../includes/config.php';

$plan = trim($_GET['plan'] ?? 'pro') ?: 'pro';
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : null;
$years = isset($_GET['years']) ? (int)$_GET['years'] : null;

$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath;

$params = ['open' => 'register'];
if ($plan !== 'pro') $params['plan'] = $plan;
if ($amount !== null) $params['amount'] = $amount;
if ($years !== null) $params['years'] = $years;
$query = http_build_query($params);
$redirect = $baseUrl . '/pages/home.php?' . $query;
header('Location: ' . $redirect, true, 302);
exit;
