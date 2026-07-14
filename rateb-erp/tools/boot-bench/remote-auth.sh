#!/bin/bash
set -euo pipefail
MODE="${1:-mint}"
php <<PHP
<?php
\$_SERVER['HTTP_HOST'] = 'rateb.sa';
\$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';
\$_SERVER['HTTPS'] = 'on';
\$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
define('RATEB_ROOT', '/home/admin/domains/rateb.sa/public_html/rateb-erp');
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\\Rateb\\App\\Core\\Bootstrap::init(RATEB_ROOT);
\$pdo = \\Rateb\\App\\Core\\Database::pdo();
\$mode = getenv('RATEB_MINT_MODE') ?: '$MODE';
\$email = 'admin@rateb.sa';
\$st = \$pdo->prepare('SELECT id, email, company_id, is_super_admin, password_hash, status FROM rateb_users WHERE email = ? LIMIT 1');
\$st->execute([\$email]);
\$row = \$st->fetch(PDO::FETCH_ASSOC);
if (!\$row) {
  echo json_encode(['ok'=>false,'error'=>'user_missing']);
  exit(1);
}
if (\$mode === 'settemp') {
  \$bak = '/tmp/rateb_admin_pw_backup.json';
  file_put_contents(\$bak, json_encode(['id'=>(int)\$row['id'],'password_hash'=>\$row['password_hash']], JSON_UNESCAPED_SLASHES));
  \$temp = 'RatebBench!' . substr(bin2hex(random_bytes(4)), 0, 8);
  \$hash = password_hash(\$temp, PASSWORD_DEFAULT);
  \$u = \$pdo->prepare('UPDATE rateb_users SET password_hash = ? WHERE id = ?');
  \$u->execute([\$hash, (int)\$row['id']]);
  echo json_encode(['ok'=>true,'mode'=>'settemp','email'=>\$email,'temp'=>\$temp,'backup'=>\$bak], JSON_UNESCAPED_SLASHES);
  exit(0);
}
if (\$mode === 'restore') {
  \$bak = '/tmp/rateb_admin_pw_backup.json';
  if (!is_file(\$bak)) { echo json_encode(['ok'=>false,'error'=>'no_backup']); exit(1); }
  \$j = json_decode(file_get_contents(\$bak), true);
  \$u = \$pdo->prepare('UPDATE rateb_users SET password_hash = ? WHERE id = ?');
  \$u->execute([\$j['password_hash'], (int)\$j['id']]);
  @unlink(\$bak);
  echo json_encode(['ok'=>true,'mode'=>'restore']);
  exit(0);
}
// mint session
\\Rateb\\App\\Core\\SessionManager::start();
\\Rateb\\App\\Core\\SessionManager::set('rateb_user_id', (int)\$row['id']);
\\Rateb\\App\\Core\\SessionManager::set('rateb_company_id', (int)(\$row['company_id'] ?: 22));
\\Rateb\\App\\Core\\SessionManager::set('rateb_is_super_admin', !empty(\$row['is_super_admin']));
session_write_close();
echo json_encode([
  'ok'=>true,
  'mode'=>'mint',
  'session_name'=>session_name(),
  'session_id'=>session_id(),
  'user_id'=>(int)\$row['id'],
  'email'=>\$row['email'],
  'company_id'=>(int)(\$row['company_id'] ?: 22),
], JSON_UNESCAPED_SLASHES);
PHP
