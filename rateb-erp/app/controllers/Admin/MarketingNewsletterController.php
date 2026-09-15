<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Models\CmsNewsletterSubscriber;

/**
 * Marketing > Bulk Newsletter — same campaign engine and views as CMS > Newsletter,
 * exposed under a Marketing area gated by marketing.newsletter.* permissions.
 * Route middleware (rateb_platform_oversight_mw) enforces the permission; the
 * shared campaign views render against $this->routePrefix, so URLs stay here.
 */
class MarketingNewsletterController extends CmsNewsletterController
{
    public function __construct()
    {
        $this->model = new CmsNewsletterSubscriber();
        $this->viewPrefix = 'admin/cms/newsletter';
        $this->routePrefix = 'admin/marketing/newsletter';
        $this->entityName = 'marketing_newsletter';
        $this->createEnabled = false;
        $this->fields = [
            ['name' => 'email'], ['name' => 'name'],
            ['name' => 'segment'], ['name' => 'status', 'type' => 'select', 'options' => ['active', 'unsubscribed']],
        ];
    }
}