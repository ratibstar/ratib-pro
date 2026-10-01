<?php
declare(strict_types=1);

/** @var string $ratebhrUrl */
/** @var string $httpsUrl */
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
    var ratebhr = <?php echo json_encode($ratebhrUrl ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var httpsOpen = <?php echo json_encode($httpsUrl ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var apkDirect = <?php echo json_encode($apkUrl ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    function go(url) {
        if (!url) return;
        try { window.location.replace(url); } catch (e) {
            try { window.location.href = url; } catch (e2) {}
        }
    }
    go(httpsOpen);
    window.setTimeout(function () { go(ratebhr); }, 120);
    window.setTimeout(function () {
        if (apkDirect) go(apkDirect);
    }, 2200);
})();
</script>
</head><body></body></html>
