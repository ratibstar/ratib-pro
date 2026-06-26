<?php
declare(strict_types=1);

/**
 * Production reset runner — shared by CLI (reset-production.php) and HTTP cert runner.
 */
final class ProductionResetRunner
{
    public const CONFIRM_PHRASE = 'RESET-PRODUCTION';

    /** @var list<string> Never truncated — system foundation */
    private const PRESERVE_TABLES = [
        'rateb_migrations',
        'rateb_plans',
        'rateb_permissions',
        'rateb_roles',
        'rateb_role_permissions',
        'rateb_system_settings',
        'rateb_email_templates',
        'rateb_sms_templates',
        'rateb_cms_about',
        'rateb_cms_analytics',
        'rateb_cms_article_tags',
        'rateb_cms_blocks',
        'rateb_cms_blog_articles',
        'rateb_cms_blog_authors',
        'rateb_cms_blog_categories',
        'rateb_cms_blog_tags',
        'rateb_cms_careers',
        'rateb_cms_contact_settings',
        'rateb_cms_faq_categories',
        'rateb_cms_faqs',
        'rateb_cms_footer_columns',
        'rateb_cms_help_articles',
        'rateb_cms_kb_articles',
        'rateb_cms_leads',
        'rateb_cms_lead_notes',
        'rateb_cms_media',
        'rateb_cms_media_categories',
        'rateb_cms_menu_items',
        'rateb_cms_menus',
        'rateb_cms_newsletter_campaigns',
        'rateb_cms_newsletter_segments',
        'rateb_cms_newsletter_subscribers',
        'rateb_cms_offices',
        'rateb_cms_pages',
        'rateb_cms_partners',
        'rateb_cms_redirects',
        'rateb_cms_robots',
        'rateb_cms_sections',
        'rateb_cms_seo',
        'rateb_cms_service_categories',
        'rateb_cms_services',
        'rateb_cms_slides',
        'rateb_cms_system_status',
        'rateb_cms_team_members',
        'rateb_cms_testimonials',
        'rateb_cms_theme',
        'rateb_cms_timeline',
        'rateb_cms_visitors',
    ];

    /** @var list<string> Preferred wipe order (children before parents) */
    private const WIPE_ORDER = [
        'rateb_journal_lines',
        'rateb_bank_statement_lines',
        'rateb_budget_lines',
        'rateb_payroll_lines',
        'rateb_inventory_audit_lines',
        'rateb_purchase_items',
        'rateb_purchase_request_items',
        'rateb_invoice_lines',
        'rateb_journal_entries',
        'rateb_supplier_payments',
        'rateb_cash_vouchers',
        'rateb_purchase_invoices',
        'rateb_purchase_orders',
        'rateb_purchase_requests',
        'rateb_invoices',
        'rateb_payments',
        'rateb_stock_movements',
        'rateb_inventory_batches',
        'rateb_inventory_audits',
        'rateb_inventory',
        'rateb_warehouse_transfers',
        'rateb_warehouses',
        'rateb_branch_transfers',
        'rateb_user_branches',
        'rateb_branches',
        'rateb_attendance_records',
        'rateb_leave_requests',
        'rateb_leave_balances',
        'rateb_payroll_periods',
        'rateb_hr_permission_requests',
        'rateb_hr_employee_requests',
        'rateb_hr_loans',
        'rateb_hr_documents',
        'rateb_hr_fleet',
        'rateb_employees',
        'rateb_hr_departments',
        'rateb_hr_holidays',
        'rateb_hr_workplaces',
        'rateb_hr_loan_types',
        'rateb_hr_payroll_components',
        'rateb_hr_payroll_structures',
        'rateb_leave_types',
        'rateb_customers',
        'rateb_supplier_quotations',
        'rateb_supplier_comm_timeline',
        'rateb_supplier_communications',
        'rateb_supplier_evaluations',
        'rateb_supplier_classifications',
        'rateb_suppliers',
        'rateb_rfq',
        'rateb_tender_comparisons',
        'rateb_tenders',
        'rateb_contract_renewals',
        'rateb_contracts',
        'rateb_asset_depreciation',
        'rateb_asset_maintenance',
        'rateb_asset_assignments',
        'rateb_assets',
        'rateb_asset_categories',
        'rateb_device_spare_parts',
        'rateb_device_service_history',
        'rateb_medical_devices',
        'rateb_device_categories',
        'rateb_documents',
        'rateb_approval_actions',
        'rateb_approval_instances',
        'rateb_approval_workflow_steps',
        'rateb_approval_workflows',
        'rateb_chart_of_accounts',
        'rateb_cost_centers',
        'rateb_bank_accounts',
        'rateb_fiscal_periods',
        'rateb_company_tax_profiles',
        'rateb_product_categories',
        'rateb_subscriptions',
        'rateb_notification_queue',
        'rateb_notifications',
        'rateb_audit_logs',
        'rateb_login_activity',
        'rateb_api_tokens',
        'rateb_remember_tokens',
        'rateb_two_factor_backup_codes',
        'rateb_password_resets',
        'rateb_login_barcode_pairs',
        'rateb_support_tickets',
        'rateb_lims_results',
        'rateb_lims_samples',
        'rateb_blood_units',
        'rateb_blood_donors',
        'rateb_pharmacy_dispenses',
        'rateb_pharmacy_prescriptions',
        'rateb_cron_health',
        'rateb_user_roles',
        'rateb_companies',
    ];

    private \PDO $db;

    /** @var array<string, mixed> */
    private array $report = [
        'mode' => '',
        'database' => '',
        'started_at' => '',
        'finished_at' => '',
        'tables' => [],
        'users' => [],
        'files' => [],
        'errors' => [],
    ];

    public function __construct(\PDO $db)
    {
        $this->db = $db;
        $this->report['database'] = Rateb\App\Core\Database::resolvedDatabaseName();
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        return $this->report;
    }

    public function run(bool $dryRun): void
    {
        $this->report['mode'] = $dryRun ? 'dry-run' : 'execute';
        $this->report['started_at'] = date('c');

        $existing = $this->listErpTables();
        $preserve = array_flip(self::PRESERVE_TABLES);
        $toWipe = [];
        foreach (self::WIPE_ORDER as $table) {
            if (isset($existing[$table]) && !isset($preserve[$table])) {
                $toWipe[] = $table;
            }
        }
        foreach ($existing as $table => $_) {
            if (isset($preserve[$table]) || in_array($table, $toWipe, true)) {
                continue;
            }
            if ($table === 'rateb_users') {
                continue;
            }
            $toWipe[] = $table;
        }

        echo "Database: {$this->report['database']}\n";
        echo 'Mode: ' . ($dryRun ? 'DRY-RUN (no changes)' : 'EXECUTE') . "\n";
        echo 'Tables to wipe: ' . count($toWipe) . "\n\n";

        if (!$dryRun) {
            $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach ($toWipe as $table) {
            $count = $this->tableRowCount($table);
            $this->report['tables'][$table] = ['before' => $count, 'action' => 'TRUNCATE'];
            echo sprintf("  %-40s %8d rows\n", $table, $count);
            if (!$dryRun && $count >= 0) {
                try {
                    $this->db->exec('TRUNCATE TABLE `' . str_replace('`', '', $table) . '`');
                } catch (\Throwable $e) {
                    $this->report['errors'][] = $table . ': ' . $e->getMessage();
                }
            }
        }

        $this->handleUsers($dryRun);
        $this->handleFiles($dryRun);

        if (!$dryRun) {
            $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->report['finished_at'] = date('c');
    }

    private function handleUsers(bool $dryRun): void
    {
        $admins = $this->db->query(
            'SELECT id, email FROM rateb_users WHERE is_super_admin = 1'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $this->report['users']['preserved_super_admins'] = $admins;

        $toDelete = (int) $this->db->query(
            'SELECT COUNT(*) FROM rateb_users WHERE is_super_admin = 0 OR is_super_admin IS NULL'
        )->fetchColumn();
        $this->report['users']['deleted_non_admin'] = $toDelete;
        echo "\nUsers to delete (non-super-admin): {$toDelete}\n";
        foreach ($admins as $a) {
            echo '  preserve super-admin id=' . ($a['id'] ?? '?') . ' ' . ($a['email'] ?? '') . "\n";
        }

        if ($dryRun || $toDelete < 1) {
            return;
        }

        $this->db->exec('DELETE FROM rateb_user_roles WHERE user_id IN (SELECT id FROM rateb_users WHERE is_super_admin = 0 OR is_super_admin IS NULL)');
        $this->db->exec('DELETE FROM rateb_users WHERE is_super_admin = 0 OR is_super_admin IS NULL');
    }

    private function handleFiles(bool $dryRun): void
    {
        $dirs = [
            RATEB_ROOT . '/storage/uploads',
            RATEB_ROOT . '/storage/rate-limit',
        ];
        foreach ($dirs as $dir) {
            $entry = ['path' => $dir, 'removed' => 0];
            if (!is_dir($dir)) {
                $this->report['files'][] = $entry;
                continue;
            }
            if ($dryRun) {
                $count = 0;
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $file) {
                    if ($file->isFile()) {
                        $count++;
                    }
                }
                $entry['would_remove'] = $count;
                echo "Files under {$dir}: would remove {$count} files\n";
            } else {
                $entry['removed'] = $this->emptyDirectory($dir);
                echo "Files under {$dir}: removed {$entry['removed']} files\n";
            }
            $this->report['files'][] = $entry;
        }
    }

    private function emptyDirectory(string $dir): int
    {
        $removed = 0;
        if (!is_dir($dir)) {
            return 0;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                if (@unlink($file->getPathname())) {
                    $removed++;
                }
            } elseif ($file->isDir()) {
                @rmdir($file->getPathname());
            }
        }
        return $removed;
    }

    /** @return array<string, true> */
    private function listErpTables(): array
    {
        $stmt = $this->db->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'rateb\\_%'"
        );
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $name = (string) ($row['TABLE_NAME'] ?? '');
            if ($name !== '') {
                $out[$name] = true;
            }
        }
        return $out;
    }

    private function tableRowCount(string $table): int
    {
        try {
            return (int) $this->db->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`')->fetchColumn();
        } catch (\Throwable $e) {
            $this->report['errors'][] = 'count ' . $table . ': ' . $e->getMessage();
            return -1;
        }
    }

    /** @return list<string> */
    public static function validateProcedure(): array
    {
        $issues = [];
        if (!is_file(RATEB_ROOT . '/bin/reset-production.php')) {
            $issues[] = 'reset-production.php missing';
        }
        if (!is_file(RATEB_ROOT . '/bin/ProductionResetRunner.php')) {
            $issues[] = 'ProductionResetRunner.php missing';
        }
        if (self::CONFIRM_PHRASE === '') {
            $issues[] = 'confirm phrase empty';
        }
        if (self::PRESERVE_TABLES === []) {
            $issues[] = 'preserve list empty';
        }
        return $issues;
    }
}
