<?php
declare(strict_types=1);

namespace Rateb\App\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Model;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuditService;

abstract class CrudController extends Controller
{
    protected Model $model;
    protected string $viewPrefix;
    protected string $routePrefix;
    protected array $fields = [];
    protected string $entityName = 'record';

    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $this->view($this->viewPrefix . '/index', [
            'title' => __($this->entityName),
            'items' => $this->model->all($limit, $offset),
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function create(): void
    {
        $this->view($this->viewPrefix . '/form', [
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $data = $this->collectData();
        $id = $this->model->create($data);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function edit(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }

        $this->view($this->viewPrefix . '/form', [
            'title' => __('edit') . ' ' . __($this->entityName),
            'item' => $item,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields,
            'csrf' => Csrf::token(),
        ], $this->layout());
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        $this->model->update($id, $data);
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function destroy(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $id = (int) ($params['id'] ?? 0);
        $this->model->delete($id);
        (new AuditService())->log('delete', $this->entityName, $id);
        SessionManager::flash('success', __('delete') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function collectData(): array
    {
        $data = [];
        foreach ($this->fields as $field) {
            $name = $field['name'];
            $data[$name] = trim((string) $this->input($name, ''));
        }
        return $data;
    }

    protected function layout(): string
    {
        return 'main';
    }
}
