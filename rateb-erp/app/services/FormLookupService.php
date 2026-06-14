<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Helpers\LineItems;
use Rateb\App\Models\Asset;
use Rateb\App\Models\BankAccount;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\CmsBlogAuthor;
use Rateb\App\Models\CmsBlogCategory;
use Rateb\App\Models\CmsFaqCategory;
use Rateb\App\Models\CmsMenu;
use Rateb\App\Models\CmsPage;
use Rateb\App\Models\CmsSection;
use Rateb\App\Models\CmsServiceCategory;
use Rateb\App\Models\Contract;
use Rateb\App\Models\CostCenter;
use Rateb\App\Models\FiscalPeriod;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\ProductCategory;
use Rateb\App\Models\Rfq;
use Rateb\App\Models\Supplier;
use Rateb\App\Models\SupplierClassification;
use Rateb\App\Models\Warehouse;

/** @phpstan-type FormOption array{value: string|int, label: string} */
final class FormLookupService
{
    /** @var array<string, list<FormOption>> */
    private array $cache = [];

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, list<FormOption>>
     */
    public function forFields(array $fields): array
    {
        $needed = [];
        foreach ($fields as $field) {
            $lookup = (string) ($field['lookup'] ?? '');
            if ($lookup !== '') {
                $needed[$lookup] = true;
            }
        }
        $out = [];
        foreach (array_keys($needed) as $key) {
            $out[$key] = $this->get($key);
        }
        return $out;
    }

    /** @return list<FormOption> */
    public function get(string $lookup): array
    {
        if (isset($this->cache[$lookup])) {
            return $this->cache[$lookup];
        }
        switch ($lookup) {
            case 'companies':
                $options = $this->mapRows((new BillingService())->companyOptions(), 'id', 'name');
                break;
            case 'suppliers':
                $options = $this->mapRows((new Supplier())->all(500, 0), 'id', 'name');
                break;
            case 'warehouses':
                $options = $this->warehouseOptions();
                break;
            case 'cost_centers':
                $options = $this->costCenterOptions();
                break;
            case 'inventory':
                $options = $this->inventoryOptions();
                break;
            case 'product_categories':
                $options = $this->mapRows((new ProductCategory())->all(300, 0), 'id', 'name');
                break;
            case 'supplier_classifications':
                $options = $this->mapRows((new SupplierClassification())->all(200, 0), 'id', 'name');
                break;
            case 'rfq':
                $options = $this->rfqOptions();
                break;
            case 'contracts':
                $options = $this->contractOptions();
                break;
            case 'assets':
                $options = $this->assetOptions();
                break;
            case 'chart_of_accounts':
                $options = $this->coaOptions();
                break;
            case 'bank_accounts':
                $options = $this->mapRows((new BankAccount())->all(200, 0), 'id', 'name');
                break;
            case 'fiscal_periods':
                $options = $this->mapRows((new FiscalPeriod())->all(100, 0), 'id', 'name');
                break;
            case 'departments':
                $options = $this->departmentOptions();
                break;
            case 'permission_modules':
                $options = $this->moduleOptions();
                break;
            case 'asset_categories':
                $options = $this->assetCategoryOptions();
                break;
            case 'units':
                $options = $this->unitOptions();
                break;
            case 'currencies':
                $options = $this->staticOptions(['SAR', 'USD', 'EUR']);
                break;
            case 'locales':
                $options = $this->staticOptions(['ar', 'en'], true);
                break;
            case 'page_templates':
                $options = $this->staticOptions(['default', 'landing', 'blog', 'contact'], true);
                break;
            case 'contract_types':
                $options = $this->staticOptions(['supply', 'service', 'lease', 'maintenance', 'other'], true);
                break;
            case 'approval_statuses':
                $options = $this->staticOptions(['pending', 'approved', 'rejected'], true);
                break;
            case 'maintenance_types':
                $options = $this->staticOptions(['preventive', 'corrective', 'calibration', 'inspection'], true);
                break;
            case 'regulatory_statuses':
                $options = $this->staticOptions(['compliant', 'pending', 'non_compliant'], true);
                break;
            case 'payment_methods':
                $options = $this->staticOptions(['bank_transfer', 'card', 'cash', 'cheque', 'online'], true);
                break;
            case 'redirect_status_codes':
                $options = $this->staticOptions(['301', '302', '307', '308']);
                break;
            case 'cms_pages':
                $options = $this->cmsPageOptions();
                break;
            case 'cms_sections':
                $options = $this->mapRows((new CmsSection())->all(500, 0), 'id', 'section_key');
                break;
            case 'cms_menus':
                $options = $this->mapRows((new CmsMenu())->all(100, 0), 'id', 'name_en');
                break;
            case 'cms_faq_categories':
                $options = $this->mapRows((new CmsFaqCategory())->all(200, 0), 'id', 'name_en');
                break;
            case 'cms_blog_categories':
                $options = $this->mapRows((new CmsBlogCategory())->all(200, 0), 'id', 'name_en');
                break;
            case 'cms_blog_authors':
                $options = $this->mapRows((new CmsBlogAuthor())->all(200, 0), 'id', 'name_en');
                break;
            case 'cms_service_categories':
                $options = $this->mapRows((new CmsServiceCategory())->all(200, 0), 'id', 'name_en');
                break;
            case 'kb_categories':
                $options = $this->distinctCategoryOptions('rateb_cms_kb_articles', 'category');
                break;
            case 'help_categories':
                $options = $this->distinctCategoryOptions('rateb_cms_help_articles', 'category');
                break;
            case 'career_departments':
                $options = $this->distinctCategoryOptions('rateb_cms_careers', 'department_en');
                break;
            case 'newsletter_segments':
                $options = $this->distinctCategoryOptions('rateb_cms_newsletter_segments', 'name_en');
                break;
            case 'setting_groups':
                $options = $this->staticOptions(['general', 'billing', 'email', 'sms', 'security', 'cms'], true);
                break;
            case 'pr_statuses':
                $options = $this->staticOptions(['draft', 'submitted', 'approved', 'rejected', 'cancelled'], true);
                break;
            case 'po_statuses':
                $options = $this->staticOptions(['draft', 'sent', 'confirmed', 'partial', 'received', 'cancelled'], true);
                break;
            case 'rfq_statuses':
                $options = $this->staticOptions(['draft', 'published', 'closed', 'awarded', 'cancelled'], true);
                break;
            case 'inventory_statuses':
                $options = $this->staticOptions(['active', 'inactive', 'expired'], true);
                break;
            case 'evaluation_statuses':
                $options = $this->staticOptions(['draft', 'published', 'archived'], true);
                break;
            default:
                $options = [];
        }
        $this->cache[$lookup] = $options;
        return $options;
    }

    /** @return list<FormOption> */
    private function warehouseOptions(): array
    {
        $out = [];
        foreach ((new Warehouse())->all(300, 0) as $row) {
            $label = trim((string) ($row['code'] ?? '')) !== ''
                ? ($row['code'] . ' — ' . ($row['name'] ?? ''))
                : (string) ($row['name'] ?? '');
            $out[] = ['value' => (int) $row['id'], 'label' => $label];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function costCenterOptions(): array
    {
        $out = [];
        foreach ((new CostCenter())->all(300, 0) as $row) {
            $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : ($row['name'] ?? '');
            $out[] = ['value' => (int) $row['id'], 'label' => trim(($row['code'] ?? '') . ' — ' . $name)];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function inventoryOptions(): array
    {
        $out = [];
        foreach ((new Inventory())->all(500, 0) as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $label = $sku !== '' ? ($sku . ' — ' . ($row['item_name'] ?? '')) : (string) ($row['item_name'] ?? '');
            $out[] = ['value' => (int) $row['id'], 'label' => $label];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function rfqOptions(): array
    {
        $out = [];
        foreach ((new Rfq())->all(300, 0) as $row) {
            $out[] = ['value' => (int) $row['id'], 'label' => trim(($row['rfq_no'] ?? '') . ' — ' . ($row['title'] ?? ''))];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function contractOptions(): array
    {
        $out = [];
        foreach ((new Contract())->all(300, 0) as $row) {
            $out[] = ['value' => (int) $row['id'], 'label' => trim(($row['contract_no'] ?? '') . ' — ' . ($row['title'] ?? ''))];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function assetOptions(): array
    {
        $out = [];
        foreach ((new Asset())->all(300, 0) as $row) {
            $out[] = ['value' => (int) $row['id'], 'label' => trim(($row['asset_tag'] ?? '') . ' — ' . ($row['name'] ?? ''))];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function coaOptions(): array
    {
        $out = [];
        foreach ((new ChartOfAccount())->all(500, 0) as $row) {
            $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : ($row['name'] ?? '');
            $out[] = ['value' => (int) $row['id'], 'label' => trim(($row['code'] ?? '') . ' — ' . $name)];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function departmentOptions(): array
    {
        $out = [];
        foreach ((new ProcurementService())->departmentOptions() as $dept) {
            $out[] = ['value' => $dept, 'label' => $dept];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function moduleOptions(): array
    {
        $out = [];
        foreach (array_keys(PlanLimitService::moduleCatalog()) as $module) {
            $out[] = ['value' => $module, 'label' => __($module)];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function assetCategoryOptions(): array
    {
        $preset = ['equipment', 'furniture', 'vehicle', 'it', 'medical', 'building', 'other'];
        $cid = TenantContext::companyId() ?? 0;
        $distinct = [];
        if ($cid > 0) {
            $rows = (new Asset())->query(
                'SELECT DISTINCT category FROM rateb_assets WHERE company_id = :cid AND category IS NOT NULL AND category <> \'\' ORDER BY category',
                ['cid' => $cid]
            );
            foreach ($rows as $row) {
                $distinct[] = (string) $row['category'];
            }
        }
        $merged = array_values(array_unique(array_merge($preset, $distinct)));
        return $this->staticOptions($merged, true);
    }

    /** @return list<FormOption> */
    private function unitOptions(): array
    {
        $out = [];
        foreach (LineItems::unitOptions() as $unit) {
            $out[] = ['value' => $unit, 'label' => __('unit_' . $unit)];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function cmsPageOptions(): array
    {
        $out = [];
        foreach ((new CmsPage())->all(300, 0) as $row) {
            $slug = (string) ($row['slug'] ?? '');
            $title = rateb_locale() === 'ar' ? ($row['title_ar'] ?? $row['title_en'] ?? $slug) : ($row['title_en'] ?? $slug);
            $out[] = ['value' => $slug, 'label' => $slug . ' — ' . $title];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function distinctCategoryOptions(string $table, string $column): array
    {
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->query(
            'SELECT DISTINCT `' . preg_replace('/[^a-z_]/', '', $column) . '` AS cat
             FROM `' . preg_replace('/[^a-z_]/', '', $table) . '`
             WHERE `' . preg_replace('/[^a-z_]/', '', $column) . '` IS NOT NULL
               AND `' . preg_replace('/[^a-z_]/', '', $column) . '` <> \'\'
             ORDER BY cat LIMIT 200'
        );
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $val = (string) ($row['cat'] ?? '');
            if ($val !== '') {
                $out[] = ['value' => $val, 'label' => $val];
            }
        }
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<FormOption>
     */
    private function mapRows(array $rows, string $valueKey, string $labelKey): array
    {
        $out = [];
        foreach ($rows as $row) {
            $label = (string) ($row[$labelKey] ?? '');
            if ($label === '' && isset($row['id'])) {
                $label = '#' . $row['id'];
            }
            $out[] = ['value' => $row[$valueKey] ?? '', 'label' => $label];
        }
        return $out;
    }

    /**
     * @param list<string> $values
     * @return list<FormOption>
     */
    private function staticOptions(array $values, bool $translate = false): array
    {
        $out = [];
        foreach ($values as $value) {
            $out[] = ['value' => $value, 'label' => $translate ? __($value) : $value];
        }
        return $out;
    }
}
