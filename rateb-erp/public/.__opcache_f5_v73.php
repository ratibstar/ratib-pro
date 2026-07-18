<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t=(string)($_GET['t']??'');
if(!hash_equals('4d9353570a0905da35d4c1d6a3e15912',$t)){http_response_code(404);echo "not_found\n";exit;}
$f=dirname(__DIR__).'/config/app.php';
if(function_exists('opcache_invalidate')){@opcache_invalidate($f,true);}
echo "invalidate $f\n";
echo 'reset='.(function_exists('opcache_reset')?var_export(opcache_reset(),true):'n/a')."\n";
$app=@file_get_contents($f);
echo 'build='.(preg_match("/RATEB_ASSET_BUILD',\s*'([^']+)'/",(string)$app,$m)?$m[1]:'?')."\n";
@unlink(__FILE__);
