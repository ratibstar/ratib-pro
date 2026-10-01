<?php
declare(strict_types=1);

/** @var string $intent */
/** @var string $intentHttps */
/** @var string $apkUrl */
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title></title>
<style>html,body{margin:0;padding:0;background:#fff;height:100%}</style>
<script>
(function () {
    var intentRatebhr = <?php echo json_encode($intent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var intentHttps = <?php echo json_encode($intentHttps ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var apkDirect = <?php echo json_encode($apkUrl ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    function go(url) {
        if (!url) return;
        try { window.location.replace(url); } catch (e) {
            try { window.location.href = url; } catch (e2) {}
        }
    }
    go(intentRatebhr);
    window.setTimeout(function () { go(intentHttps); }, 80);
    window.setTimeout(function () { go(apkDirect); }, 700);
})();
</script>
</head><body></body></html>
