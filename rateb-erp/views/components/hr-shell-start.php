<?php
/** @var string $hrActive */
$hrActive = (string) ($hrActive ?? '');
?>
<link href="<?php echo rateb_asset('css/hr-module.css'); ?>" rel="stylesheet">
<div class="rateb-hr-layout">
    <?php Rateb\App\Core\View::partial('hr-tree-nav', ['hrActive' => $hrActive]); ?>
    <div class="rateb-hr-content">
