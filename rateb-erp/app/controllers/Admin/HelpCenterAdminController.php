<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\Help\HelpCenterRepository;
use Rateb\App\Services\Help\HelpContentBuilder;

/**
 * Help Center content management architecture (file catalog today; DB-ready tables via migration 258).
 * Full CRUD UI can expand against rateb_help_* tables without changing end-user routes.
 */
final class HelpCenterAdminController extends Controller
{
    public function index(): void
    {
        $repo = new HelpCenterRepository();
        if (!$repo->gate()->canManageContent()) {
            SessionManager::flash('error', __('help_admin_forbidden'));
            $this->redirect(rateb_url('admin/help'));

            return;
        }

        $modules = HelpContentBuilder::modules();
        $articles = HelpContentBuilder::articles();
        $this->view('help/admin/index', [
            'title' => __('help_admin_title'),
            'modules' => $modules,
            'articles' => $articles,
            'articleCount' => count($articles),
            'moduleCount' => count($modules),
            'helpHomeUrl' => rateb_url('admin/help'),
            'architectureNote' => true,
        ], 'main');
    }
}
