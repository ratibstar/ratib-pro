<?php
if (preg_match('#\.(css|js|png|jpg|jpeg|gif|ico|woff2?|map|webp|svg)$#i', $_SERVER["REQUEST_URI"] ?? "")) {
    return false;
}
header("Content-Type: text/plain");
echo "URI=".($_SERVER["REQUEST_URI"]??"")."\n";
echo "SCRIPT=".($_SERVER["SCRIPT_NAME"]??"")."\n";
echo "PHP_SELF=".($_SERVER["PHP_SELF"]??"")."\n";
echo "PATH_INFO=".($_SERVER["PATH_INFO"]??"")."\n";
