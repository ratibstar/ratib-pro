<?php use Rateb\App\Services\CmsService; ?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto">
<div class="accordion rateb-mkt-faq" id="faqPage">
<?php foreach ($allFaqs ?? $faqs ?? [] as $i => $faq) { ?>
<div class="accordion-item">
<h3 class="accordion-header"><button class="accordion-button<?php echo $i > 0 ? ' collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faqP<?php echo $i; ?>"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($faq, 'question')); ?></button></h3>
<div id="faqP<?php echo $i; ?>" class="accordion-collapse collapse<?php echo $i === 0 ? ' show' : ''; ?>" data-bs-parent="#faqPage"><div class="accordion-body"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($faq, 'answer')); ?></div></div>
</div>
<?php } ?>
</div></div></section>
