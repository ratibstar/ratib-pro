<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Website\WebsiteBlockRegistry;
use Rateb\App\Website\WebsiteBuilderService;
use Rateb\App\Website\WebsiteContext;
use Rateb\App\Website\WebsiteFormService;
use Rateb\App\Website\WebsiteMediaManagerService;
use Rateb\App\Website\WebsiteMenuBuilderService;
use Rateb\App\Website\WebsiteSeoEditorService;
use Rateb\App\Website\WebsiteThemeEditorService;
use Rateb\App\Website\WebsiteVersionService;

/**
 * Phase WEBSITE-04 — Company-scoped website builder (ERP shell; public site stays on WebsiteKernel).
 */
trait WebsiteBuilderBoot
{
    protected function bootWebsite(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        WebsiteContext::bootForOps();
    }

    protected function websiteAssets(): void
    {
        // Hook for layout; CSS/JS linked from views (no inline).
    }
}

final class WebsiteDashboardController extends Controller
{
    use WebsiteBuilderBoot;

    public function index(): void
    {
        $this->bootWebsite();
        $builder = new WebsiteBuilderService();
        $pages = $builder->pages();
        $this->view('company/website/dashboard', [
            'title' => __('website') ?: 'Website',
            'pages' => $pages,
            'companyId' => $builder->companyId(),
            'blockTypes' => WebsiteBlockRegistry::all(),
        ], 'main');
    }
}

final class WebsitePagesController extends Controller
{
    use WebsiteBuilderBoot;

    public function index(): void
    {
        $this->bootWebsite();
        $builder = new WebsiteBuilderService();
        $this->view('company/website/pages/index', [
            'title' => __('website_pages') ?: 'Pages',
            'pages' => $builder->pages(),
        ], 'main');
    }

    public function create(): void
    {
        $this->bootWebsite();
        $this->view('company/website/pages/form', [
            'title' => __('website_page_create') ?: 'Create page',
            'page' => null,
            'action' => rateb_url(rateb_app_route('website/pages')),
        ], 'main');
    }

    public function store(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('website/pages')));
        }
        $id = (new WebsiteBuilderService())->savePage($_POST);
        SessionManager::flash('success', __('saved_ok'));
        $this->redirect(rateb_url(rateb_app_route('website/builder') . '?page_id=' . $id));
    }

    public function edit(array $params): void
    {
        $this->bootWebsite();
        $id = (int) ($params['id'] ?? 0);
        $page = (new WebsiteBuilderService())->pageById($id);
        if ($page === null) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url(rateb_app_route('website/pages')));
        }
        $this->view('company/website/pages/form', [
            'title' => __('website_page_edit') ?: 'Edit page',
            'page' => $page,
            'action' => rateb_url(rateb_app_route('website/pages/' . $id)),
            'seo' => (new \Rateb\App\Website\TenantSeoService())->seoRow((string) $page['slug']),
        ], 'main');
    }

    public function update(array $params): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('website/pages')));
        }
        $id = (int) ($params['id'] ?? 0);
        (new WebsiteBuilderService())->savePage($_POST, $id);
        if (!empty($_POST['seo']) && is_array($_POST['seo'])) {
            $page = (new WebsiteBuilderService())->pageById($id);
            if ($page) {
                (new WebsiteSeoEditorService())->saveForSlug((string) $page['slug'], $_POST['seo']);
            }
        }
        SessionManager::flash('success', __('saved_ok'));
        $this->redirect(rateb_url(rateb_app_route('website/pages/' . $id . '/edit')));
    }

    public function destroy(array $params): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('website/pages')));
        }
        (new WebsiteBuilderService())->deletePage((int) ($params['id'] ?? 0));
        SessionManager::flash('success', __('deleted_ok') ?: 'Deleted');
        $this->redirect(rateb_url(rateb_app_route('website/pages')));
    }
}

final class WebsiteBuilderController extends Controller
{
    use WebsiteBuilderBoot;

    public function index(): void
    {
        $this->bootWebsite();
        $builder = new WebsiteBuilderService();
        $pageId = (int) ($_GET['page_id'] ?? 0);
        $pages = $builder->pages();
        $page = $pageId > 0 ? $builder->pageById($pageId) : ($pages[0] ?? null);
        if ($page === null) {
            SessionManager::flash('info', __('website_create_page_first') ?: 'Create a page first');
            $this->redirect(rateb_url(rateb_app_route('website/pages/create')));
        }
        $slug = (string) $page['slug'];
        $versions = (new WebsiteVersionService())->versionsForPage((int) $page['id']);
        $this->view('company/website/builder/index', [
            'title' => __('website_builder') ?: 'Website builder',
            'page' => $page,
            'pages' => $pages,
            'tree' => $builder->builderTree($slug),
            'blockTypes' => WebsiteBlockRegistry::all(),
            'library' => $builder->library(),
            'versions' => $versions,
            'csrf' => Csrf::token(),
            'previewBase' => rateb_url('site/preview'),
            'saveReorderUrl' => rateb_url(rateb_app_route('website/builder/reorder')),
            'addSectionUrl' => rateb_url(rateb_app_route('website/builder/section')),
            'addBlockUrl' => rateb_url(rateb_app_route('website/builder/block')),
            'updateBlockUrl' => rateb_url(rateb_app_route('website/builder/block/update')),
            'deleteBlockUrl' => rateb_url(rateb_app_route('website/builder/block/delete')),
            'deleteSectionUrl' => rateb_url(rateb_app_route('website/builder/section/delete')),
            'publishUrl' => rateb_url(rateb_app_route('website/builder/publish')),
            'draftUrl' => rateb_url(rateb_app_route('website/builder/draft')),
            'previewUrl' => rateb_url(rateb_app_route('website/builder/preview')),
            'rollbackUrl' => rateb_url(rateb_app_route('website/builder/rollback')),
            'scheduleUrl' => rateb_url(rateb_app_route('website/builder/schedule')),
        ], 'main');
    }

    public function reorder(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false, 'message' => 'CSRF'], 403);
            return;
        }
        $sections = $_POST['sections'] ?? [];
        $blocks = $_POST['blocks'] ?? [];
        $bySection = [];
        if (is_array($blocks)) {
            foreach ($blocks as $sectionId => $ids) {
                $bySection[(int) $sectionId] = is_array($ids) ? array_map('intval', $ids) : [];
            }
        }
        (new WebsiteBuilderService())->reorder(
            is_array($sections) ? array_map('intval', $sections) : [],
            $bySection
        );
        Response::json(['ok' => true]);
    }

    public function addSection(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $slug = (string) ($_POST['page_slug'] ?? '');
        $id = (new WebsiteBuilderService())->addSection($slug, $_POST);
        Response::json(['ok' => true, 'id' => $id]);
    }

    public function deleteSection(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        (new WebsiteBuilderService())->deleteSection((int) ($_POST['id'] ?? 0));
        Response::json(['ok' => true]);
    }

    public function addBlock(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $type = (string) ($_POST['block_type'] ?? 'text');
        $id = (new WebsiteBuilderService())->addBlock($sectionId, $type, $_POST);
        Response::json(['ok' => true, 'id' => $id]);
    }

    public function updateBlock(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $data = $_POST;
        if (isset($data['settings']) && is_string($data['settings'])) {
            $decoded = json_decode($data['settings'], true);
            if (is_array($decoded)) {
                $data['settings'] = $decoded;
            }
        }
        (new WebsiteBuilderService())->updateBlock($id, $data);
        Response::json(['ok' => true]);
    }

    public function deleteBlock(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        (new WebsiteBuilderService())->deleteBlock((int) ($_POST['id'] ?? 0));
        Response::json(['ok' => true]);
    }

    public function saveDraft(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $pageId = (int) ($_POST['page_id'] ?? 0);
        $id = (new WebsiteVersionService())->saveDraft($pageId, (string) ($_POST['label'] ?? 'Draft'));
        Response::json(['ok' => true, 'version_id' => $id]);
    }

    public function publish(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $pageId = (int) ($_POST['page_id'] ?? 0);
        $versionId = isset($_POST['version_id']) ? (int) $_POST['version_id'] : null;
        $id = (new WebsiteVersionService())->publish($pageId, $versionId);
        Response::json(['ok' => true, 'version_id' => $id]);
    }

    public function schedule(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $id = (new WebsiteVersionService())->schedule(
            (int) ($_POST['page_id'] ?? 0),
            (string) ($_POST['scheduled_at'] ?? ''),
            isset($_POST['version_id']) ? (int) $_POST['version_id'] : null
        );
        Response::json(['ok' => true, 'version_id' => $id]);
    }

    public function rollback(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        (new WebsiteVersionService())->rollback(
            (int) ($_POST['page_id'] ?? 0),
            (int) ($_POST['version_id'] ?? 0)
        );
        Response::json(['ok' => true]);
    }

    public function preview(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $token = (new WebsiteVersionService())->createPreviewToken(
            (int) ($_POST['page_id'] ?? 0),
            isset($_POST['version_id']) ? (int) $_POST['version_id'] : null
        );
        Response::json(['ok' => true, 'url' => rateb_url('site/preview/' . $token)]);
    }
}

final class WebsiteThemeController extends Controller
{
    use WebsiteBuilderBoot;

    public function edit(): void
    {
        $this->bootWebsite();
        $editor = new WebsiteThemeEditorService();
        $this->view('company/website/theme/edit', [
            'title' => __('website_theme') ?: 'Theme',
            'tokens' => $editor->tokens(),
            'theme' => (new \Rateb\App\Website\TenantThemeService())->theme(),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function save(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('website/theme')));
        }
        $tokens = $_POST['tokens'] ?? [];
        if (is_string($tokens)) {
            $tokens = json_decode($tokens, true) ?: [];
        }
        (new WebsiteThemeEditorService())->save(is_array($tokens) ? $tokens : [], $_POST);
        SessionManager::flash('success', __('saved_ok'));
        $this->redirect(rateb_url(rateb_app_route('website/theme')));
    }
}

final class WebsiteMediaController extends Controller
{
    use WebsiteBuilderBoot;

    public function index(): void
    {
        $this->bootWebsite();
        $folderId = isset($_GET['folder']) ? (int) $_GET['folder'] : null;
        $mgr = new WebsiteMediaManagerService();
        $this->view('company/website/media/index', [
            'title' => __('website_media') ?: 'Media',
            'folders' => $mgr->folders($folderId),
            'media' => $mgr->listMedia($folderId),
            'folderId' => $folderId,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function upload(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $folderId = isset($_POST['folder_id']) ? (int) $_POST['folder_id'] : null;
        $result = (new WebsiteMediaManagerService())->upload($_FILES['file'] ?? [], $folderId, (int) (SessionManager::get('rateb_user_id') ?? 0) ?: null);
        Response::json($result, !empty($result['ok']) ? 200 : 400);
    }

    public function createFolder(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $id = (new WebsiteMediaManagerService())->createFolder(
            (string) ($_POST['name'] ?? 'Folder'),
            isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : null
        );
        Response::json(['ok' => true, 'id' => $id]);
    }
}

final class WebsiteMenusController extends Controller
{
    use WebsiteBuilderBoot;

    public function index(): void
    {
        $this->bootWebsite();
        $menus = new WebsiteMenuBuilderService();
        $menuId = (int) ($_GET['menu_id'] ?? 0);
        $all = $menus->menus();
        $current = $menuId > 0 ? null : ($all[0] ?? null);
        foreach ($all as $m) {
            if ((int) $m['id'] === $menuId) {
                $current = $m;
                break;
            }
        }
        $this->view('company/website/menus/index', [
            'title' => __('website_menus') ?: 'Menus',
            'menus' => $all,
            'current' => $current,
            'items' => $current ? $menus->items((int) $current['id']) : [],
            'tree' => $current ? $menus->tree((int) $current['id']) : [],
            'footerColumns' => $menus->footerColumns(),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function saveItems(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $menuId = (int) ($_POST['menu_id'] ?? 0);
        $items = $_POST['items'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }
        (new WebsiteMenuBuilderService())->replaceItems($menuId, is_array($items) ? $items : []);
        Response::json(['ok' => true]);
    }

    public function saveFooter(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false], 403);
            return;
        }
        $cols = $_POST['columns'] ?? [];
        if (is_string($cols)) {
            $cols = json_decode($cols, true) ?: [];
        }
        (new WebsiteMenuBuilderService())->saveFooterColumns(is_array($cols) ? $cols : []);
        Response::json(['ok' => true]);
    }
}

final class WebsiteFormsController extends Controller
{
    use WebsiteBuilderBoot;

    public function index(): void
    {
        $this->bootWebsite();
        $this->view('company/website/forms/index', [
            'title' => __('website_forms') ?: 'Forms',
            'forms' => (new WebsiteFormService())->listForms(),
        ], 'main');
    }

    public function create(): void
    {
        $this->bootWebsite();
        $this->view('company/website/forms/form', [
            'title' => __('website_form_create') ?: 'Create form',
            'form' => null,
            'fields' => [],
            'action' => rateb_url(rateb_app_route('website/forms')),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('website/forms')));
        }
        $fields = $_POST['fields'] ?? [];
        if (is_string($fields)) {
            $fields = json_decode($fields, true) ?: [];
        }
        $id = (new WebsiteFormService())->saveForm($_POST, is_array($fields) ? $fields : []);
        SessionManager::flash('success', __('saved_ok'));
        $this->redirect(rateb_url(rateb_app_route('website/forms/' . $id . '/edit')));
    }

    public function edit(array $params): void
    {
        $this->bootWebsite();
        $id = (int) ($params['id'] ?? 0);
        $svc = new WebsiteFormService();
        $form = $svc->find($id);
        if ($form === null) {
            SessionManager::flash('error', __('not_found'));
            $this->redirect(rateb_url(rateb_app_route('website/forms')));
        }
        $this->view('company/website/forms/form', [
            'title' => __('website_form_edit') ?: 'Edit form',
            'form' => $form,
            'fields' => $svc->fieldsForForm($id),
            'action' => rateb_url(rateb_app_route('website/forms/' . $id)),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        $this->bootWebsite();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('website/forms')));
        }
        $id = (int) ($params['id'] ?? 0);
        $fields = $_POST['fields'] ?? [];
        if (is_string($fields)) {
            $fields = json_decode($fields, true) ?: [];
        }
        (new WebsiteFormService())->saveForm($_POST, is_array($fields) ? $fields : [], $id);
        SessionManager::flash('success', __('saved_ok'));
        $this->redirect(rateb_url(rateb_app_route('website/forms/' . $id . '/edit')));
    }
}
