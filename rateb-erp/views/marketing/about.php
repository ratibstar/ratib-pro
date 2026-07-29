<?php
use Rateb\App\Services\CmsService;
$about = $about ?? null;
if (!$about) {
    $about = [
        'story_en' => 'Rateb was built to unify operations for healthcare and institutions in Saudi Arabia. We combine procurement, inventory, HR, finance, and compliance into one platform.',
        'story_ar' => 'بُني رتب لتوحيد عمليات القطاع الصحي والمؤسسات في السعودية. نجمع المشتريات والمخزون والموارد البشرية والمالية والامتثال في منصة واحدة.',
        'vision_en' => 'To become the trusted operations backbone for healthcare and institutions across the region.',
        'vision_ar' => 'أن نصبح العمود الفقري الموثوق للعمليات في القطاع الصحي والمؤسسات عبر المنطقة.',
        'mission_en' => 'Deliver an Arabic-first, compliant, and secure ERP that simplifies complex operations and drives measurable outcomes.',
        'mission_ar' => 'تقديم نظام ERP عربي أولاً ومتوافق وآمن يبسّط العمليات المعقدة ويحقق نتائج ملموسة.',
    ];
}
?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<?php if ($about) { ?>
<section class="rateb-mkt-section"><div class="container col-lg-8 mx-auto">
<h2><?php echo __('cms_our_story'); ?></h2>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($about, 'story')); ?></p>
<h2><?php echo __('cms_vision'); ?></h2>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($about, 'vision')); ?></p>
<h2><?php echo __('cms_mission'); ?></h2>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($about, 'mission')); ?></p>
</div></section>
<?php } ?>
