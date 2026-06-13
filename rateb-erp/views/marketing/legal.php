<?php
use Rateb\App\Services\CmsService;
$body = $page ? CmsService::pickLocale($page, 'content') : '';
?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto rateb-mkt-legal"><?php echo CmsService::sanitizeHtml($body); ?></div></section>
