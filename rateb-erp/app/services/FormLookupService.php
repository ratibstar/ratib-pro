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
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Rfq;
use Rateb\App\Models\Supplier;
use Rateb\App\Models\SupplierClassification;
use Rateb\App\Models\Tender;
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
        $this->bootstrapTenantForLookups();
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
        $this->bootstrapTenantForLookups();
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
            case 'inventory_movement_types':
                $options = [
                    ['value' => 'in', 'label' => __('movement_in')],
                    ['value' => 'out', 'label' => __('movement_out')],
                    ['value' => 'transfer', 'label' => __('movement_transfer')],
                    ['value' => 'adjustment', 'label' => __('movement_adjustment')],
                ];
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
                $options = $this->staticOptions([
                    'preventive', 'corrective', 'calibration', 'inspection', 'general',
                    'repair', 'upgrade', 'replacement', 'emergency', 'routine',
                ], true);
                break;
            case 'maintenance_statuses':
                $options = $this->staticOptions([
                    'scheduled', 'in_progress', 'completed', 'cancelled', 'overdue',
                ], true);
                break;
            case 'service_types':
                $options = $this->staticOptions([
                    'maintenance', 'repair', 'calibration', 'inspection', 'installation',
                    'upgrade', 'preventive', 'corrective', 'emergency',
                ], true);
                break;
            case 'renewal_statuses':
                $options = $this->staticOptions(['planned', 'approved', 'completed', 'cancelled'], true);
                break;
            case 'asset_statuses':
                $options = $this->staticOptions(['active', 'maintenance', 'retired', 'disposed'], true);
                break;
            case 'medical_device_statuses':
                $options = $this->staticOptions(['operational', 'maintenance', 'out_of_service'], true);
                break;
            case 'medical_devices':
                $options = $this->medicalDeviceOptions();
                break;
            case 'company_users':
                $options = $this->companyUserOptions();
                break;
            case 'purchase_orders':
                $options = $this->purchaseOrderOptions();
                break;
            case 'tenders':
                $options = $this->tenderOptions();
                break;
            case 'quotation_statuses':
                $options = $this->staticOptions(['submitted', 'under_review', 'accepted', 'rejected'], true);
                break;
            case 'supplier_statuses':
                $options = $this->staticOptions(['active', 'inactive', 'blacklisted'], true);
                break;
            case 'communication_types':
                $options = $this->staticOptions(['email', 'phone', 'meeting', 'visit', 'note'], true);
                break;
            case 'priority_levels':
                $options = $this->staticOptions(['low', 'normal', 'high', 'urgent'], true);
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
            case 'tender_statuses':
                $options = $this->staticOptions(['draft', 'open', 'closed', 'awarded', 'cancelled'], true);
                break;
            case 'contract_statuses':
                $options = $this->staticOptions(['draft', 'active', 'expired', 'terminated', 'renewed'], true);
                break;
            case 'warranty_statuses':
                $options = $this->staticOptions(['active', 'expired', 'void', 'pending'], true);
                break;
            case 'warehouse_statuses':
                $options = $this->staticOptions(['active', 'inactive'], true);
                break;
            case 'account_types':
                $options = $this->staticOptions(['asset', 'liability', 'equity', 'revenue', 'expense'], true);
                break;
            case 'voucher_types':
                $options = [
                    ['value' => 'receipt', 'label' => __('receipt_voucher')],
                    ['value' => 'payment', 'label' => __('payment_voucher')],
                ];
                break;
            case 'document_entity_types':
                $options = $this->staticOptions(['general', 'contract', 'supplier', 'asset', 'device', 'inventory', 'purchase_order'], true);
                break;
            case 'yes_no':
                $options = [
                    ['value' => '1', 'label' => __('yes')],
                    ['value' => '0', 'label' => __('no')],
                ];
                break;
            case 'zatca_environments':
                $options = $this->staticOptions(['sandbox', 'production'], true);
                break;
            case 'manufacturers':
                $options = $this->distinctTenantOptions('rateb_medical_devices', 'manufacturer', ['GE', 'Siemens', 'Philips', 'Medtronic', 'Drager', 'Other']);
                break;
            case 'asset_locations':
                $options = $this->distinctTenantOptions('rateb_assets', 'location', ['HQ', 'Warehouse', 'Clinic', 'Lab', 'Office']);
                break;
            case 'warehouse_locations':
                $options = $this->distinctTenantOptions('rateb_warehouses', 'location', ['Main', 'Branch', 'Cold storage']);
                break;
            case 'batch_numbers':
                $options = $this->distinctTenantOptions('rateb_inventory_batches', 'batch_no');
                break;
            case 'party_names':
                $options = $this->partyNameOptions();
                break;
            case 'supplier_names':
                $options = $this->supplierNameOptions();
                break;
            case 'purchase_requests':
                $options = $this->purchaseRequestOptions();
                break;
            case 'coa_parents':
                $options = $this->coaParentOptions();
                break;
            case 'model_numbers':
                $options = $this->distinctTenantOptions('rateb_medical_devices', 'model_no');
                break;
            case 'part_names':
                $options = $this->distinctTenantOptions('rateb_device_spare_parts', 'part_name');
                break;
            case 'tax_presets':
                $options = [];
                foreach (LineItems::taxPresets() as $pct) {
                    $options[] = ['value' => (string) $pct, 'label' => $pct . '%'];
                }
                break;
            default:
                $options = [];
        }
        $this->cache[$lookup] = $options;
        return $options;
    }

    private function bootstrapTenantForLookups(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function assetMaintenanceFormFields(): array
    {
        return [
            ['name' => 'asset_id', 'label' => 'assets', 'type' => 'fk', 'lookup' => 'assets', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'maintenance_type', 'label' => 'maintenance_type', 'type' => 'select', 'lookup' => 'maintenance_types', 'col' => 'col-md-4'],
            ['name' => 'scheduled_date', 'label' => 'scheduled_date', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'cost', 'label' => 'cost', 'type' => 'number', 'step' => '0.01', 'col' => 'col-md-4'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'maintenance_statuses', 'col' => 'col-md-4', 'default' => 'scheduled'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function assetAssignmentFormFields(): array
    {
        return [
            ['name' => 'asset_id', 'label' => 'assets', 'type' => 'fk', 'lookup' => 'assets', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'assigned_to', 'label' => 'assigned_to', 'type' => 'hybrid', 'lookup' => 'company_users', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'department', 'label' => 'department', 'type' => 'hybrid', 'lookup' => 'departments', 'col' => 'col-md-4'],
            ['name' => 'assigned_at', 'label' => 'assigned_at', 'type' => 'date', 'col' => 'col-md-4', 'default' => date('Y-m-d')],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function assetDepreciationFormFields(): array
    {
        return [
            ['name' => 'asset_id', 'label' => 'assets', 'type' => 'fk', 'lookup' => 'assets', 'required' => true, 'col' => 'col-md-3'],
            ['name' => 'period_date', 'label' => 'period_date', 'type' => 'date', 'col' => 'col-md-3', 'default' => date('Y-m-d')],
            ['name' => 'amount', 'label' => 'depreciation_amount', 'type' => 'number', 'step' => '0.01', 'required' => true, 'col' => 'col-md-3'],
            ['name' => 'book_value', 'label' => 'book_value', 'type' => 'number', 'step' => '0.01', 'required' => true, 'col' => 'col-md-3'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function deviceMaintenanceFormFields(): array
    {
        return [
            ['name' => 'device_id', 'label' => 'medical_devices', 'type' => 'fk', 'lookup' => 'medical_devices', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'service_date', 'label' => 'service_date', 'type' => 'date', 'col' => 'col-md-2', 'default' => date('Y-m-d')],
            ['name' => 'service_type', 'label' => 'service_type', 'type' => 'select', 'lookup' => 'service_types', 'col' => 'col-md-2'],
            ['name' => 'provider', 'label' => 'provider', 'type' => 'hybrid', 'lookup' => 'supplier_names', 'col' => 'col-md-2'],
            ['name' => 'cost', 'label' => 'cost', 'type' => 'number', 'step' => '0.01', 'col' => 'col-md-2'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function deviceSparePartsFormFields(): array
    {
        return [
            ['name' => 'device_id', 'label' => 'medical_devices', 'type' => 'fk', 'lookup' => 'medical_devices', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'part_name', 'label' => 'part_name', 'type' => 'hybrid', 'lookup' => 'part_names', 'required' => true, 'col' => 'col-md-3'],
            ['name' => 'part_no', 'label' => 'part_no', 'type' => 'datalist', 'lookup' => 'part_names', 'col' => 'col-md-2'],
            ['name' => 'quantity', 'label' => 'quantity', 'type' => 'number', 'step' => '0.001', 'col' => 'col-md-2'],
            ['name' => 'reorder_level', 'label' => 'reorder_level', 'type' => 'number', 'step' => '0.001', 'col' => 'col-md-1'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function stockMovementFormFields(): array
    {
        return [
            ['name' => 'inventory_id', 'label' => 'inventory', 'type' => 'fk', 'lookup' => 'inventory', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'movement_type', 'label' => 'movement_type', 'type' => 'select', 'lookup' => 'inventory_movement_types', 'col' => 'col-md-3', 'default' => 'in', 'translate_options' => false],
            ['name' => 'quantity', 'label' => 'quantity', 'type' => 'number', 'step' => '0.001', 'min' => '0.001', 'required' => true, 'col' => 'col-md-2'],
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses', 'col' => 'col-md-3'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function warehouseTransferFormFields(): array
    {
        return [
            ['name' => 'inventory_id', 'label' => 'inventory', 'type' => 'fk', 'lookup' => 'inventory', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'source_warehouse_id', 'label' => 'from', 'type' => 'fk', 'lookup' => 'warehouses', 'required' => true, 'col' => 'col-md-3'],
            ['name' => 'destination_warehouse_id', 'label' => 'to', 'type' => 'fk', 'lookup' => 'warehouses', 'required' => true, 'col' => 'col-md-3'],
            ['name' => 'quantity', 'label' => 'quantity', 'type' => 'number', 'step' => '0.001', 'min' => '0.001', 'required' => true, 'col' => 'col-md-3'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function inventoryBatchFormFields(): array
    {
        return [
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'inventory_id', 'label' => 'inventory', 'type' => 'fk', 'lookup' => 'inventory', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'batch_no', 'label' => 'batch_no', 'type' => 'hybrid', 'lookup' => 'batch_numbers', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'quantity', 'label' => 'quantity', 'type' => 'number', 'step' => '0.001', 'min' => '0', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'expiry_date', 'label' => 'expiry_date', 'type' => 'date', 'col' => 'col-md-4'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function inventoryAuditFormFields(): array
    {
        return [
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses', 'col' => 'col-md-4'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function cashVoucherFormFields(): array
    {
        return [
            ['name' => 'voucher_type', 'label' => 'voucher_type', 'type' => 'select', 'lookup' => 'voucher_types', 'translate_options' => false, 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'voucher_date', 'label' => 'voucher_date', 'type' => 'date', 'required' => true, 'col' => 'col-md-4', 'default' => date('Y-m-d')],
            ['name' => 'amount', 'label' => 'amount', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'party_name', 'label' => 'party_name', 'type' => 'hybrid', 'lookup' => 'party_names', 'col' => 'col-md-6'],
            ['name' => 'counter_account_id', 'label' => 'counter_account', 'type' => 'fk', 'lookup' => 'chart_of_accounts', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'bank_account_id', 'label' => 'bank_account', 'type' => 'fk', 'lookup' => 'bank_accounts', 'col' => 'col-md-6'],
            ['name' => 'description', 'label' => 'description', 'type' => 'text', 'col' => 'col-md-6'],
            ['name' => 'description_ar', 'label' => 'description_ar', 'type' => 'text', 'col' => 'col-md-6'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function documentUploadFormFields(): array
    {
        return [
            ['name' => 'entity_type', 'label' => 'entity_type', 'type' => 'select', 'lookup' => 'document_entity_types', 'col' => 'col-md-3', 'default' => 'general'],
            ['name' => 'entity_id', 'label' => 'entity_id', 'type' => 'number', 'col' => 'col-md-2', 'min' => '0'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function supplierEvaluationFormFields(): array
    {
        return [
            ['name' => 'supplier_id', 'label' => 'suppliers', 'type' => 'fk', 'lookup' => 'suppliers', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'evaluation_date', 'label' => 'evaluation_date', 'type' => 'date', 'required' => true, 'col' => 'col-md-6', 'default' => date('Y-m-d')],
            ['name' => 'quality_score', 'label' => 'quality_score', 'type' => 'score_select', 'col' => 'col-md-3'],
            ['name' => 'delivery_score', 'label' => 'delivery_score', 'type' => 'score_select', 'col' => 'col-md-3'],
            ['name' => 'price_score', 'label' => 'price_score', 'type' => 'score_select', 'col' => 'col-md-3'],
            ['name' => 'service_score', 'label' => 'service_score', 'type' => 'score_select', 'col' => 'col-md-3'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'evaluation_statuses', 'col' => 'col-md-6', 'default' => 'published'],
            ['name' => 'comments', 'label' => 'comments', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 4],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function contractRenewalFormFields(): array
    {
        return [
            ['name' => 'contract_id', 'label' => 'contracts', 'type' => 'fk', 'lookup' => 'contracts', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'renewal_date', 'label' => 'renewal_date', 'type' => 'date', 'col' => 'col-md-2', 'default' => date('Y-m-d')],
            ['name' => 'new_end_date', 'label' => 'new_end_date', 'type' => 'date', 'col' => 'col-md-2'],
            ['name' => 'new_value', 'label' => 'new_value', 'type' => 'number', 'step' => '0.01', 'col' => 'col-md-2'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'renewal_statuses', 'col' => 'col-md-2'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    /** @return list<FormOption> */
    private function medicalDeviceOptions(): array
    {
        $out = [];
        foreach ((new \Rateb\App\Models\MedicalDevice())->all(300, 0) as $row) {
            $label = trim((string) ($row['device_name'] ?? ''));
            $serial = trim((string) ($row['serial_no'] ?? ''));
            if ($serial !== '') {
                $label .= ' — ' . $serial;
            }
            $out[] = ['value' => (int) $row['id'], 'label' => $label !== '' ? $label : '#' . $row['id']];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function companyUserOptions(): array
    {
        $cid = TenantContext::companyId() ?? 0;
        if ($cid < 1) {
            return [];
        }
        $rows = (new \Rateb\App\Models\User())->query(
            'SELECT id, name, email FROM rateb_users WHERE company_id = :cid AND status = :st ORDER BY name ASC LIMIT 300',
            ['cid' => $cid, 'st' => 'active']
        );
        $out = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $label .= ' (' . $email . ')';
            }
            $out[] = ['value' => (int) $row['id'], 'label' => $label];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function purchaseOrderOptions(): array
    {
        $out = [];
        foreach ((new \Rateb\App\Models\PurchaseOrder())->all(300, 0) as $row) {
            $out[] = ['value' => (int) $row['id'], 'label' => trim(($row['order_no'] ?? '') . ' — ' . ($row['title'] ?? ''))];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function tenderOptions(): array
    {
        $out = [];
        foreach ((new \Rateb\App\Models\Tender())->all(200, 0) as $row) {
            $out[] = ['value' => (int) $row['id'], 'label' => trim(($row['tender_no'] ?? '') . ' — ' . ($row['title'] ?? ''))];
        }
        return $out;
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
    public function inventoryByWarehouse(int $warehouseId): array
    {
        if ($warehouseId < 1) {
            return [];
        }
        $out = [];
        $rows = (new Inventory())->query(
            'SELECT id, item_name, sku, quantity, unit, unit_cost, reorder_level, max_stock, category_id
             FROM rateb_inventory WHERE warehouse_id = :wid ORDER BY item_name ASC LIMIT 500',
            ['wid' => $warehouseId]
        );
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $label = $sku !== '' ? ($sku . ' — ' . ($row['item_name'] ?? '')) : (string) ($row['item_name'] ?? '');
            $out[] = ['value' => (int) $row['id'], 'label' => $label];
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function inventoryRowsByWarehouse(int $warehouseId): array
    {
        if ($warehouseId < 1) {
            return [];
        }
        $params = ['wid' => $warehouseId];
        $sql = 'SELECT id, item_name, sku, quantity, unit, unit_cost, reorder_level, max_stock, category_id
             FROM rateb_inventory WHERE warehouse_id = :wid';
        $companyId = \Rateb\App\Core\TenantContext::companyId();
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY item_name ASC LIMIT 500';

        return (new Inventory())->query($sql, $params);
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
    private function distinctTenantOptions(string $table, string $column, array $presets = []): array
    {
        $safeTable = preg_replace('/[^a-z_]/', '', $table);
        $safeCol = preg_replace('/[^a-z_]/', '', $column);
        $cid = TenantContext::companyId() ?? 0;
        if ($cid < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $cid = rateb_resolve_ops_company_id();
        }
        $distinct = [];
        if ($cid > 0) {
            $db = \Rateb\App\Core\Database::connection();
            $stmt = $db->prepare(
                'SELECT DISTINCT `' . $safeCol . '` AS v FROM `' . $safeTable . '`
                 WHERE company_id = :cid AND `' . $safeCol . '` IS NOT NULL AND `' . $safeCol . '` <> \'\'
                 ORDER BY v LIMIT 200'
            );
            $stmt->execute(['cid' => $cid]);
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $distinct[] = (string) ($row['v'] ?? '');
            }
        }
        $merged = array_values(array_unique(array_merge($presets, $distinct)));
        $out = [];
        foreach ($merged as $val) {
            if ($val !== '') {
                $out[] = ['value' => $val, 'label' => $val];
            }
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function supplierNameOptions(): array
    {
        $out = [];
        foreach ((new Supplier())->all(300, 0) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $out[] = ['value' => $name, 'label' => $name];
            }
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function partyNameOptions(): array
    {
        $out = [];
        foreach ((new Supplier())->all(300, 0) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $out[] = ['value' => $name, 'label' => $name];
            }
        }
        foreach ($this->distinctTenantOptions('rateb_cash_vouchers', 'party_name') as $opt) {
            $out[] = $opt;
        }
        $seen = [];
        $deduped = [];
        foreach ($out as $opt) {
            $k = (string) $opt['value'];
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $deduped[] = $opt;
            }
        }
        return $deduped;
    }

    /** @return list<FormOption> */
    private function purchaseRequestOptions(): array
    {
        $out = [];
        $rows = (new PurchaseRequest())->all(200, 0);
        foreach ($rows as $row) {
            $out[] = ['value' => (int) $row['id'], 'label' => trim(($row['request_no'] ?? '') . ' — ' . ($row['title'] ?? ''))];
        }
        return $out;
    }

    /** @return list<FormOption> */
    private function coaParentOptions(): array
    {
        $cid = TenantContext::companyId() ?? 0;
        if ($cid < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $cid = rateb_resolve_ops_company_id();
        }
        if ($cid < 1) {
            return [['value' => '', 'label' => '—']];
        }
        $tree = (new AccountingService())->coaTreeWithBalances($cid);
        $out = [['value' => '', 'label' => '—']];
        $walk = static function (array $nodes, int $depth) use (&$walk, &$out): void {
            foreach ($nodes as $node) {
                $id = (int) ($node['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $code = (string) ($node['code'] ?? '');
                $name = rateb_locale() === 'ar' && !empty($node['name_ar']) ? $node['name_ar'] : ($node['name'] ?? '');
                $out[] = ['value' => $id, 'label' => str_repeat('— ', $depth) . $code . ' — ' . $name];
                if (!empty($node['children'])) {
                    $walk($node['children'], $depth + 1);
                }
            }
        };
        $walk($tree, 0);
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
        if ($cid < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $cid = rateb_resolve_ops_company_id();
        }
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
