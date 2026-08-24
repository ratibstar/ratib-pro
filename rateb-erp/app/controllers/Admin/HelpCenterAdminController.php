<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\HelpArticle;
use Rateb\App\Services\Help\HelpAnalyticsService;
use Rateb\App\Services\Help\HelpCenterRepository;
use Rateb\App\Services\Help\HelpContentBuilder;

/**
 * Help Center admin: manage DB articles + analytics for the AI assistant.
 */
final class HelpCenterAdminController extends Controller
{
    public function index(): void
    {
        if (!$this->guard()) {
            return;
        }
        $dbArticles = $this->safeListDb();
        $fileArticles = HelpContentBuilder::articles();
        $this->view('help/admin/index', [
            'title' => __('help_admin_title'),
            'modules' => HelpContentBuilder::modules(),
            'fileArticles' => $fileArticles,
            'dbArticles' => $dbArticles,
            'articleCount' => count($fileArticles) + count($dbArticles),
            'moduleCount' => count(HelpContentBuilder::modules()),
            'helpHomeUrl' => rateb_url('admin/help'),
            'csrf' => Csrf::token(),
            'canManage' => true,
        ], 'main');
    }

    public function create(): void
    {
        if (!$this->guard()) {
            return;
        }
        $this->view('help/admin/form', [
            'title' => __('help_admin_create'),
            'article' => null,
            'modules' => HelpContentBuilder::modules(),
            'csrf' => Csrf::token(),
            'action' => rateb_url('admin/help/manage/store'),
            'helpHomeUrl' => rateb_url('admin/help'),
        ], 'main');
    }

    public function edit(string $id): void
    {
        if (!$this->guard()) {
            return;
        }
        $article = (new HelpArticle())->find((int) $id);
        if (!$article) {
            SessionManager::flash('error', __('help_admin_not_found'));
            $this->redirect(rateb_url('admin/help/manage'));

            return;
        }
        $this->view('help/admin/form', [
            'title' => __('help_admin_edit'),
            'article' => $article,
            'modules' => HelpContentBuilder::modules(),
            'csrf' => Csrf::token(),
            'action' => rateb_url('admin/help/manage/update/' . (int) $id),
            'helpHomeUrl' => rateb_url('admin/help'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->guard() || !$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/help/manage'));

            return;
        }
        $data = $this->readForm();
        if ($data['slug'] === '' || $data['title_ar'] === '') {
            SessionManager::flash('error', __('help_admin_validation'));
            $this->redirect(rateb_url('admin/help/manage/create'));

            return;
        }
        try {
            (new HelpArticle())->create($data);
            SessionManager::flash('success', __('help_admin_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', __('help_admin_save_failed'));
        }
        $this->redirect(rateb_url('admin/help/manage'));
    }

    public function update(string $id): void
    {
        if (!$this->guard() || !$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/help/manage'));

            return;
        }
        $data = $this->readForm();
        try {
            (new HelpArticle())->update((int) $id, $data);
            SessionManager::flash('success', __('help_admin_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', __('help_admin_save_failed'));
        }
        $this->redirect(rateb_url('admin/help/manage'));
    }

    public function archive(string $id): void
    {
        if (!$this->guard() || !$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/help/manage'));

            return;
        }
        try {
            (new HelpArticle())->update((int) $id, ['status' => 'archived']);
            SessionManager::flash('success', __('help_admin_archived'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', __('help_admin_save_failed'));
        }
        $this->redirect(rateb_url('admin/help/manage'));
    }

    public function analytics(): void
    {
        if (!$this->guard()) {
            return;
        }
        $report = (new HelpAnalyticsService())->report(40);
        $this->view('help/admin/analytics', [
            'title' => __('help_admin_analytics'),
            'report' => $report,
            'helpHomeUrl' => rateb_url('admin/help'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function resolveUnanswered(string $id): void
    {
        if (!$this->guard() || !$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/help/manage/analytics'));

            return;
        }
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('UPDATE rateb_help_unanswered SET status = ? WHERE id = ?');
            $stmt->execute([(string) $this->input('status', 'resolved'), (int) $id]);
            SessionManager::flash('success', __('help_admin_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', __('help_admin_save_failed'));
        }
        $this->redirect(rateb_url('admin/help/manage/analytics'));
    }

    private function guard(): bool
    {
        $repo = new HelpCenterRepository();
        if (!$repo->gate()->canManageContent()) {
            SessionManager::flash('error', __('help_admin_forbidden'));
            $this->redirect(rateb_url('admin/help'));

            return false;
        }

        return true;
    }

    /** @return list<array<string,mixed>> */
    private function safeListDb(): array
    {
        try {
            return (new HelpArticle())->all(200, 0);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<string,mixed> */
    private function readForm(): array
    {
        $slug = strtolower(trim((string) $this->input('slug', '')));
        $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug) ?? '';
        $keywords = array_values(array_filter(array_map('trim', preg_split('/[,،]+/u', (string) $this->input('keywords', '')) ?: [])));
        $related = array_values(array_filter(array_map('trim', preg_split('/[,،\s]+/u', (string) $this->input('related', '')) ?: [])));
        $stepsAr = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", (string) $this->input('steps_ar', '')) ?: [])));
        $stepsEn = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", (string) $this->input('steps_en', '')) ?: [])));
        $bodyAr = json_encode([
            'what' => (string) $this->input('what_ar', ''),
            'when' => (string) $this->input('when_ar', ''),
            'steps' => $stepsAr,
            'example' => (string) $this->input('example_ar', ''),
            'tips' => [],
            'mistakes' => [],
        ], JSON_UNESCAPED_UNICODE);
        $bodyEn = json_encode([
            'what' => (string) $this->input('what_en', ''),
            'when' => (string) $this->input('when_en', ''),
            'steps' => $stepsEn,
            'example' => (string) $this->input('example_en', ''),
            'tips' => [],
            'mistakes' => [],
        ], JSON_UNESCAPED_UNICODE);

        return [
            'module_slug' => (string) $this->input('module_slug', ''),
            'slug' => $slug,
            'title_ar' => trim((string) $this->input('title_ar', '')),
            'title_en' => trim((string) $this->input('title_en', '')),
            'summary_ar' => trim((string) $this->input('summary_ar', '')),
            'summary_en' => trim((string) $this->input('summary_en', '')),
            'body_json_ar' => $bodyAr ?: null,
            'body_json_en' => $bodyEn ?: null,
            'difficulty' => in_array((string) $this->input('difficulty', 'beginner'), ['beginner', 'intermediate', 'advanced'], true)
                ? (string) $this->input('difficulty', 'beginner') : 'beginner',
            'minutes' => max(1, min(60, (int) $this->input('minutes', 3))),
            'icon' => trim((string) $this->input('icon', 'fa-circle-question')) ?: 'fa-circle-question',
            'audience' => in_array((string) $this->input('audience', 'all'), ['all', 'user', 'manager', 'admin'], true)
                ? (string) $this->input('audience', 'all') : 'all',
            'route_hint' => trim((string) $this->input('route_hint', '')),
            'keywords_json' => json_encode($keywords, JSON_UNESCAPED_UNICODE),
            'related_json' => json_encode($related, JSON_UNESCAPED_UNICODE),
            'sort_order' => (int) $this->input('sort_order', 0),
            'status' => in_array((string) $this->input('status', 'draft'), ['draft', 'published', 'archived'], true)
                ? (string) $this->input('status', 'draft') : 'draft',
        ];
    }
}
