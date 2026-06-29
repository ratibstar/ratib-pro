<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\View;
use Rateb\App\Models\CmsAbout;
use Rateb\App\Models\CmsAnalytics;
use Rateb\App\Models\CmsBlogArticle;
use Rateb\App\Models\CmsBlogAuthor;
use Rateb\App\Models\CmsBlogCategory;
use Rateb\App\Models\CmsBlogTag;
use Rateb\App\Models\CmsBlock;
use Rateb\App\Models\CmsCareer;
use Rateb\App\Models\CmsFaq;
use Rateb\App\Models\CmsFaqCategory;
use Rateb\App\Models\CmsHelpArticle;
use Rateb\App\Models\CmsKbArticle;
use Rateb\App\Models\CmsLead;
use Rateb\App\Models\CmsLeadNote;
use Rateb\App\Models\CmsFooterColumn;
use Rateb\App\Models\CmsNewsletterCampaign;
use Rateb\App\Models\CmsOffice;
use Rateb\App\Models\CmsMedia;
use Rateb\App\Models\CmsMediaCategory;
use Rateb\App\Models\CmsMenu;
use Rateb\App\Models\CmsMenuItem;
use Rateb\App\Models\CmsNewsletterSegment;
use Rateb\App\Models\CmsNewsletterSubscriber;
use Rateb\App\Models\CmsPage;
use Rateb\App\Models\CmsPartner;
use Rateb\App\Models\CmsRedirect;
use Rateb\App\Models\CmsRobots;
use Rateb\App\Models\CmsSection;
use Rateb\App\Models\CmsSeo;
use Rateb\App\Models\CmsService as CmsServiceModel;
use Rateb\App\Models\CmsServiceCategory;
use Rateb\App\Models\CmsSlide;
use Rateb\App\Models\CmsSystemStatus;
use Rateb\App\Models\CmsTeamMember;
use Rateb\App\Models\CmsTestimonial;
use Rateb\App\Models\CmsTheme;
use Rateb\App\Models\CmsTimeline;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\CmsArticleTagService;
use Rateb\App\Services\CmsMediaService;
use Rateb\App\Services\CmsNewsletterCampaignService;
use Rateb\App\Services\CmsService;

final class CmsDashboardController extends Controller
{
    public function index(): void
    {
        $stats = (new CmsService())->dashboardStats();
        $this->view('admin/cms/dashboard', [
            'title' => __('cms_dashboard'),
            'stats' => $stats,
            'csrf' => Csrf::token(),
        ], 'main');
    }
}

final class CmsPagesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsPage();
        $this->viewPrefix = 'admin/cms/pages';
        $this->routePrefix = 'admin/cms/pages';
        $this->entityName = 'cms_pages';
        $this->fields = [
            ['name' => 'slug', 'label' => 'slug'],
            ['name' => 'title_en', 'label' => 'title_en'],
            ['name' => 'title_ar', 'label' => 'title_ar'],
            ['name' => 'template', 'label' => 'template', 'type' => 'select', 'lookup' => 'page_templates'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'options' => ['draft', 'published', 'scheduled']],
            ['name' => 'content_en', 'label' => 'content_en', 'type' => 'wysiwyg'],
            ['name' => 'content_ar', 'label' => 'content_ar', 'type' => 'wysiwyg'],
        ];
    }
}

final class CmsSectionsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsSection();
        $this->viewPrefix = 'admin/cms/sections';
        $this->routePrefix = 'admin/cms/sections';
        $this->entityName = 'cms_sections';
        $this->fields = [
            ['name' => 'page_slug', 'label' => 'page_slug', 'type' => 'fk', 'lookup' => 'cms_pages'],
            ['name' => 'section_key', 'label' => 'section_key'],
            ['name' => 'title_en', 'label' => 'title_en'],
            ['name' => 'title_ar', 'label' => 'title_ar'],
            ['name' => 'body_en', 'label' => 'body_en', 'type' => 'wysiwyg'],
            ['name' => 'body_ar', 'label' => 'body_ar', 'type' => 'wysiwyg'],
            ['name' => 'sort_order', 'label' => 'sort_order', 'type' => 'number'],
            ['name' => 'is_active', 'label' => 'is_active', 'type' => 'select', 'options' => ['1', '0']],
        ];
    }
}

final class CmsBlocksController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsBlock();
        $this->viewPrefix = 'admin/cms/blocks';
        $this->routePrefix = 'admin/cms/blocks';
        $this->entityName = 'cms_blocks';
        $this->fields = [
            ['name' => 'section_id', 'label' => 'section_id', 'type' => 'fk', 'lookup' => 'cms_sections'],
            ['name' => 'block_type', 'label' => 'block_type', 'type' => 'select', 'options' => ['text', 'image', 'cta', 'features', 'stats', 'html'], 'translate_options' => false],
            ['name' => 'title_en', 'label' => 'title_en'],
            ['name' => 'title_ar', 'label' => 'title_ar'],
            ['name' => 'content_en', 'label' => 'content_en', 'type' => 'textarea'],
            ['name' => 'content_ar', 'label' => 'content_ar', 'type' => 'textarea'],
            ['name' => 'icon', 'label' => 'icon'],
            ['name' => 'sort_order', 'label' => 'sort_order', 'type' => 'number'],
        ];
    }
}

final class CmsMenuItemsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsMenuItem();
        $this->viewPrefix = 'admin/cms/menu-items';
        $this->routePrefix = 'admin/cms/menu-items';
        $this->entityName = 'cms_menu';
        $this->fields = [
            ['name' => 'menu_id', 'label' => 'menu_id', 'type' => 'fk', 'lookup' => 'cms_menus'],
            ['name' => 'label_en', 'label' => 'label_en'],
            ['name' => 'label_ar', 'label' => 'label_ar'],
            ['name' => 'url', 'label' => 'url'],
            ['name' => 'sort_order', 'label' => 'sort_order', 'type' => 'number'],
            ['name' => 'is_active', 'label' => 'is_active', 'type' => 'select', 'options' => ['1', '0']],
        ];
    }
}

final class CmsBlogArticlesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsBlogArticle();
        $this->viewPrefix = 'admin/cms/blog-articles';
        $this->routePrefix = 'admin/cms/blog-articles';
        $this->entityName = 'cms_blog';
        $this->fields = [
            ['name' => 'slug', 'label' => 'slug'],
            ['name' => 'title_en', 'label' => 'title_en'],
            ['name' => 'title_ar', 'label' => 'title_ar'],
            ['name' => 'excerpt_en', 'label' => 'excerpt_en', 'type' => 'textarea'],
            ['name' => 'excerpt_ar', 'label' => 'excerpt_ar', 'type' => 'textarea'],
            ['name' => 'content_en', 'label' => 'content_en', 'type' => 'wysiwyg'],
            ['name' => 'content_ar', 'label' => 'content_ar', 'type' => 'wysiwyg'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'options' => ['draft', 'published', 'scheduled']],
            ['name' => 'published_at', 'label' => 'published_at', 'type' => 'datetime-local'],
            ['name' => 'meta_title_en', 'label' => 'meta_title_en'],
            ['name' => 'meta_description_en', 'label' => 'meta_description_en', 'type' => 'textarea'],
        ];
        $this->indexFields = [
            ['name' => 'slug', 'label' => 'slug', 'type' => 'slug'],
            ['name' => 'title_ar', 'label' => 'title_ar', 'type' => 'clip'],
            ['name' => 'title_en', 'label' => 'title_en', 'type' => 'clip'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
            ['name' => 'published_at', 'label' => 'published_at', 'type' => 'clip'],
        ];
    }

    public function create(): void
    {
        $this->guardManage();
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __('cms_blog'),
            'item' => null,
        ]), $this->layout());
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('edit') . ' ' . __('cms_blog'),
            'item' => $item,
        ]), $this->layout());
    }

    public function store(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $tagIds = $this->tagIdsFromInput();
        $id = $this->model->create($data);
        (new CmsArticleTagService())->syncForArticle($id, $tagIds);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        $this->model->update($id, $data);
        (new CmsArticleTagService())->syncForArticle($id, $this->tagIdsFromInput());
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    /** @return array<string, mixed> */
    protected function formViewData(array $extra = []): array
    {
        $item = $extra['item'] ?? null;
        $selectedTags = [];
        if (is_array($item)) {
            $selectedTags = (new CmsArticleTagService())->tagIdsForArticle((int) $item['id']);
        }
        return array_merge(parent::formViewData($extra), [
            'allTags' => (new CmsBlogTag())->all(200, 0),
            'selectedTags' => $selectedTags,
            'cmsWysiwyg' => true,
        ]);
    }

    /** @return array<int, int> */
    private function tagIdsFromInput(): array
    {
        $raw = $this->input('tag_ids', []);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $raw)));
    }
}

final class CmsBlogCategoriesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->init(new CmsBlogCategory(), 'blog-categories', 'cms_blog_categories', [
        ['name' => 'slug'], ['name' => 'name_en'], ['name' => 'name_ar'],
    ]); }
    private function init($m, string $path, string $entity, array $fields): void {
        $this->model = $m; $this->viewPrefix = 'admin/cms/' . $path; $this->routePrefix = 'admin/cms/' . $path;
        $this->entityName = $entity; $this->fields = array_map(static fn($f) => ['name' => $f['name'], 'label' => $f['name']], $fields);
    }
}

final class CmsBlogTagsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsBlogTag(); $this->viewPrefix = 'admin/cms/blog-tags'; $this->routePrefix = 'admin/cms/blog-tags'; $this->entityName = 'cms_blog_tags';
        $this->fields = [['name' => 'slug'], ['name' => 'name_en'], ['name' => 'name_ar']]; }
}

final class CmsBlogAuthorsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsBlogAuthor(); $this->viewPrefix = 'admin/cms/blog-authors'; $this->routePrefix = 'admin/cms/blog-authors'; $this->entityName = 'cms_blog_authors';
        $this->fields = [['name' => 'name_en'], ['name' => 'name_ar'], ['name' => 'email'], ['name' => 'bio_en', 'type' => 'textarea']]; }
}

final class CmsFaqsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsFaq();
        $this->viewPrefix = 'admin/cms/faqs';
        $this->routePrefix = 'admin/cms/faqs';
        $this->entityName = 'cms_faqs';
        $this->fields = [
            ['name' => 'category_id', 'label' => 'cms_faq_categories', 'type' => 'fk', 'lookup' => 'cms_faq_categories'],
            ['name' => 'question_en'], ['name' => 'question_ar'],
            ['name' => 'answer_en', 'type' => 'textarea'], ['name' => 'answer_ar', 'type' => 'textarea'],
            ['name' => 'sort_order', 'type' => 'number'],
        ];
    }
}

final class CmsFaqCategoriesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsFaqCategory(); $this->viewPrefix = 'admin/cms/faq-categories'; $this->routePrefix = 'admin/cms/faq-categories'; $this->entityName = 'cms_faq_categories';
        $this->fields = [['name' => 'slug'], ['name' => 'name_en'], ['name' => 'name_ar'], ['name' => 'sort_order', 'type' => 'number']]; }
}

final class CmsTestimonialsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsTestimonial();
        $this->viewPrefix = 'admin/cms/testimonials';
        $this->routePrefix = 'admin/cms/testimonials';
        $this->entityName = 'cms_testimonials';
        $this->fields = [
            ['name' => 'customer_name_en'], ['name' => 'customer_name_ar'],
            ['name' => 'position_en'], ['name' => 'company_en'],
            ['name' => 'quote_en', 'type' => 'textarea'], ['name' => 'quote_ar', 'type' => 'textarea'],
            ['name' => 'rating', 'type' => 'number'],
            ['name' => 'status', 'type' => 'select', 'options' => ['pending', 'approved', 'rejected']],
            ['name' => 'sort_order', 'type' => 'number'],
        ];
    }
}

final class CmsSlidesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsSlide();
        $this->viewPrefix = 'admin/cms/slides';
        $this->routePrefix = 'admin/cms/slides';
        $this->entityName = 'cms_slides';
        $this->fields = [
            ['name' => 'title_en'], ['name' => 'title_ar'],
            ['name' => 'subtitle_en', 'type' => 'textarea'], ['name' => 'subtitle_ar', 'type' => 'textarea'],
            ['name' => 'image_path'], ['name' => 'video_url'],
            ['name' => 'cta_label_en'], ['name' => 'cta_url'],
            ['name' => 'sort_order', 'type' => 'number'],
            ['name' => 'is_active', 'type' => 'select', 'options' => ['1', '0']],
        ];
    }
}

final class CmsLeadsController extends Controller
{
    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = 25;
        $model = new CmsLead();
        $this->view('admin/cms/leads/index', [
            'title' => __('cms_leads'),
            'items' => $model->all($limit, ($page - 1) * $limit),
            'total' => $model->count(),
            'page' => $page,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function show(int $id): void
    {
        $model = new CmsLead();
        $lead = $model->find($id);
        if ($lead === null) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url('admin/cms/leads'));
            return;
        }
        $notes = (new CmsLeadNote())->all(50, 0, ['lead_id' => $id]);
        $mailSvc = new \Rateb\App\Services\MailConfigService();
        $mailCfg = $mailSvc->resolve();
        $this->view('admin/cms/leads/show', [
            'title' => __('cms_leads'),
            'lead' => $lead,
            'notes' => $notes,
            'csrf' => Csrf::token(),
            'mailReady' => $mailSvc->isReady(),
            'mailLocalhost' => $mailSvc->isLocalRelayHost((string) ($mailCfg['host'] ?? '')),
            'mailHost' => (string) ($mailCfg['host'] ?? ''),
        ], 'main');
    }

    public function update(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/leads/' . $id));
            return;
        }
        $model = new CmsLead();
        $lead = $model->find($id);
        if ($lead === null) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url('admin/cms/leads'));
            return;
        }

        $reply = trim((string) $this->input('reply_message', ''));
        $replySubject = trim((string) $this->input('reply_subject', ''));
        if ($reply !== '') {
            if ($replySubject === '') {
                $typeLabel = match ((string) ($lead['lead_type'] ?? '')) {
                    'demo' => __('cms_lead_type_demo'),
                    'quote' => __('cms_lead_type_quote'),
                    'contact' => __('cms_lead_type_contact'),
                    default => __('cms_leads'),
                };
                $replySubject = __('cms_lead_reply_subject_default', ['type' => $typeLabel]);
            }
            $userId = (int) ($_SESSION['rateb_user_id'] ?? 0);
            $notifier = new \Rateb\App\Services\CmsLeadNotificationService();
            $sent = $notifier->replyToCustomer($lead, $replySubject, $reply, $userId);
            if ($sent) {
                (new CmsLeadNote())->create([
                    'lead_id' => $id,
                    'user_id' => $userId ?: null,
                    'note' => __('cms_lead_note_email_sent') . "\n" . $reply,
                ]);
                if (($lead['status'] ?? '') === 'new') {
                    $model->update($id, ['status' => 'contacted']);
                }
                (new AuditService())->log('cms_lead_reply', 'cms_lead', $id, ['email' => $lead['email'] ?? '']);
                SessionManager::flash('success', __('cms_lead_reply_sent'));
            } else {
                $err = $notifier->lastError() ?? __('cms_lead_reply_failed');
                SessionManager::flash('error', $err);
            }
            $this->redirect(rateb_url('admin/cms/leads/' . $id));
            return;
        }

        $model->update($id, [
            'status' => (string) $this->input('status', 'new'),
            'assigned_user_id' => (int) $this->input('assigned_user_id', 0) ?: null,
        ]);
        $note = trim((string) $this->input('note', ''));
        if ($note !== '') {
            (new CmsLeadNote())->create([
                'lead_id' => $id,
                'user_id' => (int) ($_SESSION['rateb_user_id'] ?? 0) ?: null,
                'note' => $note,
            ]);
        }
        (new AuditService())->log('cms_lead_update', 'cms_lead', $id, []);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url('admin/cms/leads/' . $id));
    }
}

final class CmsNewsletterController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsNewsletterSubscriber();
        $this->viewPrefix = 'admin/cms/newsletter';
        $this->routePrefix = 'admin/cms/newsletter';
        $this->entityName = 'cms_newsletter';
        $this->createEnabled = false;
        $this->fields = [
            ['name' => 'email'], ['name' => 'name'],
            ['name' => 'segment'], ['name' => 'status', 'type' => 'select', 'options' => ['active', 'unsubscribed']],
        ];
    }

    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $this->view($this->viewPrefix . '/index', array_merge($this->applyPermissionFlags([
            'title' => __('cms_newsletter'),
            'items' => $this->model->all($limit, ($page - 1) * $limit),
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
            'campaigns' => (new CmsNewsletterCampaign())->all(20, 0),
        ]), []), $this->layout());
    }

    public function export(): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="newsletter-subscribers.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['email', 'name', 'segment', 'status', 'subscribed_at']);
        foreach ((new CmsNewsletterSubscriber())->all(10000, 0) as $row) {
            fputcsv($out, [$row['email'], $row['name'], $row['segment'], $row['status'], $row['subscribed_at']]);
        }
        fclose($out);
        exit;
    }

    public function import(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/newsletter'));
            return;
        }
        $csv = '';
        if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $csv = (string) file_get_contents($_FILES['csv_file']['tmp_name']);
        } else {
            $csv = (string) $this->input('csv_text', '');
        }
        $result = (new CmsNewsletterCampaignService())->importCsv($csv);
        SessionManager::flash('success', __('cms_import_ok') . ': ' . $result['imported'] . ' / ' . $result['skipped']);
        $this->redirect(rateb_url('admin/cms/newsletter'));
    }

    public function campaignForm(): void
    {
        $this->guardManage();
        $id = (int) ($_GET['id'] ?? $this->input('id', 0));
        $item = $id > 0 ? (new CmsNewsletterCampaign())->find($id) : null;
        $this->view('admin/cms/newsletter/campaign-form', [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('cms_campaign'),
            'item' => $item,
            'segments' => (new CmsNewsletterSegment())->all(50, 0),
            'csrf' => Csrf::token(),
            'cmsWysiwyg' => true,
        ], $this->layout());
    }

    public function campaignSave(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/newsletter'));
            return;
        }
        $model = new CmsNewsletterCampaign();
        $id = (int) $this->input('id', 0);
        $data = [
            'subject_en' => (string) $this->input('subject_en', ''),
            'subject_ar' => (string) $this->input('subject_ar', ''),
            'body_html_en' => (string) $this->input('body_html_en', ''),
            'body_html_ar' => (string) $this->input('body_html_ar', ''),
            'segment_slug' => (string) $this->input('segment_slug', 'general'),
            'status' => (string) $this->input('status', 'draft'),
            'scheduled_at' => (string) $this->input('scheduled_at', '') ?: null,
        ];
        if ($id > 0) {
            $model->update($id, $data);
        } else {
            $id = $model->create($data);
        }
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url('admin/cms/newsletter/campaign?id=' . $id));
    }

    public function campaignSend(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/newsletter'));
            return;
        }
        $id = (int) $this->input('id', 0);
        try {
            $result = (new CmsNewsletterCampaignService())->dispatchCampaign($id);
            SessionManager::flash('success', __('cms_campaign_sent') . ': ' . $result['sent']);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url('admin/cms/newsletter'));
    }
}

final class CmsSeoController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsSeo();
        $this->viewPrefix = 'admin/cms/seo';
        $this->routePrefix = 'admin/cms/seo';
        $this->entityName = 'cms_seo';
        $this->fields = [
            ['name' => 'page_slug'], ['name' => 'meta_title_en'], ['name' => 'meta_title_ar'],
            ['name' => 'meta_description_en', 'type' => 'textarea'], ['name' => 'meta_description_ar', 'type' => 'textarea'],
            ['name' => 'og_title_en'], ['name' => 'og_image'], ['name' => 'canonical_url'],
            ['name' => 'twitter_card'],
        ];
    }
}

final class CmsRedirectsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsRedirect(); $this->viewPrefix = 'admin/cms/redirects'; $this->routePrefix = 'admin/cms/redirects'; $this->entityName = 'cms_redirects';
        $this->fields = [['name' => 'from_path'], ['name' => 'to_path'], ['name' => 'status_code', 'type' => 'select', 'lookup' => 'redirect_status_codes'], ['name' => 'is_active', 'type' => 'select', 'options' => ['1', '0']]]; }
}

final class CmsMediaController extends Controller
{
    public function index(): void
    {
        $model = new CmsMedia();
        $mediaSvc = new CmsMediaService();
        $items = $model->all(50, 0);
        foreach ($items as &$row) {
            $row['public_url'] = $mediaSvc->publicUrl((string) ($row['file_path'] ?? ''));
        }
        unset($row);
        $this->view('admin/cms/media/index', [
            'title' => __('cms_media'),
            'items' => $items,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function listJson(): void
    {
        $model = new CmsMedia();
        $mediaSvc = new CmsMediaService();
        $out = [];
        foreach ($model->all(200, 0) as $row) {
            $mime = (string) ($row['mime_type'] ?? '');
            if (strpos($mime, 'image/') !== 0) {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['file_name'] ?? ''),
                'url' => $mediaSvc->publicUrl((string) ($row['file_path'] ?? '')),
            ];
        }
        Response::json(['ok' => true, 'items' => $out]);
    }

    public function tinymceUpload(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['error' => 'CSRF'], 403);
            return;
        }
        $file = $_FILES['file'] ?? [];
        $result = (new CmsMediaService())->upload($file, (int) ($_SESSION['rateb_user_id'] ?? 0));
        if (!$result['ok']) {
            Response::json(['error' => $result['error'] ?? 'Upload failed'], 400);
            return;
        }
        $url = (new CmsMediaService())->publicUrl((string) ($result['path'] ?? ''));
        Response::json(['location' => $url]);
    }

    public function upload(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            $this->redirect(rateb_url('admin/cms/media'));
            return;
        }
        $file = $_FILES['file'] ?? [];
        $result = (new CmsMediaService())->upload($file, (int) ($_SESSION['rateb_user_id'] ?? 0));
        if (!$result['ok']) {
            SessionManager::flash('error', $result['error'] ?? 'Upload failed');
        } else {
            SessionManager::flash('success', __('cms_upload_ok'));
        }
        $this->redirect(rateb_url('admin/cms/media'));
    }
}

final class CmsThemeController extends Controller
{
    public function index(): void
    {
        $model = new CmsTheme();
        $row = $model->all(1, 0);
        $this->view('admin/cms/theme', [
            'title' => __('cms_theme'),
            'item' => $row[0] ?? null,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function save(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/theme'));
            return;
        }
        $model = new CmsTheme();
        $rows = $model->all(1, 0);
        $data = [
            'primary_color' => (string) $this->input('primary_color', '#1a5fb4'),
            'secondary_color' => (string) $this->input('secondary_color', '#3584e4'),
            'font_family' => (string) $this->input('font_family', 'Tajawal'),
            'logo_path' => (string) $this->input('logo_path', ''),
            'favicon_path' => (string) $this->input('favicon_path', ''),
            'custom_css' => (string) $this->input('custom_css', ''),
            'custom_js' => (string) $this->input('custom_js', ''),
        ];
        if (!empty($rows[0]['id'])) {
            $model->update((int) $rows[0]['id'], $data);
        } else {
            $model->create($data);
        }
        (new AuditService())->log('cms_theme_save', 'cms_theme', 1, []);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url('admin/cms/theme'));
    }
}

final class CmsAnalyticsController extends Controller
{
    public function index(): void
    {
        $model = new CmsAnalytics();
        $row = $model->all(1, 0);
        $this->view('admin/cms/analytics', [
            'title' => __('cms_analytics'),
            'item' => $row[0] ?? null,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function save(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/analytics'));
            return;
        }
        $model = new CmsAnalytics();
        $rows = $model->all(1, 0);
        $data = [
            'google_analytics_id' => (string) $this->input('google_analytics_id', ''),
            'google_tag_manager_id' => (string) $this->input('google_tag_manager_id', ''),
            'meta_pixel_id' => (string) $this->input('meta_pixel_id', ''),
            'tiktok_pixel_id' => (string) $this->input('tiktok_pixel_id', ''),
            'custom_head_code' => \Rateb\App\Core\HtmlSanitizer::sanitizeAnalyticsEmbed((string) $this->input('custom_head_code', '')),
            'custom_body_code' => \Rateb\App\Core\HtmlSanitizer::sanitizeAnalyticsEmbed((string) $this->input('custom_body_code', '')),
        ];
        if (!empty($rows[0]['id'])) {
            $model->update((int) $rows[0]['id'], $data);
        } else {
            $model->create($data);
        }
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url('admin/cms/analytics'));
    }
}

final class CmsRobotsController extends Controller
{
    public function index(): void
    {
        $model = new CmsRobots();
        $row = $model->all(1, 0);
        $this->view('admin/cms/robots', [
            'title' => __('cms_robots'),
            'item' => $row[0] ?? null,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function save(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/robots'));
            return;
        }
        $model = new CmsRobots();
        $rows = $model->all(1, 0);
        $content = (string) $this->input('content', '');
        if (!empty($rows[0]['id'])) {
            $model->update((int) $rows[0]['id'], ['content' => $content]);
        } else {
            $model->create(['content' => $content]);
        }
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url('admin/cms/robots'));
    }
}

final class CmsAboutController extends Controller
{
    public function index(): void
    {
        $about = (new CmsAbout())->all(1, 0);
        $team = (new CmsTeamMember())->all(50, 0);
        $timeline = (new CmsTimeline())->all(50, 0);
        $this->view('admin/cms/about', [
            'title' => __('cms_about'),
            'about' => $about[0] ?? null,
            'team' => $team,
            'timeline' => $timeline,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function save(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/about'));
            return;
        }
        $model = new CmsAbout();
        $rows = $model->all(1, 0);
        $data = [
            'story_en' => (string) $this->input('story_en', ''),
            'story_ar' => (string) $this->input('story_ar', ''),
            'vision_en' => (string) $this->input('vision_en', ''),
            'vision_ar' => (string) $this->input('vision_ar', ''),
            'mission_en' => (string) $this->input('mission_en', ''),
            'mission_ar' => (string) $this->input('mission_ar', ''),
        ];
        if (!empty($rows[0]['id'])) {
            $model->update((int) $rows[0]['id'], $data);
        } else {
            $model->create($data);
        }
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url('admin/cms/about'));
    }
}

final class CmsServicesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsServiceModel(); $this->viewPrefix = 'admin/cms/services'; $this->routePrefix = 'admin/cms/services'; $this->entityName = 'cms_services';
        $this->fields = [['name' => 'slug'], ['name' => 'title_en'], ['name' => 'title_ar'], ['name' => 'summary_en', 'type' => 'textarea'], ['name' => 'content_en', 'type' => 'textarea'], ['name' => 'icon'], ['name' => 'status', 'type' => 'select', 'options' => ['draft', 'published']]]; }
}

final class CmsServiceCategoriesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsServiceCategory(); $this->viewPrefix = 'admin/cms/service-categories'; $this->routePrefix = 'admin/cms/service-categories'; $this->entityName = 'cms_service_categories';
        $this->fields = [['name' => 'slug'], ['name' => 'name_en'], ['name' => 'name_ar'], ['name' => 'icon']]; }
}

final class CmsPartnersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsPartner(); $this->viewPrefix = 'admin/cms/partners'; $this->routePrefix = 'admin/cms/partners'; $this->entityName = 'cms_partners';
        $this->fields = [['name' => 'name_en'], ['name' => 'name_ar'], ['name' => 'logo_path'], ['name' => 'website_url'], ['name' => 'sort_order', 'type' => 'number']]; }
}

final class CmsCareersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsCareer(); $this->viewPrefix = 'admin/cms/careers'; $this->routePrefix = 'admin/cms/careers'; $this->entityName = 'cms_careers';
        $this->fields = [['name' => 'slug'], ['name' => 'title_en'], ['name' => 'title_ar'], ['name' => 'department_en', 'type' => 'fk', 'lookup' => 'career_departments'], ['name' => 'location_en'], ['name' => 'description_en', 'type' => 'textarea'], ['name' => 'status', 'type' => 'select', 'options' => ['open', 'closed']]]; }
}

final class CmsKbArticlesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsKbArticle(); $this->viewPrefix = 'admin/cms/kb-articles'; $this->routePrefix = 'admin/cms/kb-articles'; $this->entityName = 'cms_kb';
        $this->fields = [['name' => 'slug'], ['name' => 'title_en'], ['name' => 'title_ar'], ['name' => 'category', 'type' => 'fk', 'lookup' => 'kb_categories'], ['name' => 'content_en', 'type' => 'textarea'], ['name' => 'status', 'type' => 'select', 'options' => ['draft', 'published']]]; }
}

final class CmsHelpArticlesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsHelpArticle(); $this->viewPrefix = 'admin/cms/help-articles'; $this->routePrefix = 'admin/cms/help-articles'; $this->entityName = 'cms_help';
        $this->fields = [['name' => 'slug'], ['name' => 'title_en'], ['name' => 'title_ar'], ['name' => 'category', 'type' => 'fk', 'lookup' => 'help_categories'], ['name' => 'content_en', 'type' => 'textarea'], ['name' => 'status', 'type' => 'select', 'options' => ['draft', 'published']]]; }
}

final class CmsSystemStatusController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsSystemStatus(); $this->viewPrefix = 'admin/cms/system-status'; $this->routePrefix = 'admin/cms/system-status'; $this->entityName = 'cms_system_status';
        $this->fields = [['name' => 'component_en'], ['name' => 'component_ar'], ['name' => 'status', 'type' => 'select', 'options' => ['operational', 'degraded', 'outage']], ['name' => 'message_en', 'type' => 'textarea']]; }
}

final class CmsTeamMembersController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsTeamMember(); $this->viewPrefix = 'admin/cms/team'; $this->routePrefix = 'admin/cms/team'; $this->entityName = 'cms_team';
        $this->fields = [['name' => 'name_en'], ['name' => 'name_ar'], ['name' => 'position_en'], ['name' => 'position_ar'], ['name' => 'bio_en', 'type' => 'textarea'], ['name' => 'photo_path'], ['name' => 'sort_order', 'type' => 'number']]; }
}

final class CmsTimelineController extends \Rateb\App\Controllers\CrudController
{
    public function __construct() { $this->model = new CmsTimeline(); $this->viewPrefix = 'admin/cms/timeline'; $this->routePrefix = 'admin/cms/timeline'; $this->entityName = 'cms_timeline';
        $this->fields = [['name' => 'year_label'], ['name' => 'title_en'], ['name' => 'title_ar'], ['name' => 'body_en', 'type' => 'textarea'], ['name' => 'sort_order', 'type' => 'number']]; }
}

final class CmsContactController extends Controller
{
    public function index(): void
    {
        $settings = (new \Rateb\App\Models\CmsContactSettings())->all(1, 0);
        $this->view('admin/cms/contact', [
            'title' => __('cms_contact'),
            'item' => $settings[0] ?? null,
            'offices' => (new CmsOffice())->all(50, 0),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function save(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/cms/contact'));
            return;
        }
        $model = new \Rateb\App\Models\CmsContactSettings();
        $rows = $model->all(1, 0);
        $data = [
            'email' => (string) $this->input('email', ''),
            'phone' => (string) $this->input('phone', ''),
            'address_en' => (string) $this->input('address_en', ''),
            'address_ar' => (string) $this->input('address_ar', ''),
            'working_hours_en' => (string) $this->input('working_hours_en', ''),
            'working_hours_ar' => (string) $this->input('working_hours_ar', ''),
            'map_embed' => (string) $this->input('map_embed', ''),
        ];
        if (!empty($rows[0]['id'])) {
            $model->update((int) $rows[0]['id'], $data);
        } else {
            $model->create($data);
        }
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url('admin/cms/contact'));
    }
}

final class CmsOfficesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsOffice();
        $this->viewPrefix = 'admin/cms/offices';
        $this->routePrefix = 'admin/cms/offices';
        $this->entityName = 'cms_offices';
        $this->fields = [
            ['name' => 'name_en', 'label' => 'name_en'],
            ['name' => 'name_ar', 'label' => 'name_ar'],
            ['name' => 'address_en', 'label' => 'address_en', 'type' => 'textarea'],
            ['name' => 'address_ar', 'label' => 'address_ar', 'type' => 'textarea'],
            ['name' => 'phone', 'label' => 'phone'],
            ['name' => 'map_url', 'label' => 'map_url'],
            ['name' => 'sort_order', 'label' => 'sort_order', 'type' => 'number'],
        ];
    }
}

final class CmsFooterColumnsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new CmsFooterColumn();
        $this->viewPrefix = 'admin/cms/footer-columns';
        $this->routePrefix = 'admin/cms/footer-columns';
        $this->entityName = 'cms_footer_columns';
        $this->fields = [
            ['name' => 'title_en', 'label' => 'title_en'],
            ['name' => 'title_ar', 'label' => 'title_ar'],
            ['name' => 'links_lines', 'label' => 'cms_footer_links', 'type' => 'textarea'],
            ['name' => 'sort_order', 'label' => 'sort_order', 'type' => 'number'],
        ];
    }

    public function create(): void
    {
        $this->guardManage();
        $this->view($this->viewPrefix . '/form', $this->formExtras(null), $this->layout());
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->formExtras($item), $this->layout());
    }

    /** @param array<string, mixed>|null $item */
    private function formExtras(?array $item): array
    {
        if ($item && !empty($item['links_json'])) {
            $decoded = json_decode((string) $item['links_json'], true);
            if (is_array($decoded)) {
                $lines = [];
                foreach ($decoded as $link) {
                    if (!is_array($link)) {
                        continue;
                    }
                    $lines[] = ($link['label_en'] ?? $link['label'] ?? '') . '|' . ($link['url'] ?? '');
                }
                $item['links_lines'] = implode("\n", $lines);
            }
        }
        return [
            'title' => ($item ? __('edit') : __('create')) . ' ' . __('cms_footer_columns'),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        unset($data['links_lines']);
        $lines = trim((string) $this->input('links_lines', ''));
        $links = [];
        foreach (preg_split('/\r\n|\r|\n/', $lines) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '|') === false) {
                continue;
            }
            [$label, $url] = array_map('trim', explode('|', $line, 2));
            $links[] = ['label_en' => $label, 'label_ar' => $label, 'url' => $url];
        }
        $data['links_json'] = json_encode($links, JSON_UNESCAPED_UNICODE);
        return $data;
    }
}

final class CmsPageBuilderController extends Controller
{
    public function index(): void
    {
        $pageSlug = trim((string) $this->input('page', 'home'));
        $cms = new CmsService();
        $pages = (new CmsPage())->all(100, 0);
        $this->view('admin/cms/page-builder/index', [
            'title' => __('cms_page_builder'),
            'pageSlug' => $pageSlug,
            'pages' => $pages,
            'content' => $cms->pageContent($pageSlug),
            'previewUrl' => $pageSlug === 'home' ? rateb_url('site') : rateb_url('site/' . $pageSlug),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function reorder(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false, 'message' => 'CSRF'], 403);
            return;
        }
        $sections = $this->input('sections', []);
        $blocks = $this->input('blocks', []);
        if (is_array($sections)) {
            $stmt = \Rateb\App\Core\Database::connection()->prepare(
                'UPDATE rateb_cms_sections SET sort_order = :o WHERE id = :id'
            );
            foreach ($sections as $order => $id) {
                $stmt->execute(['o' => (int) $order, 'id' => (int) $id]);
            }
        }
        if (is_array($blocks)) {
            $stmt = \Rateb\App\Core\Database::connection()->prepare(
                'UPDATE rateb_cms_blocks SET sort_order = :o WHERE id = :id'
            );
            foreach ($blocks as $order => $id) {
                $stmt->execute(['o' => (int) $order, 'id' => (int) $id]);
            }
        }
        SessionManager::flash('success', __('cms_reorder_ok'));
        $page = (string) $this->input('page_slug', 'home');
        $this->redirect(rateb_url('admin/cms/page-builder?page=' . rawurlencode($page)));
    }
}
