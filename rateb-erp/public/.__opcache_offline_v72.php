<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');
$t=(string)($_GET['t']??'');
if(!hash_equals('562a4355d2bd110d6b63678bd31422ba',$t)){http_response_code(404);echo "not_found\n";exit;}
foreach([dirname(__DIR__).'/config/app.php'] as $f){if(function_exists('opcache_invalidate')){@opcache_invalidate($f,true);} echo "invalidate $f\n";}
echo 'reset='.(function_exists('opcache_reset')?var_export(opcache_reset(),true):'n/a')."\n";
$app=@file_get_contents(dirname(__DIR__).'/config/app.php');
echo 'build='.(preg_match("/RATEB_ASSET_BUILD',\s*'([^']+)'/",(string)$app,$m)?$m[1]:'?')."\n";
@unlink(__FILE__);
