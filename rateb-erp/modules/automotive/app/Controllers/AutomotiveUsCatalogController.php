<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveUsCatalogService;

final class AutomotiveUsCatalogController extends AutomotiveBaseController
{
    public function epa(): void
    {
        $this->guardView();
        $result = (new AutomotiveUsCatalogService())->epa(
            (int) $this->input('year', 0),
            (string) $this->input('make', ''),
            (string) $this->input('model', ''),
            (string) $this->input('fuel', ''),
            (string) $this->input('class', ''),
            max(1, (int) $this->input('page', 1))
        );
        $this->automotiveView('us-catalog/epa', [
            'title' => __('auto_us_epa'),
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'year' => (string) $this->input('year', ''),
            'make' => (string) $this->input('make', ''),
            'model' => (string) $this->input('model', ''),
            'fuel' => (string) $this->input('fuel', ''),
            'class' => (string) $this->input('class', ''),
        ]);
    }

    public function show(int $id): void
    {
        $this->guardView();
        $this->automotiveView('us-catalog/show', [
            'title' => __('auto_us_epa'),
            'row' => (new AutomotiveUsCatalogService())->epaDetail($id),
        ]);
    }

    public function nhtsa(): void
    {
        $this->guardView();
        $result = (new AutomotiveUsCatalogService())->nhtsaModels(
            (string) $this->input('make', ''),
            (string) $this->input('model', ''),
            max(1, (int) $this->input('page', 1))
        );
        $this->automotiveView('us-catalog/nhtsa', [
            'title' => __('auto_us_nhtsa'),
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'make' => (string) $this->input('make', ''),
            'model' => (string) $this->input('model', ''),
        ]);
    }
}
