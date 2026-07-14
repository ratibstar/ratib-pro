#!/bin/bash
set -euo pipefail
php <<'PHP'
<?php
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
define('RATEB_ROOT', '/home/admin/domains/rateb.sa/public_html/rateb-erp');
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

$pdo = \Rateb\App\Core\Database::pdo();
$row = $pdo->query("SELECT id, email, company_id, is_super_admin, status FROM rateb_users WHERE email='admin@rateb.sa' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  // fallback any active SA
  $row = $pdo->query("SELECT id, email, company_id, is_super_admin, status FROM rateb_users WHERE is_super_admin=1 AND status='active' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
if (!$row) {
  echo json_encode(['ok'=>false,'error'=>'no_user']);
  exit(1);
}

\Rateb\App\Core\SessionManager::start();
\Rateb\App\Core\SessionManager::set('rateb_user_id', (int)$row['id']);
\Rateb\App\Core\SessionManager::set('rateb_company_id', (int)($row['company_id'] ?: 22));
\Rateb\App\Core\SessionManager::set('rateb_is_super_admin', !empty($row['is_super_admin']));
\Rateb\App\Core\SessionManager::set('rateb_user_email', (string)$row['email']);
session_write_close();

echo json_encode([
  'ok' => true,
  'session_name' => session_name(),
  'session_id' => session_id(),
  'user_id' => (int)$row['id'],
  'email' => $row['email'],
  'company_id' => (int)($row['company_id'] ?: 22),
  'is_super' => !empty($row['is_super_admin']),
], JSON_UNESCAPED_SLASHES);
PHP
