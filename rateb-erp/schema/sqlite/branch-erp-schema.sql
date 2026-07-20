-- RATEB ERP Branch SQLite schema (Phase B)
-- Generated from MySQL dump — do not hand-edit; regenerate via hybrid-phase-b-generate-sqlite-schema.php
-- Foreign keys omitted (enforced in application layer). WAL applied at connection.
PRAGMA foreign_keys=OFF;

CREATE TABLE IF NOT EXISTS "rateb_accounting_activities" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "journal_entry_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL DEFAULT 'journal',
  "entity_id" INTEGER DEFAULT NULL,
  "action" TEXT NOT NULL,
  "summary" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_activities_idx_acc_act_company" ON "rateb_accounting_activities" ("company_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_activities_idx_acc_act_je" ON "rateb_accounting_activities" ("journal_entry_id");

CREATE TABLE IF NOT EXISTS "rateb_accounting_currencies" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "symbol" TEXT DEFAULT NULL,
  "decimal_places" INTEGER NOT NULL DEFAULT 2,
  "is_base" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_currencies_uq_acc_cur_uuid" ON "rateb_accounting_currencies" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_currencies_uq_acc_cur_code" ON "rateb_accounting_currencies" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_currencies_idx_acc_cur_company" ON "rateb_accounting_currencies" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_accounting_document_links" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "journal_entry_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL DEFAULT 'journal',
  "entity_id" INTEGER NOT NULL,
  "document_id" INTEGER DEFAULT NULL,
  "file_name" TEXT DEFAULT NULL,
  "mime_type" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_document_links_uq_acc_doc_uuid" ON "rateb_accounting_document_links" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_document_links_idx_acc_doc_entity" ON "rateb_accounting_document_links" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_accounting_exchange_rates" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "from_currency" TEXT NOT NULL,
  "to_currency" TEXT NOT NULL,
  "rate" REAL NOT NULL,
  "rate_date" TEXT NOT NULL,
  "source" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_exchange_rates_uq_acc_fx_uuid" ON "rateb_accounting_exchange_rates" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_exchange_rates_uq_acc_fx_day" ON "rateb_accounting_exchange_rates" ("company_id", "from_currency", "to_currency", "rate_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_exchange_rates_idx_acc_fx_company" ON "rateb_accounting_exchange_rates" ("company_id", "rate_date");

CREATE TABLE IF NOT EXISTS "rateb_accounting_profit_centers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_profit_centers_uq_acc_pc_uuid" ON "rateb_accounting_profit_centers" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_profit_centers_uq_acc_pc_code" ON "rateb_accounting_profit_centers" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_profit_centers_idx_acc_pc_company" ON "rateb_accounting_profit_centers" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_accounting_recurring_journal_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "recurring_journal_id" INTEGER NOT NULL,
  "account_id" INTEGER NOT NULL,
  "cost_center_id" INTEGER DEFAULT NULL,
  "profit_center_id" INTEGER DEFAULT NULL,
  "debit" REAL NOT NULL DEFAULT 0.00,
  "credit" REAL NOT NULL DEFAULT 0.00,
  "memo" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_recurring_journal_lines_idx_acc_rjl_parent" ON "rateb_accounting_recurring_journal_lines" ("recurring_journal_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_recurring_journal_lines_fk_acc_rjl_account" ON "rateb_accounting_recurring_journal_lines" ("account_id");

CREATE TABLE IF NOT EXISTS "rateb_accounting_recurring_journals" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "frequency" TEXT NOT NULL DEFAULT 'monthly',
  "next_run_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "currency_code" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "last_generated_entry_id" INTEGER DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_recurring_journals_uq_acc_rj_uuid" ON "rateb_accounting_recurring_journals" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_recurring_journals_uq_acc_rj_code" ON "rateb_accounting_recurring_journals" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_recurring_journals_idx_acc_rj_company" ON "rateb_accounting_recurring_journals" ("company_id", "status", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_accounting_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "journal_entry_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_status_history_idx_acc_hist_je" ON "rateb_accounting_status_history" ("company_id", "journal_entry_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_status_history_fk_acc_hist_je" ON "rateb_accounting_status_history" ("journal_entry_id");

CREATE TABLE IF NOT EXISTS "rateb_accounting_tax_codes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "rate_percent" REAL NOT NULL DEFAULT 0.0000,
  "tax_type" TEXT NOT NULL DEFAULT 'vat',
  "recoverable" INTEGER NOT NULL DEFAULT 1,
  "account_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_tax_codes_uq_acc_tax_uuid" ON "rateb_accounting_tax_codes" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_accounting_tax_codes_uq_acc_tax_code" ON "rateb_accounting_tax_codes" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_accounting_tax_codes_idx_acc_tax_company" ON "rateb_accounting_tax_codes" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_api_tokens" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "user_id" INTEGER NOT NULL,
  "company_id" INTEGER DEFAULT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "token_hash" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "abilities" TEXT DEFAULT NULL,
  "last_used_at" TEXT DEFAULT NULL,
  "expires_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_api_tokens_token_hash" ON "rateb_api_tokens" ("token_hash");
CREATE INDEX IF NOT EXISTS "idx_rateb_api_tokens_fk_tokens_user" ON "rateb_api_tokens" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_api_tokens_idx_api_token_branch" ON "rateb_api_tokens" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_approval_actions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "instance_id" INTEGER NOT NULL,
  "step_order" INTEGER NOT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "action" TEXT NOT NULL,
  "comment" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_approval_actions_fk_aa_instance" ON "rateb_approval_actions" ("instance_id");

CREATE TABLE IF NOT EXISTS "rateb_approval_instances" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "workflow_id" INTEGER NOT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "current_step" INTEGER NOT NULL DEFAULT 1,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "submitted_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "due_at" TEXT DEFAULT NULL,
  "escalated_at" TEXT DEFAULT NULL,
  "escalation_count" INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS "idx_rateb_approval_instances_idx_ai_entity" ON "rateb_approval_instances" ("entity_type", "entity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_approval_instances_fk_ai_company" ON "rateb_approval_instances" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_approval_workflow_steps" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "workflow_id" INTEGER NOT NULL,
  "step_order" INTEGER NOT NULL DEFAULT 1,
  "role_id" INTEGER DEFAULT NULL,
  "approver_user_id" INTEGER DEFAULT NULL,
  "label" TEXT NOT NULL,
  "sla_hours" INTEGER DEFAULT 48
);

CREATE INDEX IF NOT EXISTS "idx_rateb_approval_workflow_steps_fk_aws_workflow" ON "rateb_approval_workflow_steps" ("workflow_id");

CREATE TABLE IF NOT EXISTS "rateb_approval_workflows" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "name" TEXT NOT NULL,
  "entity_type" TEXT NOT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_approval_workflows_idx_aw_company" ON "rateb_approval_workflows" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_asset_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "assignment_no" TEXT DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "assigned_to" TEXT NOT NULL,
  "department" TEXT DEFAULT NULL,
  "assigned_at" TEXT NOT NULL,
  "returned_at" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "manager_approval" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_asset_assignments_fk_aas_company" ON "rateb_asset_assignments" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_asset_assignments_fk_aas_asset" ON "rateb_asset_assignments" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_asset_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_asset_categories_fk_acat_company" ON "rateb_asset_categories" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_asset_depreciation" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "depreciation_no" TEXT DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "period_date" TEXT NOT NULL,
  "depreciation_type" TEXT NOT NULL DEFAULT 'monthly',
  "depreciation_rate" REAL DEFAULT NULL,
  "useful_life_months" INTEGER DEFAULT NULL,
  "residual_value" REAL NOT NULL DEFAULT 0.00,
  "cost_center_id" INTEGER DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "book_value_before" REAL NOT NULL DEFAULT 0.00,
  "book_value" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_asset_depreciation_fk_ad_company" ON "rateb_asset_depreciation" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_asset_depreciation_fk_ad_asset" ON "rateb_asset_depreciation" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_asset_maintenance" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "maintenance_no" TEXT DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "maintenance_type" TEXT NOT NULL,
  "scheduled_date" TEXT DEFAULT NULL,
  "completed_date" TEXT DEFAULT NULL,
  "cost" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'scheduled',
  "manager_approval" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_asset_maintenance_fk_am_company" ON "rateb_asset_maintenance" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_asset_maintenance_fk_am_asset" ON "rateb_asset_maintenance" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_assets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_tag" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "category" TEXT DEFAULT NULL,
  "category_id" INTEGER DEFAULT NULL,
  "purchase_date" TEXT DEFAULT NULL,
  "purchase_cost" REAL NOT NULL DEFAULT 0.00,
  "current_value" REAL NOT NULL DEFAULT 0.00,
  "location" TEXT DEFAULT NULL,
  "assigned_to" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_assets_uq_assets_company_tag" ON "rateb_assets" ("company_id", "asset_tag");
CREATE INDEX IF NOT EXISTS "idx_rateb_assets_idx_asset_branch" ON "rateb_assets" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_attendance_records" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_id" INTEGER NOT NULL,
  "attendance_date" TEXT NOT NULL,
  "check_in" TEXT DEFAULT NULL,
  "check_out" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'present',
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_attendance_records_uk_attendance_day" ON "rateb_attendance_records" ("company_id", "employee_id", "attendance_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_attendance_records_idx_attendance_company" ON "rateb_attendance_records" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_attendance_records_idx_attendance_date" ON "rateb_attendance_records" ("attendance_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_attendance_records_idx_att_branch" ON "rateb_attendance_records" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_audit_logs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "action" TEXT NOT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "ip_address" TEXT DEFAULT NULL,
  "user_agent" TEXT DEFAULT NULL,
  "payload" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_audit_logs_idx_audit_company" ON "rateb_audit_logs" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_audit_logs_idx_audit_user" ON "rateb_audit_logs" ("user_id");

CREATE TABLE IF NOT EXISTS "rateb_bank_accounts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "name" TEXT NOT NULL,
  "bank_name" TEXT DEFAULT NULL,
  "account_number" TEXT DEFAULT NULL,
  "chart_account_id" INTEGER NOT NULL,
  "opening_balance" REAL NOT NULL DEFAULT 0.00,
  "is_default" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_bank_accounts_idx_ba_company" ON "rateb_bank_accounts" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_bank_accounts_fk_ba_coa" ON "rateb_bank_accounts" ("chart_account_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_bank_accounts_idx_ba_branch" ON "rateb_bank_accounts" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_bank_statement_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "bank_account_id" INTEGER NOT NULL,
  "line_date" TEXT NOT NULL,
  "description" TEXT NOT NULL DEFAULT '',
  "amount" REAL NOT NULL,
  "reference_no" TEXT DEFAULT NULL,
  "import_batch" TEXT DEFAULT NULL,
  "is_reconciled" INTEGER NOT NULL DEFAULT 0,
  "journal_entry_id" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_bank_statement_lines_idx_bsl_bank" ON "rateb_bank_statement_lines" ("bank_account_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_bank_statement_lines_idx_bsl_company" ON "rateb_bank_statement_lines" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_alerts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "kpi_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "threshold_value" REAL DEFAULT NULL,
  "comparison" TEXT NOT NULL DEFAULT 'gt',
  "alert_status" TEXT NOT NULL DEFAULT 'active',
  "last_triggered_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_alerts_uq_bi_al_uuid" ON "rateb_bi_alerts" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_alerts_uq_bi_al_code" ON "rateb_bi_alerts" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_alerts_idx_bi_al_kpi" ON "rateb_bi_alerts" ("company_id", "kpi_id", "alert_status");

CREATE TABLE IF NOT EXISTS "rateb_bi_analytics_scopes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "scope_type" TEXT NOT NULL DEFAULT 'company',
  "scope_ref_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_analytics_scopes_uq_bi_as_uuid" ON "rateb_bi_analytics_scopes" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_analytics_scopes_uq_bi_as_code" ON "rateb_bi_analytics_scopes" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_analytics_scopes_idx_bi_as_type" ON "rateb_bi_analytics_scopes" ("company_id", "scope_type", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_bi_audit_logs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "action_type" TEXT NOT NULL,
  "actor_user_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "detail_text" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_audit_logs_uq_bi_aud_uuid" ON "rateb_bi_audit_logs" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_audit_logs_idx_bi_aud_action" ON "rateb_bi_audit_logs" ("company_id", "action_type", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_bi_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "comment_text" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_comments_uq_bi_cmt_uuid" ON "rateb_bi_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_comments_idx_bi_cmt_entity" ON "rateb_bi_comments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_dashboards" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "dashboard_type" TEXT NOT NULL DEFAULT 'custom',
  "owner_user_id" INTEGER DEFAULT NULL,
  "layout_json" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "published_at" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_dashboards_uq_bi_dash_uuid" ON "rateb_bi_dashboards" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_dashboards_uq_bi_dash_code" ON "rateb_bi_dashboards" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_dashboards_idx_bi_dash_wf" ON "rateb_bi_dashboards" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_dashboards_idx_bi_dash_type" ON "rateb_bi_dashboards" ("company_id", "dashboard_type", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_bi_dataset_links" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "dataset_id" INTEGER NOT NULL,
  "linked_module" TEXT NOT NULL,
  "linked_entity_type" TEXT NOT NULL,
  "linked_entity_id" INTEGER DEFAULT NULL,
  "link_role" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_dataset_links_uq_bi_dsl_uuid" ON "rateb_bi_dataset_links" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_dataset_links_idx_bi_dsl_ds" ON "rateb_bi_dataset_links" ("company_id", "dataset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_dataset_links_idx_bi_dsl_mod" ON "rateb_bi_dataset_links" ("company_id", "linked_module", "linked_entity_type");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_dataset_links_fk_bi_dsl_ds" ON "rateb_bi_dataset_links" ("dataset_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_datasets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "source_module" TEXT NOT NULL,
  "entity_hint" TEXT DEFAULT NULL,
  "refresh_mode" TEXT NOT NULL DEFAULT 'manual',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_datasets_uq_bi_ds_uuid" ON "rateb_bi_datasets" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_datasets_uq_bi_ds_code" ON "rateb_bi_datasets" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_datasets_idx_bi_ds_mod" ON "rateb_bi_datasets" ("company_id", "source_module", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_bi_drilldowns" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "report_id" INTEGER DEFAULT NULL,
  "parent_level" TEXT NOT NULL,
  "child_level" TEXT NOT NULL,
  "config_json" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_drilldowns_uq_bi_dd_uuid" ON "rateb_bi_drilldowns" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_drilldowns_idx_bi_dd_rpt" ON "rateb_bi_drilldowns" ("company_id", "report_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_drilldowns_fk_bi_dd_rpt" ON "rateb_bi_drilldowns" ("report_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_exports" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "report_id" INTEGER DEFAULT NULL,
  "export_format" TEXT NOT NULL DEFAULT 'csv',
  "export_status" TEXT NOT NULL DEFAULT 'pending',
  "storage_path" TEXT DEFAULT NULL,
  "requested_at" TEXT NOT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_exports_uq_bi_exp_uuid" ON "rateb_bi_exports" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_exports_idx_bi_exp_rpt" ON "rateb_bi_exports" ("company_id", "report_id", "export_status");

CREATE TABLE IF NOT EXISTS "rateb_bi_favorites" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER NOT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_favorites_uq_bi_fav_uuid" ON "rateb_bi_favorites" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_favorites_uq_bi_fav_user" ON "rateb_bi_favorites" ("company_id", "user_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_forecasts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "kpi_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "horizon_periods" INTEGER NOT NULL DEFAULT 3,
  "method_hint" TEXT DEFAULT NULL,
  "forecast_json" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_forecasts_uq_bi_fc_uuid" ON "rateb_bi_forecasts" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_forecasts_uq_bi_fc_code" ON "rateb_bi_forecasts" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_forecasts_idx_bi_fc_kpi" ON "rateb_bi_forecasts" ("company_id", "kpi_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_kpi_snapshots" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "kpi_id" INTEGER NOT NULL,
  "snapshot_at" TEXT NOT NULL,
  "metric_value" REAL NOT NULL DEFAULT 0.0000,
  "period_key" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_kpi_snapshots_uq_bi_ks_uuid" ON "rateb_bi_kpi_snapshots" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_kpi_snapshots_idx_bi_ks_kpi" ON "rateb_bi_kpi_snapshots" ("company_id", "kpi_id", "snapshot_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_kpi_snapshots_fk_bi_ks_kpi" ON "rateb_bi_kpi_snapshots" ("kpi_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_kpis" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "metric_key" TEXT NOT NULL,
  "unit" TEXT DEFAULT NULL,
  "target_value" REAL DEFAULT NULL,
  "direction" TEXT NOT NULL DEFAULT 'higher_better',
  "source_module" TEXT DEFAULT NULL,
  "formula_text" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_kpis_uq_bi_kpi_uuid" ON "rateb_bi_kpis" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_kpis_uq_bi_kpi_code" ON "rateb_bi_kpis" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_kpis_idx_bi_kpi_wf" ON "rateb_bi_kpis" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_kpis_idx_bi_kpi_mod" ON "rateb_bi_kpis" ("company_id", "source_module");

CREATE TABLE IF NOT EXISTS "rateb_bi_report_runs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "report_id" INTEGER NOT NULL,
  "run_status" TEXT NOT NULL DEFAULT 'pending',
  "started_at" TEXT DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "row_count" INTEGER DEFAULT NULL,
  "result_summary" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_report_runs_uq_bi_run_uuid" ON "rateb_bi_report_runs" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_report_runs_idx_bi_run_rpt" ON "rateb_bi_report_runs" ("company_id", "report_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_report_runs_fk_bi_run_rpt" ON "rateb_bi_report_runs" ("report_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_reports" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "report_type" TEXT NOT NULL DEFAULT 'saved',
  "source_module" TEXT DEFAULT NULL,
  "query_meta_json" TEXT DEFAULT NULL,
  "filters_json" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "published_at" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_reports_uq_bi_rpt_uuid" ON "rateb_bi_reports" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_reports_uq_bi_rpt_code" ON "rateb_bi_reports" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_reports_idx_bi_rpt_wf" ON "rateb_bi_reports" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_reports_idx_bi_rpt_type" ON "rateb_bi_reports" ("company_id", "report_type", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_bi_schedules" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "report_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "cron_hint" TEXT DEFAULT NULL,
  "next_run_at" TEXT DEFAULT NULL,
  "schedule_status" TEXT NOT NULL DEFAULT 'active',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_schedules_uq_bi_sch_uuid" ON "rateb_bi_schedules" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_schedules_uq_bi_sch_code" ON "rateb_bi_schedules" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_schedules_idx_bi_sch_rpt" ON "rateb_bi_schedules" ("company_id", "report_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_status_history_uq_bi_sh_uuid" ON "rateb_bi_status_history" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_status_history_idx_bi_sh_entity" ON "rateb_bi_status_history" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_tags_uq_bi_tag_uuid" ON "rateb_bi_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_tags_uq_bi_tag_code" ON "rateb_bi_tags" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_bi_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_timeline_uq_bi_tl_uuid" ON "rateb_bi_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_timeline_idx_bi_tl_company" ON "rateb_bi_timeline" ("company_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_timeline_idx_bi_tl_entity" ON "rateb_bi_timeline" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_trends" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "kpi_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "period_grain" TEXT NOT NULL DEFAULT 'month',
  "series_json" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_trends_uq_bi_tr_uuid" ON "rateb_bi_trends" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_trends_uq_bi_tr_code" ON "rateb_bi_trends" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_trends_idx_bi_tr_kpi" ON "rateb_bi_trends" ("company_id", "kpi_id");

CREATE TABLE IF NOT EXISTS "rateb_bi_widgets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "dashboard_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "title_ar" TEXT DEFAULT NULL,
  "widget_type" TEXT NOT NULL DEFAULT 'kpi',
  "data_source" TEXT DEFAULT NULL,
  "config_json" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_widgets_uq_bi_wid_uuid" ON "rateb_bi_widgets" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_bi_widgets_uq_bi_wid_code" ON "rateb_bi_widgets" ("company_id", "dashboard_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_widgets_idx_bi_wid_dash" ON "rateb_bi_widgets" ("company_id", "dashboard_id", "sort_order");
CREATE INDEX IF NOT EXISTS "idx_rateb_bi_widgets_fk_bi_wid_dash" ON "rateb_bi_widgets" ("dashboard_id");

CREATE TABLE IF NOT EXISTS "rateb_blood_donors" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "donor_no" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "blood_group" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_blood_donors_uq_blood_donor" ON "rateb_blood_donors" ("company_id", "donor_no");

CREATE TABLE IF NOT EXISTS "rateb_blood_units" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "unit_no" TEXT NOT NULL,
  "blood_group" TEXT NOT NULL,
  "donor_id" INTEGER DEFAULT NULL,
  "collected_at" TEXT NOT NULL,
  "expiry_at" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'available'
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_blood_units_uq_blood_unit" ON "rateb_blood_units" ("company_id", "unit_no");

CREATE TABLE IF NOT EXISTS "rateb_branch_transfers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "transfer_no" TEXT NOT NULL,
  "transfer_type" TEXT NOT NULL,
  "source_branch_id" INTEGER NOT NULL,
  "dest_branch_id" INTEGER NOT NULL,
  "source_entity_type" TEXT DEFAULT NULL,
  "source_entity_id" INTEGER DEFAULT NULL,
  "quantity" REAL DEFAULT NULL,
  "amount" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "payload_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "approved_by" INTEGER DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "created_at" TEXT DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_branch_transfers_uq_bt_company_no" ON "rateb_branch_transfers" ("company_id", "transfer_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_branch_transfers_idx_bt_company" ON "rateb_branch_transfers" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_branch_transfers_idx_bt_source" ON "rateb_branch_transfers" ("source_branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_branch_transfers_idx_bt_dest" ON "rateb_branch_transfers" ("dest_branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_branch_transfers_idx_bt_type" ON "rateb_branch_transfers" ("transfer_type");
CREATE INDEX IF NOT EXISTS "idx_rateb_branch_transfers_idx_bt_status" ON "rateb_branch_transfers" ("status");

CREATE TABLE IF NOT EXISTS "rateb_branches" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "code" TEXT DEFAULT NULL,
  "address" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "map_url" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "is_main" INTEGER NOT NULL DEFAULT 0,
  "is_archived" INTEGER NOT NULL DEFAULT 0,
  "archived_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_branches_uq_branch_company_code" ON "rateb_branches" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_branches_idx_branch_company" ON "rateb_branches" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_branches_idx_branch_code" ON "rateb_branches" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_branches_idx_branch_company_archived" ON "rateb_branches" ("company_id", "is_archived");

CREATE TABLE IF NOT EXISTS "rateb_budget_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "fiscal_year" INTEGER NOT NULL,
  "account_id" INTEGER NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_budget_lines_uq_budget_company_year_acct" ON "rateb_budget_lines" ("company_id", "fiscal_year", "account_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_budget_lines_idx_budget_company_year" ON "rateb_budget_lines" ("company_id", "fiscal_year");
CREATE INDEX IF NOT EXISTS "idx_rateb_budget_lines_fk_budget_account" ON "rateb_budget_lines" ("account_id");

CREATE TABLE IF NOT EXISTS "rateb_cash_vouchers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "voucher_no" TEXT NOT NULL,
  "voucher_type" TEXT NOT NULL,
  "voucher_date" TEXT NOT NULL,
  "amount" REAL NOT NULL,
  "party_name" TEXT DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "description" TEXT NOT NULL,
  "description_ar" TEXT DEFAULT NULL,
  "counter_account_id" INTEGER NOT NULL,
  "bank_account_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "reject_reason" TEXT DEFAULT NULL,
  "rejected_at" TEXT DEFAULT NULL,
  "rejected_by" INTEGER DEFAULT NULL,
  "journal_entry_id" INTEGER DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "posted_at" TEXT DEFAULT NULL,
  "submitted_for_approval_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_uq_cv_company_no" ON "rateb_cash_vouchers" ("company_id", "voucher_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_idx_cv_company" ON "rateb_cash_vouchers" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_idx_cv_status" ON "rateb_cash_vouchers" ("status");
CREATE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_fk_cv_counter" ON "rateb_cash_vouchers" ("counter_account_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_fk_cv_journal" ON "rateb_cash_vouchers" ("journal_entry_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_idx_cv_bank" ON "rateb_cash_vouchers" ("bank_account_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_idx_cv_customer" ON "rateb_cash_vouchers" ("customer_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_idx_cv_submitted" ON "rateb_cash_vouchers" ("submitted_for_approval_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_cash_vouchers_idx_cv_branch" ON "rateb_cash_vouchers" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_chart_of_accounts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT DEFAULT NULL,
  "company_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "account_type" TEXT NOT NULL DEFAULT 'asset',
  "parent_id" INTEGER DEFAULT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_chart_of_accounts_uq_coa_company_code" ON "rateb_chart_of_accounts" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_chart_of_accounts_idx_coa_company" ON "rateb_chart_of_accounts" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_cms_about" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "story_en" TEXT DEFAULT NULL,
  "story_ar" TEXT DEFAULT NULL,
  "vision_en" TEXT DEFAULT NULL,
  "vision_ar" TEXT DEFAULT NULL,
  "mission_en" TEXT DEFAULT NULL,
  "mission_ar" TEXT DEFAULT NULL,
  "values_json" TEXT DEFAULT NULL,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "rateb_cms_analytics" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "google_analytics_id" TEXT DEFAULT NULL,
  "google_tag_manager_id" TEXT DEFAULT NULL,
  "meta_pixel_id" TEXT DEFAULT NULL,
  "tiktok_pixel_id" TEXT DEFAULT NULL,
  "custom_head_code" TEXT DEFAULT NULL,
  "custom_body_code" TEXT DEFAULT NULL,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "rateb_cms_article_tags" (
  "article_id" INTEGER NOT NULL,
  "tag_id" INTEGER NOT NULL,
  PRIMARY KEY ("article_id", "tag_id")
);

CREATE TABLE IF NOT EXISTS "rateb_cms_blocks" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "section_id" INTEGER NOT NULL,
  "block_type" TEXT NOT NULL DEFAULT 'text',
  "title_en" TEXT NOT NULL DEFAULT '',
  "title_ar" TEXT NOT NULL DEFAULT '',
  "content_en" TEXT DEFAULT NULL,
  "content_ar" TEXT DEFAULT NULL,
  "icon" TEXT DEFAULT NULL,
  "image_path" TEXT DEFAULT NULL,
  "link_url" TEXT DEFAULT NULL,
  "settings_json" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_cms_blocks_idx_cms_block_section" ON "rateb_cms_blocks" ("section_id", "sort_order");

CREATE TABLE IF NOT EXISTS "rateb_cms_blog_articles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "category_id" INTEGER DEFAULT NULL,
  "author_id" INTEGER DEFAULT NULL,
  "slug" TEXT NOT NULL,
  "title_en" TEXT NOT NULL,
  "title_ar" TEXT NOT NULL DEFAULT '',
  "excerpt_en" TEXT DEFAULT NULL,
  "excerpt_ar" TEXT DEFAULT NULL,
  "content_en" TEXT DEFAULT NULL,
  "content_ar" TEXT DEFAULT NULL,
  "featured_image" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "published_at" TEXT DEFAULT NULL,
  "meta_title_en" TEXT DEFAULT NULL,
  "meta_title_ar" TEXT DEFAULT NULL,
  "meta_description_en" TEXT DEFAULT NULL,
  "meta_description_ar" TEXT DEFAULT NULL,
  "views_count" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_blog_articles_uq_cms_article_slug" ON "rateb_cms_blog_articles" ("slug");
CREATE INDEX IF NOT EXISTS "idx_rateb_cms_blog_articles_idx_cms_article_status" ON "rateb_cms_blog_articles" ("status", "published_at");

CREATE TABLE IF NOT EXISTS "rateb_cms_blog_authors" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT '',
  "email" TEXT DEFAULT NULL,
  "bio_en" TEXT DEFAULT NULL,
  "bio_ar" TEXT DEFAULT NULL,
  "photo_path" TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "rateb_cms_blog_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT ''
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_blog_categories_uq_cms_blog_cat" ON "rateb_cms_blog_categories" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_blog_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT ''
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_blog_tags_uq_cms_blog_tag" ON "rateb_cms_blog_tags" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_careers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "title_en" TEXT NOT NULL,
  "title_ar" TEXT NOT NULL DEFAULT '',
  "department_en" TEXT DEFAULT NULL,
  "department_ar" TEXT DEFAULT NULL,
  "location_en" TEXT DEFAULT NULL,
  "location_ar" TEXT DEFAULT NULL,
  "description_en" TEXT DEFAULT NULL,
  "description_ar" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open'
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_careers_uq_cms_career_slug" ON "rateb_cms_careers" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_contact_settings" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "address_en" TEXT DEFAULT NULL,
  "address_ar" TEXT DEFAULT NULL,
  "working_hours_en" TEXT DEFAULT NULL,
  "working_hours_ar" TEXT DEFAULT NULL,
  "social_json" TEXT DEFAULT NULL,
  "map_embed" TEXT DEFAULT NULL,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "rateb_cms_faq_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT '',
  "sort_order" INTEGER NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_faq_categories_uq_cms_faq_cat" ON "rateb_cms_faq_categories" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_faqs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "category_id" INTEGER DEFAULT NULL,
  "question_en" TEXT NOT NULL,
  "question_ar" TEXT NOT NULL DEFAULT '',
  "answer_en" TEXT DEFAULT NULL,
  "answer_ar" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS "rateb_cms_footer_columns" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "title_en" TEXT NOT NULL DEFAULT '',
  "title_ar" TEXT NOT NULL DEFAULT '',
  "links_json" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS "rateb_cms_help_articles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "title_en" TEXT NOT NULL,
  "title_ar" TEXT NOT NULL DEFAULT '',
  "content_en" TEXT DEFAULT NULL,
  "content_ar" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'published'
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_help_articles_uq_cms_help_slug" ON "rateb_cms_help_articles" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_kb_articles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "title_en" TEXT NOT NULL,
  "title_ar" TEXT NOT NULL DEFAULT '',
  "content_en" TEXT DEFAULT NULL,
  "content_ar" TEXT DEFAULT NULL,
  "category" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'published'
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_kb_articles_uq_cms_kb_slug" ON "rateb_cms_kb_articles" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_lead_notes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "lead_id" INTEGER NOT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "note" TEXT NOT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_cms_lead_notes_idx_cms_lead_note" ON "rateb_cms_lead_notes" ("lead_id");

CREATE TABLE IF NOT EXISTS "rateb_cms_leads" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "lead_type" TEXT NOT NULL DEFAULT 'contact',
  "name" TEXT NOT NULL,
  "email" TEXT NOT NULL,
  "phone" TEXT DEFAULT NULL,
  "company" TEXT DEFAULT NULL,
  "message" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'new',
  "assigned_user_id" INTEGER DEFAULT NULL,
  "source_page" TEXT DEFAULT NULL,
  "ip_address" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "branch_id" INTEGER DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_cms_leads_idx_cms_lead_type_status" ON "rateb_cms_leads" ("lead_type", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_cms_leads_idx_lead_company" ON "rateb_cms_leads" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_cms_leads_idx_lead_branch" ON "rateb_cms_leads" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_cms_media" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "category_id" INTEGER DEFAULT NULL,
  "file_name" TEXT NOT NULL,
  "file_path" TEXT NOT NULL,
  "mime_type" TEXT NOT NULL,
  "file_size" INTEGER NOT NULL DEFAULT 0,
  "alt_en" TEXT DEFAULT NULL,
  "alt_ar" TEXT DEFAULT NULL,
  "uploaded_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_cms_media_idx_cms_media_cat" ON "rateb_cms_media" ("category_id");

CREATE TABLE IF NOT EXISTS "rateb_cms_media_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT ''
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_media_categories_uq_cms_media_cat" ON "rateb_cms_media_categories" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_menu_items" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "menu_id" INTEGER NOT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "label_en" TEXT NOT NULL DEFAULT '',
  "label_ar" TEXT NOT NULL DEFAULT '',
  "url" TEXT NOT NULL DEFAULT '',
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_menu_items_uq_cms_menu_item_url" ON "rateb_cms_menu_items" ("menu_id", "url");
CREATE INDEX IF NOT EXISTS "idx_rateb_cms_menu_items_idx_cms_menu_item" ON "rateb_cms_menu_items" ("menu_id", "sort_order");

CREATE TABLE IF NOT EXISTS "rateb_cms_menus" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "name_en" TEXT NOT NULL DEFAULT '',
  "name_ar" TEXT NOT NULL DEFAULT '',
  "location" TEXT NOT NULL DEFAULT 'header'
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_menus_uq_cms_menu_slug" ON "rateb_cms_menus" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_newsletter_campaigns" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "subject_en" TEXT NOT NULL DEFAULT '',
  "subject_ar" TEXT NOT NULL DEFAULT '',
  "body_html_en" TEXT DEFAULT NULL,
  "body_html_ar" TEXT DEFAULT NULL,
  "segment_slug" TEXT DEFAULT 'general',
  "status" TEXT NOT NULL DEFAULT 'draft',
  "scheduled_at" TEXT DEFAULT NULL,
  "sent_at" TEXT DEFAULT NULL,
  "sent_count" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "rateb_cms_newsletter_segments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT '',
  "description_en" TEXT DEFAULT NULL,
  "description_ar" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_newsletter_segments_uq_cms_segment" ON "rateb_cms_newsletter_segments" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_newsletter_subscribers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "email" TEXT NOT NULL,
  "name" TEXT DEFAULT NULL,
  "segment" TEXT DEFAULT 'general',
  "status" TEXT NOT NULL DEFAULT 'active',
  "subscribed_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_newsletter_subscribers_uq_cms_newsletter_email" ON "rateb_cms_newsletter_subscribers" ("email");

CREATE TABLE IF NOT EXISTS "rateb_cms_offices" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT '',
  "address_en" TEXT DEFAULT NULL,
  "address_ar" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "map_url" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS "rateb_cms_pages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "title_en" TEXT NOT NULL DEFAULT '',
  "title_ar" TEXT NOT NULL DEFAULT '',
  "content_en" TEXT DEFAULT NULL,
  "content_ar" TEXT DEFAULT NULL,
  "template" TEXT NOT NULL DEFAULT 'default',
  "status" TEXT NOT NULL DEFAULT 'published',
  "published_at" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_pages_uq_cms_page_slug" ON "rateb_cms_pages" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_partners" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT '',
  "logo_path" TEXT DEFAULT NULL,
  "website_url" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS "rateb_cms_redirects" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "from_path" TEXT NOT NULL,
  "to_path" TEXT NOT NULL,
  "status_code" INTEGER NOT NULL DEFAULT 301,
  "is_active" INTEGER NOT NULL DEFAULT 1
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_redirects_uq_cms_redirect_from" ON "rateb_cms_redirects" ("from_path");

CREATE TABLE IF NOT EXISTS "rateb_cms_robots" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "content" TEXT NOT NULL,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "rateb_cms_sections" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "page_slug" TEXT NOT NULL,
  "section_key" TEXT NOT NULL,
  "title_en" TEXT NOT NULL DEFAULT '',
  "title_ar" TEXT NOT NULL DEFAULT '',
  "body_en" TEXT DEFAULT NULL,
  "body_ar" TEXT DEFAULT NULL,
  "settings_json" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_sections_uq_cms_section_page_key" ON "rateb_cms_sections" ("page_slug", "section_key");
CREATE INDEX IF NOT EXISTS "idx_rateb_cms_sections_idx_cms_section_page" ON "rateb_cms_sections" ("page_slug", "sort_order");

CREATE TABLE IF NOT EXISTS "rateb_cms_seo" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "page_slug" TEXT NOT NULL,
  "meta_title_en" TEXT DEFAULT NULL,
  "meta_title_ar" TEXT DEFAULT NULL,
  "meta_description_en" TEXT DEFAULT NULL,
  "meta_description_ar" TEXT DEFAULT NULL,
  "og_title_en" TEXT DEFAULT NULL,
  "og_title_ar" TEXT DEFAULT NULL,
  "og_description_en" TEXT DEFAULT NULL,
  "og_description_ar" TEXT DEFAULT NULL,
  "og_image" TEXT DEFAULT NULL,
  "twitter_card" TEXT DEFAULT 'summary_large_image',
  "canonical_url" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_seo_uq_cms_seo_slug" ON "rateb_cms_seo" ("page_slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_service_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT '',
  "icon" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_service_categories_uq_cms_svc_cat" ON "rateb_cms_service_categories" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_services" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "category_id" INTEGER DEFAULT NULL,
  "slug" TEXT NOT NULL,
  "title_en" TEXT NOT NULL,
  "title_ar" TEXT NOT NULL DEFAULT '',
  "summary_en" TEXT DEFAULT NULL,
  "summary_ar" TEXT DEFAULT NULL,
  "content_en" TEXT DEFAULT NULL,
  "content_ar" TEXT DEFAULT NULL,
  "icon" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'published'
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_services_uq_cms_service_slug" ON "rateb_cms_services" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_cms_slides" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "title_en" TEXT NOT NULL DEFAULT '',
  "title_ar" TEXT NOT NULL DEFAULT '',
  "subtitle_en" TEXT DEFAULT NULL,
  "subtitle_ar" TEXT DEFAULT NULL,
  "image_path" TEXT DEFAULT NULL,
  "video_url" TEXT DEFAULT NULL,
  "cta_label_en" TEXT DEFAULT NULL,
  "cta_label_ar" TEXT DEFAULT NULL,
  "cta_url" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "starts_at" TEXT DEFAULT NULL,
  "ends_at" TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "rateb_cms_system_status" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "component_en" TEXT NOT NULL,
  "component_ar" TEXT NOT NULL DEFAULT '',
  "status" TEXT NOT NULL DEFAULT 'operational',
  "message_en" TEXT DEFAULT NULL,
  "message_ar" TEXT DEFAULT NULL,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "rateb_cms_team_members" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name_en" TEXT NOT NULL,
  "name_ar" TEXT NOT NULL DEFAULT '',
  "position_en" TEXT NOT NULL DEFAULT '',
  "position_ar" TEXT NOT NULL DEFAULT '',
  "bio_en" TEXT DEFAULT NULL,
  "bio_ar" TEXT DEFAULT NULL,
  "photo_path" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS "rateb_cms_testimonials" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "customer_name_en" TEXT NOT NULL,
  "customer_name_ar" TEXT NOT NULL DEFAULT '',
  "position_en" TEXT NOT NULL DEFAULT '',
  "position_ar" TEXT NOT NULL DEFAULT '',
  "company_en" TEXT NOT NULL DEFAULT '',
  "company_ar" TEXT NOT NULL DEFAULT '',
  "quote_en" TEXT NOT NULL,
  "quote_ar" TEXT DEFAULT NULL,
  "rating" INTEGER NOT NULL DEFAULT 5,
  "photo_path" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "sort_order" INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS "rateb_cms_theme" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "primary_color" TEXT DEFAULT '#1a5fb4',
  "secondary_color" TEXT DEFAULT '#3584e4',
  "font_family" TEXT DEFAULT 'Tajawal',
  "logo_path" TEXT DEFAULT NULL,
  "favicon_path" TEXT DEFAULT NULL,
  "custom_css" TEXT DEFAULT NULL,
  "custom_js" TEXT DEFAULT NULL,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "rateb_cms_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "year_label" TEXT NOT NULL,
  "title_en" TEXT NOT NULL,
  "title_ar" TEXT NOT NULL DEFAULT '',
  "body_en" TEXT DEFAULT NULL,
  "body_ar" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS "rateb_cms_visitors" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "visit_date" TEXT NOT NULL,
  "page_views" INTEGER NOT NULL DEFAULT 0,
  "unique_visitors" INTEGER NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cms_visitors_uq_cms_visitor_date" ON "rateb_cms_visitors" ("visit_date");

CREATE TABLE IF NOT EXISTS "rateb_companies" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "slug" TEXT NOT NULL,
  "email" TEXT NOT NULL,
  "phone" TEXT DEFAULT NULL,
  "address" TEXT DEFAULT NULL,
  "country" TEXT DEFAULT NULL,
  "logo_path" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "plan_id" INTEGER DEFAULT NULL,
  "storage_limit_mb" INTEGER NOT NULL DEFAULT 1024,
  "user_limit" INTEGER NOT NULL DEFAULT 10,
  "branch_limit" INTEGER NOT NULL DEFAULT 0,
  "modules" TEXT DEFAULT NULL,
  "settings" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_companies_slug" ON "rateb_companies" ("slug");
CREATE INDEX IF NOT EXISTS "idx_rateb_companies_idx_companies_status" ON "rateb_companies" ("status");
CREATE INDEX IF NOT EXISTS "idx_rateb_companies_idx_companies_plan" ON "rateb_companies" ("plan_id");

CREATE TABLE IF NOT EXISTS "rateb_company_tax_profiles" (
  "company_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "vat_number" TEXT DEFAULT NULL,
  "cr_number" TEXT DEFAULT NULL,
  "legal_name_ar" TEXT DEFAULT NULL,
  "legal_name_en" TEXT DEFAULT NULL,
  "street" TEXT DEFAULT NULL,
  "building_no" TEXT DEFAULT NULL,
  "city" TEXT DEFAULT NULL,
  "postal_code" TEXT DEFAULT NULL,
  "zatca_enabled" INTEGER NOT NULL DEFAULT 0,
  "zatca_environment" TEXT NOT NULL DEFAULT 'sandbox',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "rateb_contract_renewals" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "renewal_no" TEXT DEFAULT NULL,
  "contract_id" INTEGER NOT NULL,
  "renewal_date" TEXT NOT NULL,
  "new_end_date" TEXT DEFAULT NULL,
  "new_value" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'planned',
  "manager_approval" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_contract_renewals_fk_cr_company" ON "rateb_contract_renewals" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_contract_renewals_fk_cr_contract" ON "rateb_contract_renewals" ("contract_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_contract_renewals_idx_cr_branch" ON "rateb_contract_renewals" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_contracts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "contract_no" TEXT NOT NULL,
  "barcode" TEXT DEFAULT NULL,
  "qr_code" TEXT DEFAULT NULL,
  "title" TEXT NOT NULL,
  "supplier_id" INTEGER DEFAULT NULL,
  "contract_type" TEXT DEFAULT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT DEFAULT NULL,
  "renewal_date" TEXT DEFAULT NULL,
  "alert_days" INTEGER NOT NULL DEFAULT 30,
  "value" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "approval_status" TEXT NOT NULL DEFAULT 'draft',
  "document_path" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "signature_status" TEXT NOT NULL DEFAULT 'none',
  "signature_trail" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_contracts_uq_contracts_company_no" ON "rateb_contracts" ("company_id", "contract_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_contracts_idx_contracts_expiry" ON "rateb_contracts" ("company_id", "end_date", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_contracts_idx_ctr_doc_barcode" ON "rateb_contracts" ("barcode");
CREATE INDEX IF NOT EXISTS "idx_rateb_contracts_idx_contract_branch" ON "rateb_contracts" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_cost_centers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cost_centers_uq_cc_company_code" ON "rateb_cost_centers" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_cost_centers_idx_cc_company" ON "rateb_cost_centers" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_cost_centers_idx_cc_branch" ON "rateb_cost_centers" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_activities" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "activity_type" TEXT NOT NULL DEFAULT 'other',
  "subject" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "related_type" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "lead_id" INTEGER DEFAULT NULL,
  "opportunity_id" INTEGER DEFAULT NULL,
  "contact_id" INTEGER DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "activity_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_activities_uq_crm_act_uuid" ON "rateb_crm_activities" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_activities_idx_crm_act_company" ON "rateb_crm_activities" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_activities_idx_crm_act_related" ON "rateb_crm_activities" ("related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_activities_idx_crm_act_lead" ON "rateb_crm_activities" ("lead_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "related_type" TEXT NOT NULL,
  "related_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER NOT NULL,
  "role_label" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_assignments_uq_crm_asg_uuid" ON "rateb_crm_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_assignments_idx_crm_asg_related" ON "rateb_crm_assignments" ("company_id", "related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_assignments_idx_crm_asg_user" ON "rateb_crm_assignments" ("assignee_user_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_calls" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "subject" TEXT NOT NULL,
  "direction" TEXT NOT NULL DEFAULT 'outbound',
  "called_at" TEXT NOT NULL,
  "duration_sec" INTEGER DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "lead_id" INTEGER DEFAULT NULL,
  "opportunity_id" INTEGER DEFAULT NULL,
  "contact_id" INTEGER DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "outcome" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'logged',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_calls_uq_crm_call_uuid" ON "rateb_crm_calls" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_calls_idx_crm_call_company" ON "rateb_crm_calls" ("company_id", "called_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_calls_idx_crm_call_lead" ON "rateb_crm_calls" ("lead_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_campaigns" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "campaign_type" TEXT NOT NULL DEFAULT 'other',
  "start_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "budget" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_campaigns_uq_crm_camp_uuid" ON "rateb_crm_campaigns" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_campaigns_uq_crm_camp_code" ON "rateb_crm_campaigns" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_campaigns_idx_crm_camp_company" ON "rateb_crm_campaigns" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_crm_companies" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "industry" TEXT DEFAULT NULL,
  "website" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "city" TEXT DEFAULT NULL,
  "country_code" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_companies_uq_crm_co_uuid" ON "rateb_crm_companies" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_companies_uq_crm_co_code" ON "rateb_crm_companies" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_companies_idx_crm_co_company" ON "rateb_crm_companies" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_companies_idx_crm_co_customer" ON "rateb_crm_companies" ("customer_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_companies_idx_crm_co_branch" ON "rateb_crm_companies" ("company_id", "branch_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_contacts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "full_name" TEXT NOT NULL,
  "full_name_ar" TEXT DEFAULT NULL,
  "job_title" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "mobile" TEXT DEFAULT NULL,
  "is_primary" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_contacts_uq_crm_ct_uuid" ON "rateb_crm_contacts" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_contacts_idx_crm_ct_company" ON "rateb_crm_contacts" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_contacts_idx_crm_ct_crmco" ON "rateb_crm_contacts" ("crm_company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_contacts_idx_crm_ct_customer" ON "rateb_crm_contacts" ("customer_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_entity_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "tag_id" INTEGER NOT NULL,
  "related_type" TEXT NOT NULL,
  "related_id" INTEGER NOT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_entity_tags_uq_crm_etag" ON "rateb_crm_entity_tags" ("company_id", "tag_id", "related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_entity_tags_idx_crm_etag_related" ON "rateb_crm_entity_tags" ("related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_entity_tags_fk_crm_etag_tag" ON "rateb_crm_entity_tags" ("tag_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_lead_sources" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_lead_sources_uq_crm_src_uuid" ON "rateb_crm_lead_sources" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_lead_sources_uq_crm_src_code" ON "rateb_crm_lead_sources" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_lead_sources_idx_crm_src_company" ON "rateb_crm_lead_sources" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_crm_leads" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "lead_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "contact_name" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "contact_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "source_id" INTEGER DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'new',
  "estimated_value" REAL DEFAULT NULL,
  "currency_code" TEXT DEFAULT NULL,
  "expected_close_date" TEXT DEFAULT NULL,
  "priority" TEXT NOT NULL DEFAULT 'normal',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_leads_uq_crm_lead_uuid" ON "rateb_crm_leads" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_leads_uq_crm_lead_no" ON "rateb_crm_leads" ("company_id", "lead_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_leads_idx_crm_lead_company" ON "rateb_crm_leads" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_leads_idx_crm_lead_status" ON "rateb_crm_leads" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_leads_idx_crm_lead_owner" ON "rateb_crm_leads" ("owner_user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_leads_idx_crm_lead_branch" ON "rateb_crm_leads" ("company_id", "branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_leads_fk_crm_lead_src" ON "rateb_crm_leads" ("source_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_leads_fk_crm_lead_crmco" ON "rateb_crm_leads" ("crm_company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_leads_fk_crm_lead_contact" ON "rateb_crm_leads" ("contact_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_meetings" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "subject" TEXT NOT NULL,
  "location" TEXT DEFAULT NULL,
  "starts_at" TEXT NOT NULL,
  "ends_at" TEXT DEFAULT NULL,
  "lead_id" INTEGER DEFAULT NULL,
  "opportunity_id" INTEGER DEFAULT NULL,
  "contact_id" INTEGER DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'scheduled',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_meetings_uq_crm_mtg_uuid" ON "rateb_crm_meetings" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_meetings_idx_crm_mtg_company" ON "rateb_crm_meetings" ("company_id", "starts_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_meetings_idx_crm_mtg_lead" ON "rateb_crm_meetings" ("lead_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_notes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "related_type" TEXT NOT NULL,
  "related_id" INTEGER NOT NULL,
  "lead_id" INTEGER DEFAULT NULL,
  "opportunity_id" INTEGER DEFAULT NULL,
  "contact_id" INTEGER DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "body" TEXT NOT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_notes_uq_crm_note_uuid" ON "rateb_crm_notes" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_notes_idx_crm_note_related" ON "rateb_crm_notes" ("company_id", "related_type", "related_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_opportunities" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "opportunity_no" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "lead_id" INTEGER DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "contact_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "pipeline_id" INTEGER DEFAULT NULL,
  "stage_id" INTEGER DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "currency_code" TEXT DEFAULT NULL,
  "probability_percent" REAL NOT NULL DEFAULT 0.00,
  "expected_close_date" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'open',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_opportunities_uq_crm_opp_uuid" ON "rateb_crm_opportunities" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_opportunities_uq_crm_opp_no" ON "rateb_crm_opportunities" ("company_id", "opportunity_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_opportunities_idx_crm_opp_company" ON "rateb_crm_opportunities" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_opportunities_idx_crm_opp_pipe" ON "rateb_crm_opportunities" ("pipeline_id", "stage_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_opportunities_idx_crm_opp_lead" ON "rateb_crm_opportunities" ("lead_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_opportunities_idx_crm_opp_owner" ON "rateb_crm_opportunities" ("owner_user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_opportunities_fk_crm_opp_stage" ON "rateb_crm_opportunities" ("stage_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_pipeline_stages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "pipeline_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "probability_percent" REAL NOT NULL DEFAULT 0.00,
  "is_won" INTEGER NOT NULL DEFAULT 0,
  "is_lost" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_pipeline_stages_uq_crm_stage_uuid" ON "rateb_crm_pipeline_stages" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_pipeline_stages_uq_crm_stage_code" ON "rateb_crm_pipeline_stages" ("pipeline_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_pipeline_stages_idx_crm_stage_pipe" ON "rateb_crm_pipeline_stages" ("company_id", "pipeline_id", "sort_order");

CREATE TABLE IF NOT EXISTS "rateb_crm_pipelines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "is_default" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_pipelines_uq_crm_pipe_uuid" ON "rateb_crm_pipelines" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_pipelines_uq_crm_pipe_code" ON "rateb_crm_pipelines" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_pipelines_idx_crm_pipe_company" ON "rateb_crm_pipelines" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_crm_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "lead_id" INTEGER NOT NULL,
  "from_status" TEXT NOT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_crm_status_history_idx_crm_sh_lead" ON "rateb_crm_status_history" ("company_id", "lead_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_status_history_fk_crm_sh_lead" ON "rateb_crm_status_history" ("lead_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_tags_uq_crm_tag_uuid" ON "rateb_crm_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_tags_uq_crm_tag_code" ON "rateb_crm_tags" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_tags_idx_crm_tag_company" ON "rateb_crm_tags" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_crm_tasks" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "subject" TEXT NOT NULL,
  "due_at" TEXT DEFAULT NULL,
  "priority" TEXT NOT NULL DEFAULT 'normal',
  "lead_id" INTEGER DEFAULT NULL,
  "opportunity_id" INTEGER DEFAULT NULL,
  "contact_id" INTEGER DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "reminder_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "completed_at" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_crm_tasks_uq_crm_task_uuid" ON "rateb_crm_tasks" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_tasks_idx_crm_task_company" ON "rateb_crm_tasks" ("company_id", "status", "due_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_tasks_idx_crm_task_owner" ON "rateb_crm_tasks" ("owner_user_id");

CREATE TABLE IF NOT EXISTS "rateb_crm_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "related_type" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "lead_id" INTEGER DEFAULT NULL,
  "opportunity_id" INTEGER DEFAULT NULL,
  "contact_id" INTEGER DEFAULT NULL,
  "crm_company_id" INTEGER DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_crm_timeline_idx_crm_tl_company" ON "rateb_crm_timeline" ("company_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_timeline_idx_crm_tl_lead" ON "rateb_crm_timeline" ("lead_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_crm_timeline_idx_crm_tl_related" ON "rateb_crm_timeline" ("related_type", "related_id");

CREATE TABLE IF NOT EXISTS "rateb_cron_health" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "job_name" TEXT NOT NULL,
  "last_run_at" TEXT NOT NULL,
  "next_expected_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'ok',
  "stats_json" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_cron_health_uq_cron_job" ON "rateb_cron_health" ("job_name");

CREATE TABLE IF NOT EXISTS "rateb_customers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "tax_id" TEXT DEFAULT NULL,
  "cost_center_id" INTEGER DEFAULT NULL,
  "price_group_id" INTEGER DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_customers_uq_customer_company_code" ON "rateb_customers" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_customers_idx_customer_company" ON "rateb_customers" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_customers_idx_customer_cc" ON "rateb_customers" ("cost_center_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_customers_idx_customer_branch" ON "rateb_customers" ("branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_customers_idx_customer_price_group" ON "rateb_customers" ("price_group_id");

CREATE TABLE IF NOT EXISTS "rateb_device_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_device_categories_fk_dcat_company" ON "rateb_device_categories" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_device_service_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "service_no" TEXT DEFAULT NULL,
  "device_id" INTEGER NOT NULL,
  "service_date" TEXT NOT NULL,
  "service_type" TEXT NOT NULL,
  "provider" TEXT DEFAULT NULL,
  "cost" REAL NOT NULL DEFAULT 0.00,
  "notes" TEXT DEFAULT NULL,
  "manager_approval" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_device_service_history_fk_dsh_company" ON "rateb_device_service_history" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_device_service_history_fk_dsh_device" ON "rateb_device_service_history" ("device_id");

CREATE TABLE IF NOT EXISTS "rateb_device_spare_parts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "device_id" INTEGER NOT NULL,
  "part_name" TEXT NOT NULL,
  "part_no" TEXT DEFAULT NULL,
  "quantity" REAL NOT NULL DEFAULT 0.000,
  "reorder_level" REAL NOT NULL DEFAULT 0.000,
  "manager_approval" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_device_spare_parts_fk_dsp_company" ON "rateb_device_spare_parts" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_device_spare_parts_fk_dsp_device" ON "rateb_device_spare_parts" ("device_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_approvals_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "approval_stage" TEXT NOT NULL,
  "approver_user_id" INTEGER DEFAULT NULL,
  "approval_status" TEXT NOT NULL DEFAULT 'pending',
  "decided_at" TEXT DEFAULT NULL,
  "legacy_approval_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_approvals_meta_uq_dms_appr_uuid" ON "rateb_dms_approvals_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_approvals_meta_idx_dms_appr_doc" ON "rateb_dms_approvals_meta" ("company_id", "document_id", "approval_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_approvals_meta_fk_dms_appr_doc" ON "rateb_dms_approvals_meta" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_audit_logs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER DEFAULT NULL,
  "action_type" TEXT NOT NULL,
  "actor_user_id" INTEGER DEFAULT NULL,
  "resource_type" TEXT DEFAULT NULL,
  "resource_id" INTEGER DEFAULT NULL,
  "detail_text" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_audit_logs_uq_dms_audit_uuid" ON "rateb_dms_audit_logs" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_audit_logs_idx_dms_audit_doc" ON "rateb_dms_audit_logs" ("company_id", "document_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_audit_logs_idx_dms_audit_action" ON "rateb_dms_audit_logs" ("company_id", "action_type", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_dms_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_categories_uq_dms_cat_uuid" ON "rateb_dms_categories" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_categories_uq_dms_cat_code" ON "rateb_dms_categories" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_dms_checkouts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "checked_out_at" TEXT NOT NULL,
  "due_at" TEXT DEFAULT NULL,
  "checked_in_at" TEXT DEFAULT NULL,
  "checkout_status" TEXT NOT NULL DEFAULT 'open',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_checkouts_uq_dms_chk_uuid" ON "rateb_dms_checkouts" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_checkouts_idx_dms_chk_doc" ON "rateb_dms_checkouts" ("company_id", "document_id", "checkout_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_checkouts_fk_dms_chk_doc" ON "rateb_dms_checkouts" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "comment_text" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_comments_uq_dms_cmt_uuid" ON "rateb_dms_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_comments_idx_dms_cmt_doc" ON "rateb_dms_comments" ("company_id", "document_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_comments_fk_dms_cmt_doc" ON "rateb_dms_comments" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_document_links" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "linked_entity_type" TEXT NOT NULL,
  "linked_entity_id" INTEGER NOT NULL,
  "link_role" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_document_links_uq_dms_link_uuid" ON "rateb_dms_document_links" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_links_idx_dms_link_entity" ON "rateb_dms_document_links" ("company_id", "linked_entity_type", "linked_entity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_links_idx_dms_link_doc" ON "rateb_dms_document_links" ("company_id", "document_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_links_fk_dms_link_doc" ON "rateb_dms_document_links" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_document_metadata" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "meta_key" TEXT NOT NULL,
  "meta_value" TEXT DEFAULT NULL,
  "value_type" TEXT NOT NULL DEFAULT 'string',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_document_metadata_uq_dms_meta_uuid" ON "rateb_dms_document_metadata" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_document_metadata_uq_dms_meta_key" ON "rateb_dms_document_metadata" ("company_id", "document_id", "meta_key");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_metadata_idx_dms_meta_doc" ON "rateb_dms_document_metadata" ("company_id", "document_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_metadata_fk_dms_meta_doc" ON "rateb_dms_document_metadata" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_document_relations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "source_document_id" INTEGER NOT NULL,
  "target_document_id" INTEGER NOT NULL,
  "relation_type" TEXT NOT NULL DEFAULT 'related',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_document_relations_uq_dms_rel_uuid" ON "rateb_dms_document_relations" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_relations_idx_dms_rel_src" ON "rateb_dms_document_relations" ("company_id", "source_document_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_relations_idx_dms_rel_tgt" ON "rateb_dms_document_relations" ("company_id", "target_document_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_relations_fk_dms_rel_src" ON "rateb_dms_document_relations" ("source_document_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_document_relations_fk_dms_rel_tgt" ON "rateb_dms_document_relations" ("target_document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_documents" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "repository_id" INTEGER NOT NULL,
  "folder_id" INTEGER DEFAULT NULL,
  "category_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "title_ar" TEXT DEFAULT NULL,
  "document_type" TEXT DEFAULT NULL,
  "mime_type" TEXT DEFAULT NULL,
  "file_extension" TEXT DEFAULT NULL,
  "current_version_no" INTEGER NOT NULL DEFAULT 1,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "checked_out_by" INTEGER DEFAULT NULL,
  "checked_out_at" TEXT DEFAULT NULL,
  "published_at" TEXT DEFAULT NULL,
  "legacy_document_id" INTEGER DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_documents_uq_dms_doc_uuid" ON "rateb_dms_documents" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_documents_uq_dms_doc_code" ON "rateb_dms_documents" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_documents_idx_dms_doc_wf" ON "rateb_dms_documents" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_documents_idx_dms_doc_repo" ON "rateb_dms_documents" ("company_id", "repository_id", "folder_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_documents_idx_dms_doc_search" ON "rateb_dms_documents" ("company_id", "title", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_documents_fk_dms_doc_repo" ON "rateb_dms_documents" ("repository_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_documents_fk_dms_doc_folder" ON "rateb_dms_documents" ("folder_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_favorites" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER NOT NULL,
  "document_id" INTEGER NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_favorites_uq_dms_fav_uuid" ON "rateb_dms_favorites" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_favorites_uq_dms_fav_user_doc" ON "rateb_dms_favorites" ("company_id", "user_id", "document_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_favorites_idx_dms_fav_user" ON "rateb_dms_favorites" ("company_id", "user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_favorites_fk_dms_fav_doc" ON "rateb_dms_favorites" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_folders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "repository_id" INTEGER NOT NULL,
  "parent_folder_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "path_text" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_folders_uq_dms_folder_uuid" ON "rateb_dms_folders" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_folders_uq_dms_folder_code" ON "rateb_dms_folders" ("company_id", "repository_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_folders_idx_dms_folder_repo" ON "rateb_dms_folders" ("company_id", "repository_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_folders_idx_dms_folder_parent" ON "rateb_dms_folders" ("company_id", "parent_folder_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_folders_fk_dms_folder_repo" ON "rateb_dms_folders" ("repository_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_legal_holds" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER DEFAULT NULL,
  "folder_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "hold_reason" TEXT DEFAULT NULL,
  "hold_status" TEXT NOT NULL DEFAULT 'active',
  "effective_from" TEXT DEFAULT NULL,
  "effective_to" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_legal_holds_uq_dms_hold_uuid" ON "rateb_dms_legal_holds" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_legal_holds_uq_dms_hold_code" ON "rateb_dms_legal_holds" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_legal_holds_idx_dms_hold_doc" ON "rateb_dms_legal_holds" ("company_id", "document_id", "hold_status");

CREATE TABLE IF NOT EXISTS "rateb_dms_permissions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "resource_type" TEXT NOT NULL DEFAULT 'document',
  "resource_id" INTEGER NOT NULL,
  "grantee_type" TEXT NOT NULL DEFAULT 'user',
  "grantee_id" INTEGER NOT NULL,
  "permission_slug" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_permissions_uq_dms_perm_uuid" ON "rateb_dms_permissions" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_permissions_idx_dms_perm_res" ON "rateb_dms_permissions" ("company_id", "resource_type", "resource_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_permissions_idx_dms_perm_grantee" ON "rateb_dms_permissions" ("company_id", "grantee_type", "grantee_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_recent_items" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER NOT NULL,
  "document_id" INTEGER NOT NULL,
  "accessed_at" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_recent_items_uq_dms_recent_uuid" ON "rateb_dms_recent_items" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_recent_items_idx_dms_recent_user" ON "rateb_dms_recent_items" ("company_id", "user_id", "accessed_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_recent_items_fk_dms_recent_doc" ON "rateb_dms_recent_items" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_repositories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "storage_driver" TEXT NOT NULL DEFAULT 'local',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_repositories_uq_dms_repo_uuid" ON "rateb_dms_repositories" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_repositories_uq_dms_repo_code" ON "rateb_dms_repositories" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_repositories_idx_dms_repo_company" ON "rateb_dms_repositories" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_dms_retention_jobs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "policy_id" INTEGER NOT NULL,
  "document_id" INTEGER DEFAULT NULL,
  "job_status" TEXT NOT NULL DEFAULT 'pending',
  "scheduled_at" TEXT DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "result_summary" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_retention_jobs_uq_dms_retjob_uuid" ON "rateb_dms_retention_jobs" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_retention_jobs_idx_dms_retjob_policy" ON "rateb_dms_retention_jobs" ("company_id", "policy_id", "job_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_retention_jobs_fk_dms_retjob_policy" ON "rateb_dms_retention_jobs" ("policy_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_retention_policies" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "retention_days" INTEGER NOT NULL DEFAULT 365,
  "action_on_expiry" TEXT NOT NULL DEFAULT 'archive',
  "applies_to" TEXT NOT NULL DEFAULT 'document',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_retention_policies_uq_dms_retpol_uuid" ON "rateb_dms_retention_policies" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_retention_policies_uq_dms_retpol_code" ON "rateb_dms_retention_policies" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_retention_policies_idx_dms_retpol_company" ON "rateb_dms_retention_policies" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_dms_search_index" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "title_index" TEXT NOT NULL,
  "content_snippet" TEXT DEFAULT NULL,
  "tag_text" TEXT DEFAULT NULL,
  "category_text" TEXT DEFAULT NULL,
  "indexed_at" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_search_index_uq_dms_sidx_uuid" ON "rateb_dms_search_index" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_search_index_uq_dms_sidx_doc" ON "rateb_dms_search_index" ("company_id", "document_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_search_index_idx_dms_sidx_title" ON "rateb_dms_search_index" ("company_id", "title_index");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_search_index_fk_dms_sidx_doc" ON "rateb_dms_search_index" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_shares" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "share_token" TEXT NOT NULL,
  "shared_with_user_id" INTEGER DEFAULT NULL,
  "shared_with_email" TEXT DEFAULT NULL,
  "permission_level" TEXT NOT NULL DEFAULT 'view',
  "expires_at" TEXT DEFAULT NULL,
  "share_status" TEXT NOT NULL DEFAULT 'active',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_shares_uq_dms_share_uuid" ON "rateb_dms_shares" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_shares_uq_dms_share_token" ON "rateb_dms_shares" ("share_token");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_shares_idx_dms_share_doc" ON "rateb_dms_shares" ("company_id", "document_id", "share_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_shares_fk_dms_share_doc" ON "rateb_dms_shares" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_signature_events" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "signature_request_id" INTEGER NOT NULL,
  "event_type" TEXT NOT NULL DEFAULT 'sent',
  "event_at" TEXT NOT NULL,
  "ip_address" TEXT DEFAULT NULL,
  "user_agent" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_signature_events_uq_dms_sigev_uuid" ON "rateb_dms_signature_events" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_signature_events_idx_dms_sigev_req" ON "rateb_dms_signature_events" ("company_id", "signature_request_id", "event_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_signature_events_fk_dms_sigev_req" ON "rateb_dms_signature_events" ("signature_request_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_signature_requests" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "version_id" INTEGER DEFAULT NULL,
  "request_code" TEXT NOT NULL,
  "signer_user_id" INTEGER DEFAULT NULL,
  "signer_email" TEXT DEFAULT NULL,
  "request_status" TEXT NOT NULL DEFAULT 'pending',
  "requested_at" TEXT NOT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_signature_requests_uq_dms_sigreq_uuid" ON "rateb_dms_signature_requests" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_signature_requests_uq_dms_sigreq_code" ON "rateb_dms_signature_requests" ("company_id", "request_code");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_signature_requests_idx_dms_sigreq_doc" ON "rateb_dms_signature_requests" ("company_id", "document_id", "request_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_signature_requests_fk_dms_sigreq_doc" ON "rateb_dms_signature_requests" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_status_history_uq_dms_sh_uuid" ON "rateb_dms_status_history" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_status_history_idx_dms_sh_entity" ON "rateb_dms_status_history" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_tags_uq_dms_tag_uuid" ON "rateb_dms_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_tags_uq_dms_tag_code" ON "rateb_dms_tags" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_dms_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_timeline_uq_dms_tl_uuid" ON "rateb_dms_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_timeline_idx_dms_tl_company" ON "rateb_dms_timeline" ("company_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_timeline_idx_dms_tl_entity" ON "rateb_dms_timeline" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_dms_versions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "document_id" INTEGER NOT NULL,
  "version_no" INTEGER NOT NULL DEFAULT 1,
  "storage_path" TEXT DEFAULT NULL,
  "storage_driver" TEXT NOT NULL DEFAULT 'local',
  "file_size" INTEGER DEFAULT NULL,
  "checksum_sha256" TEXT DEFAULT NULL,
  "change_summary" TEXT DEFAULT NULL,
  "is_current" INTEGER NOT NULL DEFAULT 1,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_versions_uq_dms_ver_uuid" ON "rateb_dms_versions" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_dms_versions_uq_dms_ver_no" ON "rateb_dms_versions" ("company_id", "document_id", "version_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_versions_idx_dms_ver_doc" ON "rateb_dms_versions" ("company_id", "document_id", "is_current");
CREATE INDEX IF NOT EXISTS "idx_rateb_dms_versions_fk_dms_ver_doc" ON "rateb_dms_versions" ("document_id");

CREATE TABLE IF NOT EXISTS "rateb_documents" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "title" TEXT NOT NULL,
  "file_name" TEXT NOT NULL,
  "file_path" TEXT NOT NULL,
  "mime_type" TEXT DEFAULT NULL,
  "file_size" INTEGER NOT NULL DEFAULT 0,
  "uploaded_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_documents_idx_doc_entity" ON "rateb_documents" ("company_id", "entity_type", "entity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_documents_idx_doc_branch" ON "rateb_documents" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_activities" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "activity_type" TEXT NOT NULL DEFAULT 'note',
  "subject" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "activity_at" TEXT DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_activities_uq_eam_act_uuid" ON "rateb_eam_activities" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_activities_idx_eam_act_asset" ON "rateb_eam_activities" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_activities_fk_eam_act_asset" ON "rateb_eam_activities" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_asset_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER NOT NULL,
  "role_label" TEXT DEFAULT NULL,
  "assigned_at" TEXT NOT NULL,
  "released_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_asset_assignments_uq_eam_asg_uuid" ON "rateb_eam_asset_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_asset_assignments_idx_eam_asg_asset" ON "rateb_eam_asset_assignments" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_asset_assignments_fk_eam_asg_asset" ON "rateb_eam_asset_assignments" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_asset_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_asset_categories_uq_eam_cat_uuid" ON "rateb_eam_asset_categories" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_asset_categories_uq_eam_cat_code" ON "rateb_eam_asset_categories" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_asset_categories_idx_eam_cat_company" ON "rateb_eam_asset_categories" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_eam_asset_models" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "manufacturer_id" INTEGER DEFAULT NULL,
  "category_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_asset_models_uq_eam_mdl_uuid" ON "rateb_eam_asset_models" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_asset_models_uq_eam_mdl_code" ON "rateb_eam_asset_models" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_asset_models_idx_eam_mdl_company" ON "rateb_eam_asset_models" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_asset_models_fk_eam_mdl_mfr" ON "rateb_eam_asset_models" ("manufacturer_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_asset_models_fk_eam_mdl_cat" ON "rateb_eam_asset_models" ("category_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_asset_transfers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "from_location_id" INTEGER DEFAULT NULL,
  "to_location_id" INTEGER DEFAULT NULL,
  "from_branch_id" INTEGER DEFAULT NULL,
  "to_branch_id" INTEGER DEFAULT NULL,
  "transfer_at" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_asset_transfers_uq_eam_tr_uuid" ON "rateb_eam_asset_transfers" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_asset_transfers_idx_eam_tr_asset" ON "rateb_eam_asset_transfers" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_asset_transfers_fk_eam_tr_asset" ON "rateb_eam_asset_transfers" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_assets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_no" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "category_id" INTEGER DEFAULT NULL,
  "model_id" INTEGER DEFAULT NULL,
  "manufacturer_id" INTEGER DEFAULT NULL,
  "vendor_id" INTEGER DEFAULT NULL,
  "location_id" INTEGER DEFAULT NULL,
  "legacy_asset_id" INTEGER DEFAULT NULL,
  "serial_no" TEXT DEFAULT NULL,
  "barcode" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "priority" TEXT NOT NULL DEFAULT 'normal',
  "purchase_date" TEXT DEFAULT NULL,
  "purchase_cost" REAL DEFAULT NULL,
  "current_value" REAL DEFAULT NULL,
  "currency_code" TEXT DEFAULT NULL,
  "useful_life_months" INTEGER DEFAULT NULL,
  "salvage_value" REAL DEFAULT NULL,
  "depreciation_method" TEXT DEFAULT NULL,
  "placed_in_service_date" TEXT DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "custodian_user_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_assets_uq_eam_ast_uuid" ON "rateb_eam_assets" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_assets_uq_eam_ast_no" ON "rateb_eam_assets" ("company_id", "asset_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_assets_idx_eam_ast_company" ON "rateb_eam_assets" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_assets_idx_eam_ast_workflow" ON "rateb_eam_assets" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_assets_idx_eam_ast_legacy" ON "rateb_eam_assets" ("legacy_asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_assets_fk_eam_ast_cat" ON "rateb_eam_assets" ("category_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_assets_fk_eam_ast_mdl" ON "rateb_eam_assets" ("model_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_assets_fk_eam_ast_mfr" ON "rateb_eam_assets" ("manufacturer_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_assets_fk_eam_ast_vnd" ON "rateb_eam_assets" ("vendor_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_assets_fk_eam_ast_loc" ON "rateb_eam_assets" ("location_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_checklist_items" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "checklist_id" INTEGER NOT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "item_text" TEXT NOT NULL,
  "is_required" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_checklist_items_uq_eam_cki_uuid" ON "rateb_eam_checklist_items" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_checklist_items_idx_eam_cki_chk" ON "rateb_eam_checklist_items" ("checklist_id", "sort_order");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_checklist_items_fk_eam_cki_company" ON "rateb_eam_checklist_items" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_checklists" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "checklist_type" TEXT NOT NULL DEFAULT 'inspection',
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_checklists_uq_eam_chk_uuid" ON "rateb_eam_checklists" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_checklists_uq_eam_chk_code" ON "rateb_eam_checklists" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_eam_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "related_type" TEXT NOT NULL,
  "related_id" INTEGER NOT NULL,
  "body" TEXT NOT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_comments_uq_eam_cmt_uuid" ON "rateb_eam_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_comments_idx_eam_cmt_related" ON "rateb_eam_comments" ("company_id", "related_type", "related_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_document_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "related_type" TEXT NOT NULL,
  "related_id" INTEGER NOT NULL,
  "document_id" INTEGER DEFAULT NULL,
  "title" TEXT NOT NULL,
  "doc_type" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_document_meta_uq_eam_doc_uuid" ON "rateb_eam_document_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_document_meta_idx_eam_doc_related" ON "rateb_eam_document_meta" ("company_id", "related_type", "related_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_entity_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "tag_id" INTEGER NOT NULL,
  "related_type" TEXT NOT NULL,
  "related_id" INTEGER NOT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_entity_tags_uq_eam_etag" ON "rateb_eam_entity_tags" ("company_id", "tag_id", "related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_entity_tags_fk_eam_etag_tag" ON "rateb_eam_entity_tags" ("tag_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_inspections" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "checklist_id" INTEGER DEFAULT NULL,
  "work_order_id" INTEGER DEFAULT NULL,
  "inspection_no" TEXT NOT NULL,
  "inspected_at" TEXT NOT NULL,
  "result" TEXT NOT NULL DEFAULT 'pass',
  "inspector_user_id" INTEGER DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_inspections_uq_eam_insp_uuid" ON "rateb_eam_inspections" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_inspections_uq_eam_insp_no" ON "rateb_eam_inspections" ("company_id", "inspection_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_inspections_idx_eam_insp_asset" ON "rateb_eam_inspections" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_inspections_fk_eam_insp_asset" ON "rateb_eam_inspections" ("asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_inspections_fk_eam_insp_chk" ON "rateb_eam_inspections" ("checklist_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_inspections_fk_eam_insp_wo" ON "rateb_eam_inspections" ("work_order_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_insurance" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "insurer_name" TEXT DEFAULT NULL,
  "policy_no" TEXT DEFAULT NULL,
  "start_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "coverage_amount" REAL DEFAULT NULL,
  "currency_code" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_insurance_uq_eam_ins_uuid" ON "rateb_eam_insurance" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_insurance_idx_eam_ins_asset" ON "rateb_eam_insurance" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_insurance_fk_eam_ins_asset" ON "rateb_eam_insurance" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_locations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_locations_uq_eam_loc_uuid" ON "rateb_eam_locations" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_locations_uq_eam_loc_code" ON "rateb_eam_locations" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_locations_idx_eam_loc_company" ON "rateb_eam_locations" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_eam_maintenance_plans" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "plan_type" TEXT NOT NULL DEFAULT 'preventive',
  "frequency_days" INTEGER DEFAULT NULL,
  "next_due_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_plans_uq_eam_mpl_uuid" ON "rateb_eam_maintenance_plans" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_plans_uq_eam_mpl_code" ON "rateb_eam_maintenance_plans" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_plans_idx_eam_mpl_asset" ON "rateb_eam_maintenance_plans" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_plans_fk_eam_mpl_asset" ON "rateb_eam_maintenance_plans" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_maintenance_requests" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "request_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "request_type" TEXT NOT NULL DEFAULT 'corrective',
  "workflow_status" TEXT NOT NULL DEFAULT 'new',
  "priority" TEXT NOT NULL DEFAULT 'normal',
  "requested_by" INTEGER DEFAULT NULL,
  "scheduled_at" TEXT DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_requests_uq_eam_mrq_uuid" ON "rateb_eam_maintenance_requests" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_requests_uq_eam_mrq_no" ON "rateb_eam_maintenance_requests" ("company_id", "request_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_requests_idx_eam_mrq_asset" ON "rateb_eam_maintenance_requests" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_requests_idx_eam_mrq_workflow" ON "rateb_eam_maintenance_requests" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_maintenance_requests_fk_eam_mrq_asset" ON "rateb_eam_maintenance_requests" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_manufacturers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_manufacturers_uq_eam_mfr_uuid" ON "rateb_eam_manufacturers" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_manufacturers_uq_eam_mfr_code" ON "rateb_eam_manufacturers" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_manufacturers_idx_eam_mfr_company" ON "rateb_eam_manufacturers" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_eam_meter_readings" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "meter_name" TEXT NOT NULL DEFAULT 'hours',
  "reading_value" REAL NOT NULL,
  "reading_at" TEXT NOT NULL,
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_meter_readings_uq_eam_mtr_uuid" ON "rateb_eam_meter_readings" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_meter_readings_idx_eam_mtr_asset" ON "rateb_eam_meter_readings" ("company_id", "asset_id", "reading_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_meter_readings_fk_eam_mtr_asset" ON "rateb_eam_meter_readings" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_parts_consumption" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "work_order_id" INTEGER NOT NULL,
  "spare_part_id" INTEGER DEFAULT NULL,
  "part_name" TEXT NOT NULL,
  "quantity" REAL NOT NULL DEFAULT 1.00,
  "unit_cost" REAL DEFAULT NULL,
  "consumed_at" TEXT NOT NULL,
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_parts_consumption_uq_eam_pc_uuid" ON "rateb_eam_parts_consumption" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_parts_consumption_idx_eam_pc_wo" ON "rateb_eam_parts_consumption" ("company_id", "work_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_parts_consumption_fk_eam_pc_wo" ON "rateb_eam_parts_consumption" ("work_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_parts_consumption_fk_eam_pc_spr" ON "rateb_eam_parts_consumption" ("spare_part_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_spare_part_refs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER DEFAULT NULL,
  "part_sku" TEXT DEFAULT NULL,
  "part_name" TEXT NOT NULL,
  "inventory_item_id" INTEGER DEFAULT NULL,
  "qty_on_hand" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_spare_part_refs_uq_eam_spr_uuid" ON "rateb_eam_spare_part_refs" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_spare_part_refs_idx_eam_spr_asset" ON "rateb_eam_spare_part_refs" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_spare_part_refs_fk_eam_spr_asset" ON "rateb_eam_spare_part_refs" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "asset_id" INTEGER DEFAULT NULL,
  "from_status" TEXT NOT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_eam_status_history_idx_eam_sh_entity" ON "rateb_eam_status_history" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_tags_uq_eam_tag_uuid" ON "rateb_eam_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_tags_uq_eam_tag_code" ON "rateb_eam_tags" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_eam_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "related_type" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_timeline_uq_eam_tl_uuid" ON "rateb_eam_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_timeline_idx_eam_tl_asset" ON "rateb_eam_timeline" ("company_id", "asset_id", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_eam_vendors" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "supplier_id" INTEGER DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_vendors_uq_eam_vnd_uuid" ON "rateb_eam_vendors" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_vendors_uq_eam_vnd_code" ON "rateb_eam_vendors" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_vendors_idx_eam_vnd_company" ON "rateb_eam_vendors" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_eam_warranties" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "provider_name" TEXT DEFAULT NULL,
  "policy_no" TEXT DEFAULT NULL,
  "start_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "coverage_notes" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_warranties_uq_eam_war_uuid" ON "rateb_eam_warranties" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_warranties_idx_eam_war_asset" ON "rateb_eam_warranties" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_warranties_fk_eam_war_asset" ON "rateb_eam_warranties" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_eam_work_orders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "asset_id" INTEGER NOT NULL,
  "request_id" INTEGER DEFAULT NULL,
  "plan_id" INTEGER DEFAULT NULL,
  "work_order_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "work_type" TEXT NOT NULL DEFAULT 'corrective',
  "workflow_status" TEXT NOT NULL DEFAULT 'new',
  "assignee_user_id" INTEGER DEFAULT NULL,
  "scheduled_start" TEXT DEFAULT NULL,
  "scheduled_end" TEXT DEFAULT NULL,
  "started_at" TEXT DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "labor_hours" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_work_orders_uq_eam_wo_uuid" ON "rateb_eam_work_orders" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eam_work_orders_uq_eam_wo_no" ON "rateb_eam_work_orders" ("company_id", "work_order_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_work_orders_idx_eam_wo_asset" ON "rateb_eam_work_orders" ("company_id", "asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_work_orders_idx_eam_wo_workflow" ON "rateb_eam_work_orders" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_work_orders_fk_eam_wo_asset" ON "rateb_eam_work_orders" ("asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_work_orders_fk_eam_wo_req" ON "rateb_eam_work_orders" ("request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eam_work_orders_fk_eam_wo_plan" ON "rateb_eam_work_orders" ("plan_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_actions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER NOT NULL,
  "stage_id" INTEGER DEFAULT NULL,
  "action_type" TEXT NOT NULL,
  "actor_user_id" INTEGER DEFAULT NULL,
  "comment" TEXT DEFAULT NULL,
  "acted_at" TEXT NOT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_actions_uq_eap_act_uuid" ON "rateb_eap_actions" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_actions_idx_eap_act_request" ON "rateb_eap_actions" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_actions_fk_eap_act_request" ON "rateb_eap_actions" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_attachment_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER NOT NULL,
  "document_id" INTEGER DEFAULT NULL,
  "title" TEXT NOT NULL,
  "doc_type" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_attachment_meta_uq_eap_att_uuid" ON "rateb_eap_attachment_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_attachment_meta_idx_eap_att_request" ON "rateb_eap_attachment_meta" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_attachment_meta_fk_eap_att_request" ON "rateb_eap_attachment_meta" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_audit" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER DEFAULT NULL,
  "event_code" TEXT NOT NULL,
  "message" TEXT NOT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_audit_uq_eap_aud_uuid" ON "rateb_eap_audit" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_audit_idx_eap_aud_request" ON "rateb_eap_audit" ("company_id", "request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_chain_stages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "chain_id" INTEGER NOT NULL,
  "stage_id" INTEGER NOT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_chain_stages_uq_eap_cs_uuid" ON "rateb_eap_chain_stages" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_chain_stages_uq_eap_cs_link" ON "rateb_eap_chain_stages" ("chain_id", "stage_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_chain_stages_idx_eap_cs_chain" ON "rateb_eap_chain_stages" ("company_id", "chain_id", "sort_order");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_chain_stages_fk_eap_cs_stage" ON "rateb_eap_chain_stages" ("stage_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_chains" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "template_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_chains_uq_eap_chn_uuid" ON "rateb_eap_chains" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_chains_uq_eap_chn_code" ON "rateb_eap_chains" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_chains_idx_eap_chn_template" ON "rateb_eap_chains" ("company_id", "template_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_chains_fk_eap_chn_template" ON "rateb_eap_chains" ("template_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER NOT NULL,
  "body" TEXT NOT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_comments_uq_eap_cmt_uuid" ON "rateb_eap_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_comments_idx_eap_cmt_request" ON "rateb_eap_comments" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_comments_fk_eap_cmt_request" ON "rateb_eap_comments" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_delegations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER DEFAULT NULL,
  "from_user_id" INTEGER NOT NULL,
  "to_user_id" INTEGER NOT NULL,
  "starts_at" TEXT DEFAULT NULL,
  "ends_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_delegations_uq_eap_dlg_uuid" ON "rateb_eap_delegations" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_delegations_idx_eap_dlg_request" ON "rateb_eap_delegations" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_delegations_fk_eap_dlg_request" ON "rateb_eap_delegations" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_escalations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER NOT NULL,
  "stage_id" INTEGER DEFAULT NULL,
  "escalate_to_user_id" INTEGER DEFAULT NULL,
  "reason" TEXT DEFAULT NULL,
  "escalated_at" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_escalations_uq_eap_esc_uuid" ON "rateb_eap_escalations" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_escalations_idx_eap_esc_request" ON "rateb_eap_escalations" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_escalations_fk_eap_esc_request" ON "rateb_eap_escalations" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_notification_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER NOT NULL,
  "channel" TEXT NOT NULL DEFAULT 'in_app',
  "recipient_user_id" INTEGER DEFAULT NULL,
  "subject" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'queued',
  "notification_id" INTEGER DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_notification_meta_uq_eap_nm_uuid" ON "rateb_eap_notification_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_notification_meta_idx_eap_nm_request" ON "rateb_eap_notification_meta" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_notification_meta_fk_eap_nm_request" ON "rateb_eap_notification_meta" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_reminders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER NOT NULL,
  "remind_at" TEXT NOT NULL,
  "channel" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_reminders_uq_eap_rmd_uuid" ON "rateb_eap_reminders" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_reminders_idx_eap_rmd_request" ON "rateb_eap_reminders" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_reminders_fk_eap_rmd_request" ON "rateb_eap_reminders" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_requests" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "template_id" INTEGER DEFAULT NULL,
  "chain_id" INTEGER DEFAULT NULL,
  "current_stage_id" INTEGER DEFAULT NULL,
  "related_module" TEXT DEFAULT NULL,
  "related_type" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "priority" TEXT NOT NULL DEFAULT 'normal',
  "amount" REAL DEFAULT NULL,
  "currency_code" TEXT DEFAULT NULL,
  "submitted_at" TEXT DEFAULT NULL,
  "decided_at" TEXT DEFAULT NULL,
  "requester_user_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_requests_uq_eap_req_uuid" ON "rateb_eap_requests" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_requests_uq_eap_req_no" ON "rateb_eap_requests" ("company_id", "request_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_requests_idx_eap_req_company" ON "rateb_eap_requests" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_requests_idx_eap_req_workflow" ON "rateb_eap_requests" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_requests_idx_eap_req_related" ON "rateb_eap_requests" ("company_id", "related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_requests_fk_eap_req_template" ON "rateb_eap_requests" ("template_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_requests_fk_eap_req_chain" ON "rateb_eap_requests" ("chain_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_requests_fk_eap_req_stage" ON "rateb_eap_requests" ("current_stage_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_rules" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "template_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "rule_type" TEXT NOT NULL DEFAULT 'amount',
  "condition_json" TEXT DEFAULT NULL,
  "priority" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_rules_uq_eap_rule_uuid" ON "rateb_eap_rules" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_rules_uq_eap_rule_code" ON "rateb_eap_rules" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_rules_idx_eap_rule_template" ON "rateb_eap_rules" ("company_id", "template_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_rules_fk_eap_rule_template" ON "rateb_eap_rules" ("template_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_sla" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER NOT NULL,
  "stage_id" INTEGER DEFAULT NULL,
  "due_at" TEXT DEFAULT NULL,
  "breached_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'ok',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_sla_uq_eap_sla_uuid" ON "rateb_eap_sla" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_sla_idx_eap_sla_request" ON "rateb_eap_sla" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_sla_fk_eap_sla_request" ON "rateb_eap_sla" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_stages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "template_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "approver_role" TEXT DEFAULT NULL,
  "min_approvals" INTEGER NOT NULL DEFAULT 1,
  "sla_hours" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_stages_uq_eap_stg_uuid" ON "rateb_eap_stages" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_stages_uq_eap_stg_code" ON "rateb_eap_stages" ("company_id", "template_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_stages_idx_eap_stg_template" ON "rateb_eap_stages" ("company_id", "template_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_stages_fk_eap_stg_template" ON "rateb_eap_stages" ("template_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "request_id" INTEGER NOT NULL,
  "from_status" TEXT NOT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_eap_status_history_idx_eap_sh_request" ON "rateb_eap_status_history" ("company_id", "request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_status_history_fk_eap_sh_request" ON "rateb_eap_status_history" ("request_id");

CREATE TABLE IF NOT EXISTS "rateb_eap_templates" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "module_key" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_templates_uq_eap_tpl_uuid" ON "rateb_eap_templates" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_templates_uq_eap_tpl_code" ON "rateb_eap_templates" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_templates_idx_eap_tpl_company" ON "rateb_eap_templates" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_eap_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "related_type" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eap_timeline_uq_eap_tl_uuid" ON "rateb_eap_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eap_timeline_idx_eap_tl_request" ON "rateb_eap_timeline" ("company_id", "request_id", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_email_templates" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "subject" TEXT NOT NULL,
  "body_html" TEXT NOT NULL,
  "body_text" TEXT DEFAULT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_email_templates_slug" ON "rateb_email_templates" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_employees" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "employee_code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "national_id" TEXT DEFAULT NULL,
  "department_id" INTEGER DEFAULT NULL,
  "job_title_id" INTEGER DEFAULT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "job_title" TEXT DEFAULT NULL,
  "hire_date" TEXT DEFAULT NULL,
  "salary_base" REAL NOT NULL DEFAULT 0.00,
  "user_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_employees_uk_employee_code" ON "rateb_employees" ("company_id", "employee_code");
CREATE INDEX IF NOT EXISTS "idx_rateb_employees_idx_employee_company" ON "rateb_employees" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_employees_idx_employee_dept" ON "rateb_employees" ("department_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_employees_idx_employee_status" ON "rateb_employees" ("status");
CREATE INDEX IF NOT EXISTS "idx_rateb_employees_idx_employee_branch" ON "rateb_employees" ("branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_employees_idx_employee_job_title" ON "rateb_employees" ("job_title_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_approval_links" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "eap_request_id" INTEGER DEFAULT NULL,
  "legacy_instance_id" INTEGER DEFAULT NULL,
  "link_status" TEXT NOT NULL DEFAULT 'pending',
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_approval_links_uq_eproc_alink_uuid" ON "rateb_eproc_approval_links" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_approval_links_idx_eproc_alink_entity" ON "rateb_eproc_approval_links" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER NOT NULL,
  "role_label" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_assignments_uq_eproc_asg_uuid" ON "rateb_eproc_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_assignments_idx_eproc_asg_entity" ON "rateb_eproc_assignments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_audit" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "action" TEXT NOT NULL,
  "message" TEXT NOT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_audit_idx_eproc_audit_company" ON "rateb_eproc_audit" ("company_id", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_eproc_bid_comparisons" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "tender_id" INTEGER NOT NULL,
  "title" TEXT NOT NULL,
  "comparison_json" TEXT DEFAULT NULL,
  "recommended_bid_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_bid_comparisons_uq_eproc_cmp_uuid" ON "rateb_eproc_bid_comparisons" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_bid_comparisons_idx_eproc_cmp_tender" ON "rateb_eproc_bid_comparisons" ("company_id", "tender_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_bid_comparisons_fk_eproc_cmp_tender" ON "rateb_eproc_bid_comparisons" ("tender_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_calendar_events" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL DEFAULT 'general',
  "title" TEXT NOT NULL,
  "starts_at" TEXT NOT NULL,
  "ends_at" TEXT DEFAULT NULL,
  "related_type" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'scheduled',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_calendar_events_uq_eproc_cal_uuid" ON "rateb_eproc_calendar_events" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_calendar_events_idx_eproc_cal_company" ON "rateb_eproc_calendar_events" ("company_id", "starts_at");

CREATE TABLE IF NOT EXISTS "rateb_eproc_collaboration" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER DEFAULT NULL,
  "related_type" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "subject" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'open',
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_collaboration_uq_eproc_collab_uuid" ON "rateb_eproc_collaboration" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_collaboration_idx_eproc_collab_company" ON "rateb_eproc_collaboration" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_collaboration_idx_eproc_collab_profile" ON "rateb_eproc_collaboration" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_collaboration_fk_eproc_collab_profile" ON "rateb_eproc_collaboration" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "body" TEXT NOT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_comments_uq_eproc_cmt_uuid" ON "rateb_eproc_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_comments_idx_eproc_cmt_entity" ON "rateb_eproc_comments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_contracts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "legacy_contract_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "starts_at" TEXT DEFAULT NULL,
  "ends_at" TEXT DEFAULT NULL,
  "value_amount" REAL DEFAULT NULL,
  "currency_code" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_contracts_uq_eproc_ctr_uuid" ON "rateb_eproc_contracts" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_contracts_uq_eproc_ctr_code" ON "rateb_eproc_contracts" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_contracts_idx_eproc_ctr_workflow" ON "rateb_eproc_contracts" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_contracts_fk_eproc_ctr_profile" ON "rateb_eproc_contracts" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_document_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "document_id" INTEGER DEFAULT NULL,
  "file_name" TEXT DEFAULT NULL,
  "mime_type" TEXT DEFAULT NULL,
  "title" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_document_meta_uq_eproc_doc_uuid" ON "rateb_eproc_document_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_document_meta_idx_eproc_doc_entity" ON "rateb_eproc_document_meta" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_entity_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "tag_id" INTEGER NOT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_entity_tags_uq_eproc_etag_uuid" ON "rateb_eproc_entity_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_entity_tags_uq_eproc_etag_link" ON "rateb_eproc_entity_tags" ("company_id", "tag_id", "entity_type", "entity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_entity_tags_fk_eproc_etag_tag" ON "rateb_eproc_entity_tags" ("tag_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_portal_invites" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "email" TEXT NOT NULL,
  "invite_token" TEXT NOT NULL,
  "expires_at" TEXT DEFAULT NULL,
  "accepted_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_portal_invites_uq_eproc_portal_uuid" ON "rateb_eproc_portal_invites" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_portal_invites_uq_eproc_portal_token" ON "rateb_eproc_portal_invites" ("invite_token");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_portal_invites_idx_eproc_portal_profile" ON "rateb_eproc_portal_invites" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_portal_invites_fk_eproc_portal_profile" ON "rateb_eproc_portal_invites" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_rfq_templates" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "body_template" TEXT DEFAULT NULL,
  "default_days" INTEGER NOT NULL DEFAULT 14,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_rfq_templates_uq_eproc_rfqt_uuid" ON "rateb_eproc_rfq_templates" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_rfq_templates_uq_eproc_rfqt_code" ON "rateb_eproc_rfq_templates" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_rfq_templates_idx_eproc_rfqt_company" ON "rateb_eproc_rfq_templates" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_eproc_spend_snapshots" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "period_label" TEXT NOT NULL,
  "category_key" TEXT DEFAULT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "currency_code" TEXT DEFAULT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_spend_snapshots_uq_eproc_spend_uuid" ON "rateb_eproc_spend_snapshots" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_spend_snapshots_idx_eproc_spend_company" ON "rateb_eproc_spend_snapshots" ("company_id", "period_label");

CREATE TABLE IF NOT EXISTS "rateb_eproc_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_status_history_idx_eproc_sh_entity" ON "rateb_eproc_status_history" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_blacklist" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "reason" TEXT NOT NULL,
  "effective_from" TEXT NOT NULL,
  "effective_to" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_blacklist_uq_eproc_bl_uuid" ON "rateb_eproc_supplier_blacklist" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_blacklist_idx_eproc_bl_profile" ON "rateb_eproc_supplier_blacklist" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_blacklist_fk_eproc_bl_profile" ON "rateb_eproc_supplier_blacklist" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_categories_uq_eproc_cat_uuid" ON "rateb_eproc_supplier_categories" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_categories_uq_eproc_cat_code" ON "rateb_eproc_supplier_categories" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_categories_idx_eproc_cat_company" ON "rateb_eproc_supplier_categories" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_certifications" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "cert_type" TEXT NOT NULL,
  "cert_number" TEXT DEFAULT NULL,
  "issued_at" TEXT DEFAULT NULL,
  "expires_at" TEXT DEFAULT NULL,
  "issuer" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_certifications_uq_eproc_cert_uuid" ON "rateb_eproc_supplier_certifications" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_certifications_idx_eproc_cert_profile" ON "rateb_eproc_supplier_certifications" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_certifications_fk_eproc_cert_profile" ON "rateb_eproc_supplier_certifications" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_contacts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "title" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "is_primary" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_contacts_uq_eproc_contact_uuid" ON "rateb_eproc_supplier_contacts" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_contacts_idx_eproc_contact_profile" ON "rateb_eproc_supplier_contacts" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_contacts_fk_eproc_contact_profile" ON "rateb_eproc_supplier_contacts" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_performance" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "metric_key" TEXT NOT NULL,
  "metric_value" REAL NOT NULL DEFAULT 0.0000,
  "period_start" TEXT DEFAULT NULL,
  "period_end" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_performance_uq_eproc_perf_uuid" ON "rateb_eproc_supplier_performance" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_performance_idx_eproc_perf_profile" ON "rateb_eproc_supplier_performance" ("company_id", "profile_id", "metric_key");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_performance_fk_eproc_perf_profile" ON "rateb_eproc_supplier_performance" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_profiles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "legacy_supplier_id" INTEGER DEFAULT NULL,
  "category_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "legal_name" TEXT DEFAULT NULL,
  "tax_number" TEXT DEFAULT NULL,
  "country_code" TEXT DEFAULT NULL,
  "city" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "website" TEXT DEFAULT NULL,
  "risk_level" TEXT NOT NULL DEFAULT 'medium',
  "qualification_status" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_profiles_uq_eproc_sup_uuid" ON "rateb_eproc_supplier_profiles" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_profiles_uq_eproc_sup_code" ON "rateb_eproc_supplier_profiles" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_profiles_idx_eproc_sup_company" ON "rateb_eproc_supplier_profiles" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_profiles_idx_eproc_sup_workflow" ON "rateb_eproc_supplier_profiles" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_profiles_idx_eproc_sup_legacy" ON "rateb_eproc_supplier_profiles" ("company_id", "legacy_supplier_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_profiles_fk_eproc_sup_category" ON "rateb_eproc_supplier_profiles" ("category_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_qualification" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "checklist_json" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "decided_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_qualification_uq_eproc_qual_uuid" ON "rateb_eproc_supplier_qualification" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_qualification_uq_eproc_qual_code" ON "rateb_eproc_supplier_qualification" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_qualification_idx_eproc_qual_profile" ON "rateb_eproc_supplier_qualification" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_qualification_idx_eproc_qual_workflow" ON "rateb_eproc_supplier_qualification" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_qualification_fk_eproc_qual_profile" ON "rateb_eproc_supplier_qualification" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_risk" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "risk_code" TEXT NOT NULL,
  "risk_level" TEXT NOT NULL DEFAULT 'medium',
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "mitigation" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_risk_uq_eproc_risk_uuid" ON "rateb_eproc_supplier_risk" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_risk_idx_eproc_risk_profile" ON "rateb_eproc_supplier_risk" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_risk_fk_eproc_risk_profile" ON "rateb_eproc_supplier_risk" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_scorecards" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "period_label" TEXT NOT NULL,
  "quality_score" REAL NOT NULL DEFAULT 0.00,
  "delivery_score" REAL NOT NULL DEFAULT 0.00,
  "price_score" REAL NOT NULL DEFAULT 0.00,
  "service_score" REAL NOT NULL DEFAULT 0.00,
  "overall_score" REAL NOT NULL DEFAULT 0.00,
  "notes" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_scorecards_uq_eproc_sc_uuid" ON "rateb_eproc_supplier_scorecards" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_scorecards_idx_eproc_sc_profile" ON "rateb_eproc_supplier_scorecards" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_scorecards_fk_eproc_sc_profile" ON "rateb_eproc_supplier_scorecards" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_supplier_sla" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "profile_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "metric_key" TEXT NOT NULL DEFAULT 'on_time_delivery',
  "target_value" REAL NOT NULL DEFAULT 0.00,
  "unit" TEXT DEFAULT NULL,
  "period_days" INTEGER NOT NULL DEFAULT 30,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_sla_uq_eproc_sla_uuid" ON "rateb_eproc_supplier_sla" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_sla_uq_eproc_sla_code" ON "rateb_eproc_supplier_sla" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_sla_idx_eproc_sla_profile" ON "rateb_eproc_supplier_sla" ("company_id", "profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_supplier_sla_fk_eproc_sla_profile" ON "rateb_eproc_supplier_sla" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_tags_uq_eproc_tag_uuid" ON "rateb_eproc_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_tags_uq_eproc_tag_code" ON "rateb_eproc_tags" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_eproc_tender_bids" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "tender_id" INTEGER NOT NULL,
  "profile_id" INTEGER DEFAULT NULL,
  "bid_amount" REAL NOT NULL DEFAULT 0.00,
  "currency_code" TEXT DEFAULT NULL,
  "score" REAL DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'submitted',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_tender_bids_uq_eproc_bid_uuid" ON "rateb_eproc_tender_bids" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_tender_bids_idx_eproc_bid_tender" ON "rateb_eproc_tender_bids" ("company_id", "tender_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_tender_bids_fk_eproc_bid_tender" ON "rateb_eproc_tender_bids" ("tender_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_tender_bids_fk_eproc_bid_profile" ON "rateb_eproc_tender_bids" ("profile_id");

CREATE TABLE IF NOT EXISTS "rateb_eproc_tenders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "legacy_tender_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "opens_at" TEXT DEFAULT NULL,
  "closes_at" TEXT DEFAULT NULL,
  "budget_amount" REAL DEFAULT NULL,
  "currency_code" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_tenders_uq_eproc_tender_uuid" ON "rateb_eproc_tenders" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_eproc_tenders_uq_eproc_tender_code" ON "rateb_eproc_tenders" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_tenders_idx_eproc_tender_workflow" ON "rateb_eproc_tenders" ("company_id", "workflow_status");

CREATE TABLE IF NOT EXISTS "rateb_eproc_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "message" TEXT NOT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_timeline_idx_eproc_tl_company" ON "rateb_eproc_timeline" ("company_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_eproc_timeline_idx_eproc_tl_entity" ON "rateb_eproc_timeline" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_face_templates" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "user_id" INTEGER NOT NULL,
  "template_data" TEXT NOT NULL,
  "confidence_threshold" REAL NOT NULL DEFAULT 0.8500,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_face_templates_uq_face_user" ON "rateb_face_templates" ("user_id");

CREATE TABLE IF NOT EXISTS "rateb_fiscal_periods" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "locked" INTEGER NOT NULL DEFAULT 0,
  "closed_at" TEXT DEFAULT NULL,
  "closed_by" INTEGER DEFAULT NULL,
  "closing_entry_id" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_fiscal_periods_uq_fp_company_dates" ON "rateb_fiscal_periods" ("company_id", "start_date", "end_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_fiscal_periods_idx_fp_company" ON "rateb_fiscal_periods" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_departments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "code" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_hr_departments_idx_hr_dept_company" ON "rateb_hr_departments" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_documents" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "employee_id" INTEGER DEFAULT NULL,
  "title" TEXT NOT NULL,
  "doc_type" TEXT NOT NULL DEFAULT 'general',
  "issue_date" TEXT DEFAULT NULL,
  "expiry_date" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_hr_documents_idx_hr_doc_company" ON "rateb_hr_documents" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_documents_idx_hr_doc_employee" ON "rateb_hr_documents" ("employee_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_employee_requests" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "request_no" TEXT NOT NULL,
  "employee_id" INTEGER NOT NULL,
  "request_type" TEXT NOT NULL,
  "request_date" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "processed_by" INTEGER DEFAULT NULL,
  "processed_at" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hr_employee_requests_uk_hr_emp_req_no" ON "rateb_hr_employee_requests" ("company_id", "request_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_employee_requests_idx_hr_emp_req_company" ON "rateb_hr_employee_requests" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_employee_requests_idx_hr_emp_req_status" ON "rateb_hr_employee_requests" ("status");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_employee_requests_idx_hr_emp_req_ess_list" ON "rateb_hr_employee_requests" ("company_id", "employee_id", "status", "id");

CREATE TABLE IF NOT EXISTS "rateb_hr_fleet" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "plate_number" TEXT NOT NULL,
  "brand" TEXT DEFAULT NULL,
  "model" TEXT DEFAULT NULL,
  "model_year" INTEGER DEFAULT NULL,
  "assigned_employee_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hr_fleet_uk_hr_fleet_plate" ON "rateb_hr_fleet" ("company_id", "plate_number");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_fleet_idx_hr_fleet_company" ON "rateb_hr_fleet" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_holidays" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "holiday_date" TEXT NOT NULL,
  "is_recurring" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_hr_holidays_idx_hr_holiday_company" ON "rateb_hr_holidays" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_holidays_idx_hr_holiday_date" ON "rateb_hr_holidays" ("holiday_date");

CREATE TABLE IF NOT EXISTS "rateb_hr_job_titles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "code" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_hr_job_titles_idx_hr_job_title_company" ON "rateb_hr_job_titles" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_loan_types" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "max_amount" REAL DEFAULT NULL,
  "max_installments" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_hr_loan_types_idx_hr_loan_type_company" ON "rateb_hr_loan_types" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_loans" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "loan_code" TEXT NOT NULL,
  "employee_id" INTEGER NOT NULL,
  "loan_type_id" INTEGER NOT NULL,
  "principal" REAL NOT NULL DEFAULT 0.00,
  "installment_amount" REAL NOT NULL DEFAULT 0.00,
  "installments_count" INTEGER NOT NULL DEFAULT 1,
  "paid_installments" INTEGER NOT NULL DEFAULT 0,
  "start_date" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hr_loans_uk_hr_loan_code" ON "rateb_hr_loans" ("company_id", "loan_code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_loans_idx_hr_loan_company" ON "rateb_hr_loans" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_loans_idx_hr_loan_employee" ON "rateb_hr_loans" ("employee_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_payroll_components" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "component_type" TEXT NOT NULL DEFAULT 'allowance',
  "calc_type" TEXT NOT NULL DEFAULT 'fixed',
  "default_value" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hr_payroll_components_uk_hr_pay_comp_code" ON "rateb_hr_payroll_components" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_payroll_components_idx_hr_pay_comp_company" ON "rateb_hr_payroll_components" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_payroll_structures" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "employee_id" INTEGER NOT NULL,
  "component_id" INTEGER NOT NULL,
  "value" REAL NOT NULL DEFAULT 0.00,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hr_payroll_structures_uk_hr_pay_structure" ON "rateb_hr_payroll_structures" ("company_id", "employee_id", "component_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_payroll_structures_idx_hr_pay_struct_company" ON "rateb_hr_payroll_structures" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_hr_permission_requests" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "employee_id" INTEGER NOT NULL,
  "permission_date" TEXT NOT NULL,
  "time_from" TEXT NOT NULL,
  "time_to" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_hr_permission_requests_idx_hr_perm_company" ON "rateb_hr_permission_requests" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_permission_requests_idx_hr_perm_employee" ON "rateb_hr_permission_requests" ("employee_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hr_permission_requests_idx_hr_perm_status" ON "rateb_hr_permission_requests" ("status");

CREATE TABLE IF NOT EXISTS "rateb_hr_workplaces" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "address" TEXT DEFAULT NULL,
  "latitude" REAL DEFAULT NULL,
  "longitude" REAL DEFAULT NULL,
  "radius_meters" INTEGER NOT NULL DEFAULT 100,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_hr_workplaces_idx_hr_workplace_company" ON "rateb_hr_workplaces" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER NOT NULL,
  "role_label" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_assignments_uq_hrm_asg_uuid" ON "rateb_hrm_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_assignments_idx_hrm_asg_entity" ON "rateb_hrm_assignments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_certifications" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "code" TEXT DEFAULT NULL,
  "name" TEXT NOT NULL,
  "issuer" TEXT DEFAULT NULL,
  "issued_at" TEXT DEFAULT NULL,
  "expires_at" TEXT DEFAULT NULL,
  "credential_id" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_certifications_uq_hrm_cert_uuid" ON "rateb_hrm_certifications" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_certifications_idx_hrm_cert_emp" ON "rateb_hrm_certifications" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "body" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_comments_uq_hrm_cmt_uuid" ON "rateb_hrm_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_comments_idx_hrm_cmt_entity" ON "rateb_hrm_comments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_competencies" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "category" TEXT DEFAULT NULL,
  "level_score" REAL DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_competencies_uq_hrm_comp_uuid" ON "rateb_hrm_competencies" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_competencies_idx_hrm_comp_emp" ON "rateb_hrm_competencies" ("company_id", "employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_competencies_idx_hrm_comp_code" ON "rateb_hrm_competencies" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_hrm_departments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "manager_profile_id" INTEGER DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_departments_uq_hrm_dept_uuid" ON "rateb_hrm_departments" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_departments_uq_hrm_dept_code" ON "rateb_hrm_departments" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_departments_idx_hrm_dept_company" ON "rateb_hrm_departments" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_hrm_dependents" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "full_name" TEXT NOT NULL,
  "relationship" TEXT NOT NULL DEFAULT 'child',
  "birth_date" TEXT DEFAULT NULL,
  "national_id" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_dependents_uq_hrm_dep_uuid" ON "rateb_hrm_dependents" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_dependents_idx_hrm_dep_emp" ON "rateb_hrm_dependents" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_disciplinary_actions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "action_type" TEXT NOT NULL DEFAULT 'warning',
  "title" TEXT NOT NULL,
  "action_date" TEXT DEFAULT NULL,
  "severity_date" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_disciplinary_actions_uq_hrm_disc_uuid" ON "rateb_hrm_disciplinary_actions" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_disciplinary_actions_uq_hrm_disc_code" ON "rateb_hrm_disciplinary_actions" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_disciplinary_actions_idx_hrm_disc_emp" ON "rateb_hrm_disciplinary_actions" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_emergency_contacts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "full_name" TEXT NOT NULL,
  "relationship" TEXT DEFAULT NULL,
  "phone" TEXT NOT NULL,
  "email" TEXT DEFAULT NULL,
  "is_primary" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_emergency_contacts_uq_hrm_emc_uuid" ON "rateb_hrm_emergency_contacts" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_emergency_contacts_idx_hrm_emc_emp" ON "rateb_hrm_emergency_contacts" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_employee_contacts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "contact_type" TEXT NOT NULL DEFAULT 'personal',
  "label" TEXT DEFAULT NULL,
  "value" TEXT NOT NULL,
  "is_primary" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_contacts_uq_hrm_ec_uuid" ON "rateb_hrm_employee_contacts" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_contacts_idx_hrm_ec_emp" ON "rateb_hrm_employee_contacts" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_employee_documents_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "doc_type" TEXT NOT NULL DEFAULT 'general',
  "title" TEXT NOT NULL,
  "file_name" TEXT DEFAULT NULL,
  "mime_type" TEXT DEFAULT NULL,
  "file_size" INTEGER DEFAULT NULL,
  "storage_key" TEXT DEFAULT NULL,
  "issued_at" TEXT DEFAULT NULL,
  "expires_at" TEXT DEFAULT NULL,
  "legacy_document_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_documents_meta_uq_hrm_doc_uuid" ON "rateb_hrm_employee_documents_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_documents_meta_idx_hrm_doc_emp" ON "rateb_hrm_employee_documents_meta" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_employee_profiles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "first_name" TEXT NOT NULL,
  "last_name" TEXT NOT NULL,
  "first_name_ar" TEXT DEFAULT NULL,
  "last_name_ar" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "department_id" INTEGER DEFAULT NULL,
  "position_id" INTEGER DEFAULT NULL,
  "grade_id" INTEGER DEFAULT NULL,
  "location_id" INTEGER DEFAULT NULL,
  "org_unit_id" INTEGER DEFAULT NULL,
  "manager_profile_id" INTEGER DEFAULT NULL,
  "hire_date" TEXT DEFAULT NULL,
  "termination_date" TEXT DEFAULT NULL,
  "employment_type" TEXT DEFAULT 'full_time',
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_profiles_uq_hrm_emp_uuid" ON "rateb_hrm_employee_profiles" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_profiles_uq_hrm_emp_code" ON "rateb_hrm_employee_profiles" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_profiles_idx_hrm_emp_legacy" ON "rateb_hrm_employee_profiles" ("company_id", "legacy_employee_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_profiles_idx_hrm_emp_workflow" ON "rateb_hrm_employee_profiles" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_employee_profiles_idx_hrm_emp_dept" ON "rateb_hrm_employee_profiles" ("company_id", "department_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_entity_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "tag_id" INTEGER NOT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_entity_tags_uq_hrm_etag_uuid" ON "rateb_hrm_entity_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_entity_tags_uq_hrm_etag_link" ON "rateb_hrm_entity_tags" ("company_id", "tag_id", "entity_type", "entity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_entity_tags_fk_hrm_etag_tag" ON "rateb_hrm_entity_tags" ("tag_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_goals" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "review_id" INTEGER DEFAULT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "target_date" TEXT DEFAULT NULL,
  "progress_percent" REAL NOT NULL DEFAULT 0.00,
  "goal_status" TEXT NOT NULL DEFAULT 'open',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_goals_uq_hrm_goal_uuid" ON "rateb_hrm_goals" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_goals_idx_hrm_goal_emp" ON "rateb_hrm_goals" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_grades" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "level_rank" INTEGER NOT NULL DEFAULT 1,
  "min_salary" REAL DEFAULT NULL,
  "max_salary" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_grades_uq_hrm_grade_uuid" ON "rateb_hrm_grades" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_grades_uq_hrm_grade_code" ON "rateb_hrm_grades" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_grades_idx_hrm_grade_company" ON "rateb_hrm_grades" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_hrm_languages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "language_code" TEXT NOT NULL,
  "language_name" TEXT NOT NULL,
  "proficiency" TEXT NOT NULL DEFAULT 'intermediate',
  "is_native" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_languages_uq_hrm_lang_uuid" ON "rateb_hrm_languages" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_languages_idx_hrm_lang_emp" ON "rateb_hrm_languages" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_licenses" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "code" TEXT DEFAULT NULL,
  "name" TEXT NOT NULL,
  "license_number" TEXT DEFAULT NULL,
  "issued_at" TEXT DEFAULT NULL,
  "expires_at" TEXT DEFAULT NULL,
  "authority" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_licenses_uq_hrm_lic_uuid" ON "rateb_hrm_licenses" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_licenses_idx_hrm_lic_emp" ON "rateb_hrm_licenses" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_locations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "address" TEXT DEFAULT NULL,
  "city" TEXT DEFAULT NULL,
  "country_code" TEXT DEFAULT NULL,
  "legacy_workplace_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_locations_uq_hrm_loc_uuid" ON "rateb_hrm_locations" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_locations_uq_hrm_loc_code" ON "rateb_hrm_locations" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_locations_idx_hrm_loc_company" ON "rateb_hrm_locations" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_hrm_notes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "title" TEXT DEFAULT NULL,
  "body" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_notes_uq_hrm_note_uuid" ON "rateb_hrm_notes" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_notes_idx_hrm_note_entity" ON "rateb_hrm_notes" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_org_units" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "department_id" INTEGER DEFAULT NULL,
  "unit_type" TEXT NOT NULL DEFAULT 'unit',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_org_units_uq_hrm_org_uuid" ON "rateb_hrm_org_units" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_org_units_uq_hrm_org_code" ON "rateb_hrm_org_units" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_org_units_idx_hrm_org_parent" ON "rateb_hrm_org_units" ("company_id", "parent_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_performance_reviews" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "reviewer_profile_id" INTEGER DEFAULT NULL,
  "period_start" TEXT DEFAULT NULL,
  "period_end" TEXT DEFAULT NULL,
  "overall_score" REAL DEFAULT NULL,
  "summary" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_performance_reviews_uq_hrm_perf_uuid" ON "rateb_hrm_performance_reviews" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_performance_reviews_uq_hrm_perf_code" ON "rateb_hrm_performance_reviews" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_performance_reviews_idx_hrm_perf_emp" ON "rateb_hrm_performance_reviews" ("company_id", "employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_performance_reviews_idx_hrm_perf_workflow" ON "rateb_hrm_performance_reviews" ("company_id", "workflow_status");

CREATE TABLE IF NOT EXISTS "rateb_hrm_positions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "department_id" INTEGER DEFAULT NULL,
  "grade_id" INTEGER DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "legacy_job_title_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_positions_uq_hrm_pos_uuid" ON "rateb_hrm_positions" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_positions_uq_hrm_pos_code" ON "rateb_hrm_positions" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_positions_idx_hrm_pos_dept" ON "rateb_hrm_positions" ("company_id", "department_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_promotions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "from_position_id" INTEGER DEFAULT NULL,
  "to_position_id" INTEGER DEFAULT NULL,
  "from_grade_id" INTEGER DEFAULT NULL,
  "to_grade_id" INTEGER DEFAULT NULL,
  "effective_date" TEXT DEFAULT NULL,
  "promotion_status" TEXT NOT NULL DEFAULT 'draft',
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_promotions_uq_hrm_promo_uuid" ON "rateb_hrm_promotions" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_promotions_uq_hrm_promo_code" ON "rateb_hrm_promotions" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_promotions_idx_hrm_promo_emp" ON "rateb_hrm_promotions" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_rewards" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "reward_type" TEXT NOT NULL DEFAULT 'recognition',
  "title" TEXT NOT NULL,
  "reward_date" TEXT DEFAULT NULL,
  "amount" REAL DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_rewards_uq_hrm_rew_uuid" ON "rateb_hrm_rewards" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_rewards_uq_hrm_rew_code" ON "rateb_hrm_rewards" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_rewards_idx_hrm_rew_emp" ON "rateb_hrm_rewards" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_skills" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "category" TEXT DEFAULT NULL,
  "proficiency" TEXT DEFAULT 'intermediate',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_skills_uq_hrm_skill_uuid" ON "rateb_hrm_skills" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_skills_idx_hrm_skill_emp" ON "rateb_hrm_skills" ("company_id", "employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_skills_idx_hrm_skill_code" ON "rateb_hrm_skills" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_hrm_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_status_history_uq_hrm_sh_uuid" ON "rateb_hrm_status_history" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_status_history_idx_hrm_sh_entity" ON "rateb_hrm_status_history" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_tags_uq_hrm_tag_uuid" ON "rateb_hrm_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_tags_uq_hrm_tag_code" ON "rateb_hrm_tags" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_hrm_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT DEFAULT NULL,
  "body" TEXT DEFAULT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_timeline_uq_hrm_tl_uuid" ON "rateb_hrm_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_timeline_idx_hrm_tl_entity" ON "rateb_hrm_timeline" ("company_id", "entity_type", "entity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_timeline_idx_hrm_tl_created" ON "rateb_hrm_timeline" ("company_id", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_hrm_training" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "title_ar" TEXT DEFAULT NULL,
  "provider" TEXT DEFAULT NULL,
  "location_id" INTEGER DEFAULT NULL,
  "planned_start" TEXT DEFAULT NULL,
  "planned_end" TEXT DEFAULT NULL,
  "hours" REAL NOT NULL DEFAULT 0.00,
  "capacity" INTEGER DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_training_uq_hrm_trn_uuid" ON "rateb_hrm_training" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_training_uq_hrm_trn_code" ON "rateb_hrm_training" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_training_idx_hrm_trn_workflow" ON "rateb_hrm_training" ("company_id", "workflow_status");

CREATE TABLE IF NOT EXISTS "rateb_hrm_training_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "training_id" INTEGER NOT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "result_status" TEXT NOT NULL DEFAULT 'enrolled',
  "score" REAL DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "certificate_ref" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_training_history_uq_hrm_trnh_uuid" ON "rateb_hrm_training_history" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_training_history_uq_hrm_trnh_link" ON "rateb_hrm_training_history" ("company_id", "training_id", "employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_training_history_idx_hrm_trnh_emp" ON "rateb_hrm_training_history" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_hrm_transfers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_profile_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "from_department_id" INTEGER DEFAULT NULL,
  "to_department_id" INTEGER DEFAULT NULL,
  "from_position_id" INTEGER DEFAULT NULL,
  "to_position_id" INTEGER DEFAULT NULL,
  "from_location_id" INTEGER DEFAULT NULL,
  "to_location_id" INTEGER DEFAULT NULL,
  "effective_date" TEXT DEFAULT NULL,
  "transfer_status" TEXT NOT NULL DEFAULT 'draft',
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_transfers_uq_hrm_xfer_uuid" ON "rateb_hrm_transfers" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_hrm_transfers_uq_hrm_xfer_code" ON "rateb_hrm_transfers" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_hrm_transfers_idx_hrm_xfer_emp" ON "rateb_hrm_transfers" ("company_id", "employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_inventory" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "item_code" TEXT DEFAULT NULL,
  "warehouse_id" INTEGER DEFAULT NULL,
  "item_name" TEXT NOT NULL,
  "sku" TEXT DEFAULT NULL,
  "barcode" TEXT DEFAULT NULL,
  "qr_code" TEXT DEFAULT NULL,
  "document_path" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "category" TEXT DEFAULT NULL,
  "category_id" INTEGER DEFAULT NULL,
  "quantity" REAL NOT NULL DEFAULT 0.000,
  "unit" TEXT NOT NULL DEFAULT 'unit',
  "unit_cost" REAL NOT NULL DEFAULT 0.00,
  "sell_price" REAL DEFAULT NULL,
  "reorder_level" REAL NOT NULL DEFAULT 0.000,
  "production_date" TEXT DEFAULT NULL,
  "min_stock" REAL NOT NULL DEFAULT 0.000,
  "max_stock" REAL DEFAULT NULL,
  "expiry_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_inventory_uq_inv_company_item_code" ON "rateb_inventory" ("company_id", "item_code");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_idx_inv_company" ON "rateb_inventory" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_fk_inv_warehouse" ON "rateb_inventory" ("warehouse_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_idx_inv_barcode" ON "rateb_inventory" ("barcode");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_idx_inventory_expiry" ON "rateb_inventory" ("company_id", "expiry_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_idx_inv_category" ON "rateb_inventory" ("category_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_idx_inv_branch" ON "rateb_inventory" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_inventory_audit_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "audit_id" INTEGER NOT NULL,
  "inventory_id" INTEGER NOT NULL,
  "system_qty" REAL NOT NULL DEFAULT 0.000,
  "counted_qty" REAL NOT NULL DEFAULT 0.000,
  "variance" REAL NOT NULL DEFAULT 0.000,
  "notes" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_audit_lines_fk_audit_line_audit" ON "rateb_inventory_audit_lines" ("audit_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_audit_lines_fk_audit_line_inv" ON "rateb_inventory_audit_lines" ("inventory_id");

CREATE TABLE IF NOT EXISTS "rateb_inventory_audits" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "warehouse_id" INTEGER DEFAULT NULL,
  "audit_no" TEXT NOT NULL,
  "audit_date" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "manager_approval" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_inventory_audits_uq_audit_company_no" ON "rateb_inventory_audits" ("company_id", "audit_no");

CREATE TABLE IF NOT EXISTS "rateb_inventory_batches" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "inventory_id" INTEGER NOT NULL,
  "batch_no" TEXT NOT NULL,
  "quantity" REAL NOT NULL DEFAULT 0.000,
  "unit_cost" REAL DEFAULT NULL,
  "production_date" TEXT DEFAULT NULL,
  "expiry_date" TEXT DEFAULT NULL,
  "warehouse_id" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_batches_idx_batch_company" ON "rateb_inventory_batches" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_batches_idx_batch_inv" ON "rateb_inventory_batches" ("inventory_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_batches_idx_ib_branch" ON "rateb_inventory_batches" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_inventory_serials" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "inventory_id" INTEGER NOT NULL,
  "serial_no" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'available',
  "warehouse_id" INTEGER DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_inventory_serials_uq_inv_serial" ON "rateb_inventory_serials" ("company_id", "serial_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_serials_idx_inv_serial_item" ON "rateb_inventory_serials" ("inventory_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_inventory_serials_idx_inv_serial_branch" ON "rateb_inventory_serials" ("company_id", "branch_id");

CREATE TABLE IF NOT EXISTS "rateb_invoice_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "invoice_id" INTEGER NOT NULL,
  "line_no" INTEGER NOT NULL DEFAULT 1,
  "item_name" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "quantity" REAL NOT NULL DEFAULT 1.000,
  "unit" TEXT NOT NULL DEFAULT 'unit',
  "unit_price" REAL NOT NULL DEFAULT 0.00,
  "account_id" INTEGER DEFAULT NULL,
  "tax_rate" REAL NOT NULL DEFAULT 15.00,
  "excluding_tax" INTEGER NOT NULL DEFAULT 1,
  "line_subtotal" REAL NOT NULL DEFAULT 0.00,
  "tax_amount" REAL NOT NULL DEFAULT 0.00,
  "line_total" REAL NOT NULL DEFAULT 0.00,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_invoice_lines_idx_invoice_lines_invoice" ON "rateb_invoice_lines" ("invoice_id");

CREATE TABLE IF NOT EXISTS "rateb_invoices" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "subscription_id" INTEGER DEFAULT NULL,
  "invoice_no" TEXT NOT NULL,
  "barcode" TEXT DEFAULT NULL,
  "qr_code" TEXT DEFAULT NULL,
  "document_path" TEXT DEFAULT NULL,
  "invoice_type" TEXT NOT NULL DEFAULT 'tax',
  "buyer_legal_name" TEXT DEFAULT NULL,
  "buyer_vat_number" TEXT DEFAULT NULL,
  "buyer_cr_number" TEXT DEFAULT NULL,
  "buyer_address" TEXT DEFAULT NULL,
  "po_number" TEXT DEFAULT NULL,
  "amount" REAL NOT NULL,
  "tax_amount" REAL NOT NULL DEFAULT 0.00,
  "total_amount" REAL NOT NULL,
  "currency" TEXT NOT NULL DEFAULT 'SAR',
  "discount_amount" REAL NOT NULL DEFAULT 0.00,
  "discount_type" TEXT NOT NULL DEFAULT 'value',
  "tax_rate" REAL NOT NULL DEFAULT 15.00,
  "payment_terms_days" INTEGER NOT NULL DEFAULT 30,
  "payment_method" TEXT DEFAULT NULL,
  "supplier_account_no" TEXT DEFAULT NULL,
  "supplier_bank_account_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "payment_status" TEXT NOT NULL DEFAULT 'unpaid',
  "sent_at" TEXT DEFAULT NULL,
  "due_date" TEXT DEFAULT NULL,
  "issued_at" TEXT NOT NULL,
  "zatca_uuid" TEXT DEFAULT NULL,
  "zatca_qr" TEXT DEFAULT NULL,
  "zatca_status" TEXT NOT NULL DEFAULT 'not_applicable',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_invoices_invoice_no" ON "rateb_invoices" ("invoice_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_invoices_idx_invoices_company" ON "rateb_invoices" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_invoices_idx_inv_doc_barcode" ON "rateb_invoices" ("barcode");

CREATE TABLE IF NOT EXISTS "rateb_journal_entries" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT DEFAULT NULL,
  "company_id" INTEGER DEFAULT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entry_no" TEXT NOT NULL,
  "entry_date" TEXT NOT NULL,
  "description" TEXT NOT NULL,
  "description_ar" TEXT DEFAULT NULL,
  "source_type" TEXT NOT NULL DEFAULT 'manual',
  "source_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "lifecycle_status" TEXT NOT NULL DEFAULT 'draft',
  "currency_code" TEXT DEFAULT NULL,
  "exchange_rate" REAL DEFAULT NULL,
  "profit_center_id" INTEGER DEFAULT NULL,
  "tax_code_id" INTEGER DEFAULT NULL,
  "is_opening_balance" INTEGER NOT NULL DEFAULT 0,
  "locked_at" TEXT DEFAULT NULL,
  "locked_by" INTEGER DEFAULT NULL,
  "reversed_entry_id" INTEGER DEFAULT NULL,
  "archived_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL,
  "reject_reason" TEXT DEFAULT NULL,
  "rejected_at" TEXT DEFAULT NULL,
  "rejected_by" INTEGER DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "posted_at" TEXT DEFAULT NULL,
  "submitted_for_approval_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_journal_entries_uq_journal_company_no" ON "rateb_journal_entries" ("company_id", "entry_no");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_journal_entries_uq_je_uuid" ON "rateb_journal_entries" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_journal_entries_idx_journal_company" ON "rateb_journal_entries" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_journal_entries_idx_journal_source" ON "rateb_journal_entries" ("source_type", "source_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_journal_entries_idx_je_submitted" ON "rateb_journal_entries" ("submitted_for_approval_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_journal_entries_idx_je_branch" ON "rateb_journal_entries" ("branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_journal_entries_idx_je_lifecycle" ON "rateb_journal_entries" ("company_id", "lifecycle_status");

CREATE TABLE IF NOT EXISTS "rateb_journal_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "journal_entry_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "account_id" INTEGER NOT NULL,
  "cost_center_id" INTEGER DEFAULT NULL,
  "debit" REAL NOT NULL DEFAULT 0.00,
  "credit" REAL NOT NULL DEFAULT 0.00,
  "memo" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_journal_lines_idx_jl_entry" ON "rateb_journal_lines" ("journal_entry_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_journal_lines_idx_jl_account" ON "rateb_journal_lines" ("account_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_journal_lines_idx_jl_cost_center" ON "rateb_journal_lines" ("cost_center_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_journal_lines_idx_jl_branch" ON "rateb_journal_lines" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_leave_balances" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "employee_id" INTEGER NOT NULL,
  "leave_type_id" INTEGER NOT NULL,
  "balance_year" INTEGER NOT NULL,
  "entitled_days" REAL NOT NULL DEFAULT 0.0,
  "used_days" REAL NOT NULL DEFAULT 0.0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_leave_balances_uk_leave_balance" ON "rateb_leave_balances" ("company_id", "employee_id", "leave_type_id", "balance_year");
CREATE INDEX IF NOT EXISTS "idx_rateb_leave_balances_idx_leave_bal_company" ON "rateb_leave_balances" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_leave_balances_idx_leave_bal_employee" ON "rateb_leave_balances" ("employee_id");

CREATE TABLE IF NOT EXISTS "rateb_leave_requests" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "employee_id" INTEGER NOT NULL,
  "leave_type_id" INTEGER NOT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT NOT NULL,
  "days" REAL NOT NULL DEFAULT 1.0,
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_leave_requests_idx_leave_req_company" ON "rateb_leave_requests" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_leave_requests_idx_leave_req_employee" ON "rateb_leave_requests" ("employee_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_leave_requests_idx_leave_req_status" ON "rateb_leave_requests" ("status");
CREATE INDEX IF NOT EXISTS "idx_rateb_leave_requests_idx_lr_branch" ON "rateb_leave_requests" ("branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_leave_requests_idx_leave_req_ess_list" ON "rateb_leave_requests" ("company_id", "employee_id", "status", "id");

CREATE TABLE IF NOT EXISTS "rateb_leave_types" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "code" TEXT DEFAULT NULL,
  "name" TEXT NOT NULL,
  "paid" INTEGER NOT NULL DEFAULT 1,
  "days_per_year" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_leave_types_idx_leave_type_company" ON "rateb_leave_types" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_leave_types_idx_leave_type_code" ON "rateb_leave_types" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_lims_results" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "sample_id" INTEGER NOT NULL,
  "test_name" TEXT NOT NULL,
  "result_value" TEXT DEFAULT NULL,
  "unit" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_lims_results_fk_lims_result_sample" ON "rateb_lims_results" ("sample_id");

CREATE TABLE IF NOT EXISTS "rateb_lims_samples" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "sample_no" TEXT NOT NULL,
  "patient_ref" TEXT DEFAULT NULL,
  "sample_type" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'received',
  "received_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_lims_samples_uq_lims_sample" ON "rateb_lims_samples" ("company_id", "sample_no");

CREATE TABLE IF NOT EXISTS "rateb_login_activity" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "user_id" INTEGER DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "ip_address" TEXT DEFAULT NULL,
  "user_agent" TEXT DEFAULT NULL,
  "success" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_login_activity_idx_login_user" ON "rateb_login_activity" ("user_id");

CREATE TABLE IF NOT EXISTS "rateb_login_barcode_pairs" (
  "token" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "context_json" TEXT DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "expires_at" INTEGER NOT NULL,
  "created_at" INTEGER NOT NULL,
  PRIMARY KEY ("token")
);

CREATE INDEX IF NOT EXISTS "idx_rateb_login_barcode_pairs_idx_rateb_barcode_pairs_expires" ON "rateb_login_barcode_pairs" ("expires_at");

CREATE TABLE IF NOT EXISTS "rateb_medical_devices" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "asset_id" INTEGER DEFAULT NULL,
  "device_name" TEXT NOT NULL,
  "category_id" INTEGER DEFAULT NULL,
  "manufacturer" TEXT DEFAULT NULL,
  "model_no" TEXT DEFAULT NULL,
  "serial_no" TEXT DEFAULT NULL,
  "calibration_due" TEXT DEFAULT NULL,
  "maintenance_due" TEXT DEFAULT NULL,
  "warranty_expiry" TEXT DEFAULT NULL,
  "regulatory_status" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'operational',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_medical_devices_fk_md_company" ON "rateb_medical_devices" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_medical_devices_fk_md_asset" ON "rateb_medical_devices" ("asset_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER NOT NULL,
  "role_label" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_assignments_uq_mfg_asg_uuid" ON "rateb_mfg_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_assignments_idx_mfg_asg_entity" ON "rateb_mfg_assignments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_attachments_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "file_name" TEXT NOT NULL,
  "mime_type" TEXT DEFAULT NULL,
  "file_size" INTEGER DEFAULT NULL,
  "storage_key" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_attachments_meta_uq_mfg_att_uuid" ON "rateb_mfg_attachments_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_attachments_meta_idx_mfg_att_entity" ON "rateb_mfg_attachments_meta" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_bom_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "bom_version_id" INTEGER NOT NULL,
  "line_no" INTEGER NOT NULL DEFAULT 1,
  "component_product_id" INTEGER DEFAULT NULL,
  "component_variant_id" INTEGER DEFAULT NULL,
  "inventory_item_id" INTEGER DEFAULT NULL,
  "component_code" TEXT DEFAULT NULL,
  "component_name" TEXT NOT NULL,
  "qty_per" REAL NOT NULL DEFAULT 1.000000,
  "uom" TEXT DEFAULT 'EA',
  "scrap_percent" REAL NOT NULL DEFAULT 0.0000,
  "is_optional" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_bom_lines_uq_mfg_boml_uuid" ON "rateb_mfg_bom_lines" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_bom_lines_idx_mfg_boml_ver" ON "rateb_mfg_bom_lines" ("company_id", "bom_version_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_bom_lines_fk_mfg_boml_ver" ON "rateb_mfg_bom_lines" ("bom_version_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_bom_versions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "bom_id" INTEGER NOT NULL,
  "version_label" TEXT NOT NULL,
  "is_current" INTEGER NOT NULL DEFAULT 0,
  "effective_from" TEXT DEFAULT NULL,
  "effective_to" TEXT DEFAULT NULL,
  "scrap_percent" REAL NOT NULL DEFAULT 0.0000,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_bom_versions_uq_mfg_bomv_uuid" ON "rateb_mfg_bom_versions" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_bom_versions_uq_mfg_bomv_label" ON "rateb_mfg_bom_versions" ("company_id", "bom_id", "version_label");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_bom_versions_idx_mfg_bomv_bom" ON "rateb_mfg_bom_versions" ("company_id", "bom_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_bom_versions_fk_mfg_bomv_bom" ON "rateb_mfg_bom_versions" ("bom_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_boms" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "product_id" INTEGER NOT NULL,
  "variant_id" INTEGER DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_boms_uq_mfg_bom_uuid" ON "rateb_mfg_boms" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_boms_uq_mfg_bom_code" ON "rateb_mfg_boms" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_boms_idx_mfg_bom_product" ON "rateb_mfg_boms" ("company_id", "product_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_boms_idx_mfg_bom_workflow" ON "rateb_mfg_boms" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_boms_fk_mfg_bom_product" ON "rateb_mfg_boms" ("product_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_boms_fk_mfg_bom_variant" ON "rateb_mfg_boms" ("variant_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_capacity_plans" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "work_center_id" INTEGER NOT NULL,
  "plan_date" TEXT NOT NULL,
  "available_hours" REAL NOT NULL DEFAULT 8.00,
  "booked_hours" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'open',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_capacity_plans_uq_mfg_cap_uuid" ON "rateb_mfg_capacity_plans" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_capacity_plans_uq_mfg_cap_day" ON "rateb_mfg_capacity_plans" ("company_id", "work_center_id", "plan_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_capacity_plans_idx_mfg_cap_date" ON "rateb_mfg_capacity_plans" ("company_id", "plan_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_capacity_plans_fk_mfg_cap_wc" ON "rateb_mfg_capacity_plans" ("work_center_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "body" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_comments_uq_mfg_cmt_uuid" ON "rateb_mfg_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_comments_idx_mfg_cmt_entity" ON "rateb_mfg_comments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_entity_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "tag_id" INTEGER NOT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_entity_tags_uq_mfg_etag_uuid" ON "rateb_mfg_entity_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_entity_tags_uq_mfg_etag_link" ON "rateb_mfg_entity_tags" ("company_id", "tag_id", "entity_type", "entity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_entity_tags_fk_mfg_etag_tag" ON "rateb_mfg_entity_tags" ("tag_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_finished_goods_receipts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "production_order_id" INTEGER NOT NULL,
  "product_id" INTEGER NOT NULL,
  "variant_id" INTEGER DEFAULT NULL,
  "inventory_item_id" INTEGER DEFAULT NULL,
  "qty_received" REAL NOT NULL DEFAULT 0.000000,
  "uom" TEXT DEFAULT 'EA',
  "warehouse_id" INTEGER DEFAULT NULL,
  "batch_code" TEXT DEFAULT NULL,
  "serial_code" TEXT DEFAULT NULL,
  "received_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'posted',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_finished_goods_receipts_uq_mfg_fgr_uuid" ON "rateb_mfg_finished_goods_receipts" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_finished_goods_receipts_idx_mfg_fgr_po" ON "rateb_mfg_finished_goods_receipts" ("company_id", "production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_finished_goods_receipts_fk_mfg_fgr_po" ON "rateb_mfg_finished_goods_receipts" ("production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_finished_goods_receipts_fk_mfg_fgr_product" ON "rateb_mfg_finished_goods_receipts" ("product_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_finished_goods_receipts_fk_mfg_fgr_variant" ON "rateb_mfg_finished_goods_receipts" ("variant_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_machines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "work_center_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "eam_asset_id" INTEGER DEFAULT NULL,
  "capacity_hours_day" REAL NOT NULL DEFAULT 8.00,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_machines_uq_mfg_mach_uuid" ON "rateb_mfg_machines" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_machines_uq_mfg_mach_code" ON "rateb_mfg_machines" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_machines_idx_mfg_mach_wc" ON "rateb_mfg_machines" ("company_id", "work_center_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_machines_idx_mfg_mach_asset" ON "rateb_mfg_machines" ("company_id", "eam_asset_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_machines_fk_mfg_mach_wc" ON "rateb_mfg_machines" ("work_center_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_material_consumptions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "production_order_id" INTEGER NOT NULL,
  "work_order_id" INTEGER DEFAULT NULL,
  "reservation_id" INTEGER DEFAULT NULL,
  "inventory_item_id" INTEGER DEFAULT NULL,
  "component_name" TEXT NOT NULL,
  "qty_consumed" REAL NOT NULL DEFAULT 0.000000,
  "uom" TEXT DEFAULT 'EA',
  "warehouse_id" INTEGER DEFAULT NULL,
  "batch_code" TEXT DEFAULT NULL,
  "serial_code" TEXT DEFAULT NULL,
  "consumed_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'posted',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_material_consumptions_uq_mfg_cons_uuid" ON "rateb_mfg_material_consumptions" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_material_consumptions_idx_mfg_cons_po" ON "rateb_mfg_material_consumptions" ("company_id", "production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_material_consumptions_fk_mfg_cons_po" ON "rateb_mfg_material_consumptions" ("production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_material_consumptions_fk_mfg_cons_wo" ON "rateb_mfg_material_consumptions" ("work_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_material_consumptions_fk_mfg_cons_res" ON "rateb_mfg_material_consumptions" ("reservation_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_material_reservations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "production_order_id" INTEGER NOT NULL,
  "bom_line_id" INTEGER DEFAULT NULL,
  "inventory_item_id" INTEGER DEFAULT NULL,
  "component_name" TEXT NOT NULL,
  "qty_reserved" REAL NOT NULL DEFAULT 0.000000,
  "warehouse_id" INTEGER DEFAULT NULL,
  "reservation_status" TEXT NOT NULL DEFAULT 'reserved',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_material_reservations_uq_mfg_res_uuid" ON "rateb_mfg_material_reservations" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_material_reservations_idx_mfg_res_po" ON "rateb_mfg_material_reservations" ("company_id", "production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_material_reservations_idx_mfg_res_item" ON "rateb_mfg_material_reservations" ("company_id", "inventory_item_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_material_reservations_fk_mfg_res_po" ON "rateb_mfg_material_reservations" ("production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_material_reservations_fk_mfg_res_line" ON "rateb_mfg_material_reservations" ("bom_line_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_product_variants" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "product_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "sku" TEXT DEFAULT NULL,
  "inventory_item_id" INTEGER DEFAULT NULL,
  "attributes_json" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_product_variants_uq_mfg_var_uuid" ON "rateb_mfg_product_variants" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_product_variants_uq_mfg_var_code" ON "rateb_mfg_product_variants" ("company_id", "product_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_product_variants_idx_mfg_var_product" ON "rateb_mfg_product_variants" ("company_id", "product_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_product_variants_fk_mfg_var_product" ON "rateb_mfg_product_variants" ("product_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_production_calendar" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "title" TEXT NOT NULL,
  "event_type" TEXT NOT NULL DEFAULT 'shift',
  "event_date" TEXT NOT NULL,
  "start_time" TEXT DEFAULT NULL,
  "end_time" TEXT DEFAULT NULL,
  "work_center_id" INTEGER DEFAULT NULL,
  "is_holiday" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_production_calendar_uq_mfg_cal_uuid" ON "rateb_mfg_production_calendar" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_calendar_idx_mfg_cal_date" ON "rateb_mfg_production_calendar" ("company_id", "event_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_calendar_fk_mfg_cal_wc" ON "rateb_mfg_production_calendar" ("work_center_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_production_costs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "production_order_id" INTEGER NOT NULL,
  "cost_type" TEXT NOT NULL DEFAULT 'material',
  "amount" REAL NOT NULL DEFAULT 0.0000,
  "currency_code" TEXT DEFAULT 'SAR',
  "cost_center_id" INTEGER DEFAULT NULL,
  "accounting_ref" TEXT DEFAULT NULL,
  "cost_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_production_costs_uq_mfg_cost_uuid" ON "rateb_mfg_production_costs" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_costs_idx_mfg_cost_po" ON "rateb_mfg_production_costs" ("company_id", "production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_costs_idx_mfg_cost_type" ON "rateb_mfg_production_costs" ("company_id", "cost_type");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_costs_fk_mfg_cost_po" ON "rateb_mfg_production_costs" ("production_order_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_production_orders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "product_id" INTEGER NOT NULL,
  "variant_id" INTEGER DEFAULT NULL,
  "bom_id" INTEGER DEFAULT NULL,
  "bom_version_id" INTEGER DEFAULT NULL,
  "routing_id" INTEGER DEFAULT NULL,
  "qty_planned" REAL NOT NULL DEFAULT 1.000000,
  "qty_completed" REAL NOT NULL DEFAULT 0.000000,
  "qty_scrap" REAL NOT NULL DEFAULT 0.000000,
  "uom" TEXT DEFAULT 'EA',
  "warehouse_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER DEFAULT NULL,
  "planned_start" TEXT DEFAULT NULL,
  "planned_end" TEXT DEFAULT NULL,
  "actual_start" TEXT DEFAULT NULL,
  "actual_end" TEXT DEFAULT NULL,
  "priority" INTEGER NOT NULL DEFAULT 50,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_uq_mfg_po_uuid" ON "rateb_mfg_production_orders" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_uq_mfg_po_code" ON "rateb_mfg_production_orders" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_idx_mfg_po_company" ON "rateb_mfg_production_orders" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_idx_mfg_po_workflow" ON "rateb_mfg_production_orders" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_idx_mfg_po_product" ON "rateb_mfg_production_orders" ("company_id", "product_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_idx_mfg_po_project" ON "rateb_mfg_production_orders" ("company_id", "project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_fk_mfg_po_product" ON "rateb_mfg_production_orders" ("product_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_fk_mfg_po_variant" ON "rateb_mfg_production_orders" ("variant_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_fk_mfg_po_bom" ON "rateb_mfg_production_orders" ("bom_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_fk_mfg_po_bomv" ON "rateb_mfg_production_orders" ("bom_version_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_production_orders_fk_mfg_po_routing" ON "rateb_mfg_production_orders" ("routing_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_products" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "inventory_item_id" INTEGER DEFAULT NULL,
  "uom" TEXT DEFAULT 'EA',
  "product_type" TEXT NOT NULL DEFAULT 'finished',
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_products_uq_mfg_prod_uuid" ON "rateb_mfg_products" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_products_uq_mfg_prod_code" ON "rateb_mfg_products" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_products_idx_mfg_prod_company" ON "rateb_mfg_products" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_products_idx_mfg_prod_workflow" ON "rateb_mfg_products" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_products_idx_mfg_prod_item" ON "rateb_mfg_products" ("company_id", "inventory_item_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_quality_checks" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "production_order_id" INTEGER NOT NULL,
  "work_order_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "check_type" TEXT NOT NULL DEFAULT 'in_process',
  "result_status" TEXT NOT NULL DEFAULT 'pending',
  "checklist_json" TEXT DEFAULT NULL,
  "checked_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_quality_checks_uq_mfg_qc_uuid" ON "rateb_mfg_quality_checks" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_quality_checks_uq_mfg_qc_code" ON "rateb_mfg_quality_checks" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_quality_checks_idx_mfg_qc_po" ON "rateb_mfg_quality_checks" ("company_id", "production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_quality_checks_fk_mfg_qc_po" ON "rateb_mfg_quality_checks" ("production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_quality_checks_fk_mfg_qc_wo" ON "rateb_mfg_quality_checks" ("work_order_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_routing_operations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "routing_id" INTEGER NOT NULL,
  "seq_no" INTEGER NOT NULL DEFAULT 10,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "work_center_id" INTEGER DEFAULT NULL,
  "machine_id" INTEGER DEFAULT NULL,
  "setup_minutes" REAL NOT NULL DEFAULT 0.00,
  "run_minutes_per_unit" REAL NOT NULL DEFAULT 0.0000,
  "queue_minutes" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_routing_operations_uq_mfg_op_uuid" ON "rateb_mfg_routing_operations" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_routing_operations_uq_mfg_op_code" ON "rateb_mfg_routing_operations" ("company_id", "routing_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_routing_operations_idx_mfg_op_routing" ON "rateb_mfg_routing_operations" ("company_id", "routing_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_routing_operations_fk_mfg_op_routing" ON "rateb_mfg_routing_operations" ("routing_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_routing_operations_fk_mfg_op_wc" ON "rateb_mfg_routing_operations" ("work_center_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_routing_operations_fk_mfg_op_machine" ON "rateb_mfg_routing_operations" ("machine_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_routings" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "product_id" INTEGER DEFAULT NULL,
  "bom_id" INTEGER DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_routings_uq_mfg_rt_uuid" ON "rateb_mfg_routings" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_routings_uq_mfg_rt_code" ON "rateb_mfg_routings" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_routings_idx_mfg_rt_product" ON "rateb_mfg_routings" ("company_id", "product_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_routings_fk_mfg_rt_product" ON "rateb_mfg_routings" ("product_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_routings_fk_mfg_rt_bom" ON "rateb_mfg_routings" ("bom_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_schedules" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "production_order_id" INTEGER NOT NULL,
  "work_order_id" INTEGER DEFAULT NULL,
  "work_center_id" INTEGER DEFAULT NULL,
  "scheduled_start" TEXT NOT NULL,
  "scheduled_end" TEXT NOT NULL,
  "schedule_status" TEXT NOT NULL DEFAULT 'planned',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_schedules_uq_mfg_sch_uuid" ON "rateb_mfg_schedules" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_schedules_idx_mfg_sch_po" ON "rateb_mfg_schedules" ("company_id", "production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_schedules_idx_mfg_sch_start" ON "rateb_mfg_schedules" ("company_id", "scheduled_start");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_schedules_fk_mfg_sch_po" ON "rateb_mfg_schedules" ("production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_schedules_fk_mfg_sch_wo" ON "rateb_mfg_schedules" ("work_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_schedules_fk_mfg_sch_wc" ON "rateb_mfg_schedules" ("work_center_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_scrap_records" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "production_order_id" INTEGER NOT NULL,
  "work_order_id" INTEGER DEFAULT NULL,
  "qty_scrap" REAL NOT NULL DEFAULT 0.000000,
  "uom" TEXT DEFAULT 'EA',
  "reason_code" TEXT DEFAULT NULL,
  "reason" TEXT DEFAULT NULL,
  "scrap_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'posted',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_scrap_records_uq_mfg_scrap_uuid" ON "rateb_mfg_scrap_records" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_scrap_records_idx_mfg_scrap_po" ON "rateb_mfg_scrap_records" ("company_id", "production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_scrap_records_fk_mfg_scrap_po" ON "rateb_mfg_scrap_records" ("production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_scrap_records_fk_mfg_scrap_wo" ON "rateb_mfg_scrap_records" ("work_order_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_status_history_uq_mfg_sh_uuid" ON "rateb_mfg_status_history" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_status_history_idx_mfg_sh_entity" ON "rateb_mfg_status_history" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_mfg_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_tags_uq_mfg_tag_uuid" ON "rateb_mfg_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_tags_uq_mfg_tag_code" ON "rateb_mfg_tags" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_mfg_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT DEFAULT NULL,
  "body" TEXT DEFAULT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_timeline_uq_mfg_tl_uuid" ON "rateb_mfg_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_timeline_idx_mfg_tl_entity" ON "rateb_mfg_timeline" ("company_id", "entity_type", "entity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_timeline_idx_mfg_tl_created" ON "rateb_mfg_timeline" ("company_id", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_mfg_work_centers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "capacity_hours_day" REAL NOT NULL DEFAULT 8.00,
  "cost_per_hour" REAL NOT NULL DEFAULT 0.0000,
  "warehouse_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_work_centers_uq_mfg_wc_uuid" ON "rateb_mfg_work_centers" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_work_centers_uq_mfg_wc_code" ON "rateb_mfg_work_centers" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_work_centers_idx_mfg_wc_company" ON "rateb_mfg_work_centers" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_mfg_work_orders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "production_order_id" INTEGER NOT NULL,
  "routing_operation_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "work_center_id" INTEGER DEFAULT NULL,
  "machine_id" INTEGER DEFAULT NULL,
  "seq_no" INTEGER NOT NULL DEFAULT 10,
  "qty_planned" REAL NOT NULL DEFAULT 1.000000,
  "qty_completed" REAL NOT NULL DEFAULT 0.000000,
  "planned_start" TEXT DEFAULT NULL,
  "planned_end" TEXT DEFAULT NULL,
  "actual_start" TEXT DEFAULT NULL,
  "actual_end" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_work_orders_uq_mfg_wo_uuid" ON "rateb_mfg_work_orders" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_mfg_work_orders_uq_mfg_wo_code" ON "rateb_mfg_work_orders" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_work_orders_idx_mfg_wo_po" ON "rateb_mfg_work_orders" ("company_id", "production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_work_orders_idx_mfg_wo_workflow" ON "rateb_mfg_work_orders" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_work_orders_fk_mfg_wo_po" ON "rateb_mfg_work_orders" ("production_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_work_orders_fk_mfg_wo_op" ON "rateb_mfg_work_orders" ("routing_operation_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_work_orders_fk_mfg_wo_wc" ON "rateb_mfg_work_orders" ("work_center_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_mfg_work_orders_fk_mfg_wo_machine" ON "rateb_mfg_work_orders" ("machine_id");

CREATE TABLE IF NOT EXISTS "rateb_migrations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "filename" TEXT NOT NULL,
  "applied_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_migrations_uq_migration_filename" ON "rateb_migrations" ("filename");

CREATE TABLE IF NOT EXISTS "rateb_notification_queue" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "channel" TEXT NOT NULL DEFAULT 'in_app',
  "template_slug" TEXT DEFAULT NULL,
  "recipient" TEXT DEFAULT NULL,
  "subject" TEXT DEFAULT NULL,
  "body" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "attempt_count" INTEGER NOT NULL DEFAULT 0,
  "sent_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "next_retry_at" TEXT DEFAULT NULL,
  "dead_letter_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_notification_queue_idx_nq_status" ON "rateb_notification_queue" ("status");
CREATE INDEX IF NOT EXISTS "idx_rateb_notification_queue_idx_nq_status_created" ON "rateb_notification_queue" ("status", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_notifications" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "title" TEXT NOT NULL,
  "message" TEXT NOT NULL,
  "type" TEXT NOT NULL DEFAULT 'info',
  "trigger_type" TEXT DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "is_read" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_notifications_idx_notif_user" ON "rateb_notifications" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_notifications_idx_notif_company" ON "rateb_notifications" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_notifications_idx_notif_dedup" ON "rateb_notifications" ("company_id", "trigger_type", "entity_type", "entity_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_notifications_idx_notif_ess_visible" ON "rateb_notifications" ("company_id", "user_id", "id");

CREATE TABLE IF NOT EXISTS "rateb_offline_devices" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "device_id" TEXT NOT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "label" TEXT DEFAULT NULL,
  "fingerprint" TEXT DEFAULT NULL,
  "nickname" TEXT DEFAULT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "last_seen_at" TEXT DEFAULT NULL,
  "last_online_at" TEXT DEFAULT NULL,
  "last_replay_at" TEXT DEFAULT NULL,
  "last_unlock_at" TEXT DEFAULT NULL,
  "last_logout_at" TEXT DEFAULT NULL,
  "identity_expires_at" INTEGER DEFAULT NULL,
  "identity_version" INTEGER NOT NULL DEFAULT 1,
  "identity_jti" TEXT DEFAULT NULL,
  "force_logout_at" TEXT DEFAULT NULL,
  "vault_integrity" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "trust_status" TEXT NOT NULL DEFAULT 'trusted',
  "activated_by" INTEGER DEFAULT NULL,
  "activated_at" TEXT DEFAULT NULL,
  "approved_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_offline_devices_uq_offline_device" ON "rateb_offline_devices" ("company_id", "device_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_devices_idx_offline_device_branch" ON "rateb_offline_devices" ("company_id", "branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_devices_idx_offline_device_status" ON "rateb_offline_devices" ("company_id", "status");

CREATE TABLE IF NOT EXISTS "rateb_offline_entity_cursors" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "cursor_token" TEXT DEFAULT NULL,
  "updated_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_offline_entity_cursors_uq_offline_cursor_scope" ON "rateb_offline_entity_cursors" ("company_id", "branch_id", "entity_type");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_entity_cursors_idx_offline_cursor_entity" ON "rateb_offline_entity_cursors" ("company_id", "entity_type");

CREATE TABLE IF NOT EXISTS "rateb_offline_identity_audit" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "device_id" TEXT DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "detail_json" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_offline_identity_audit_idx_offline_id_audit_company" ON "rateb_offline_identity_audit" ("company_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_identity_audit_idx_offline_id_audit_device" ON "rateb_offline_identity_audit" ("company_id", "device_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_identity_audit_idx_offline_id_audit_event" ON "rateb_offline_identity_audit" ("event_type");

CREATE TABLE IF NOT EXISTS "rateb_offline_identity_nonces" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "device_id" TEXT NOT NULL,
  "jti" TEXT NOT NULL,
  "identity_version" INTEGER NOT NULL DEFAULT 1,
  "status" TEXT NOT NULL DEFAULT 'active',
  "issued_at" INTEGER NOT NULL,
  "expires_at" INTEGER NOT NULL,
  "invalidated_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_offline_identity_nonces_uq_offline_identity_jti" ON "rateb_offline_identity_nonces" ("company_id", "jti");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_identity_nonces_idx_offline_identity_nonce_device" ON "rateb_offline_identity_nonces" ("company_id", "device_id", "status");

CREATE TABLE IF NOT EXISTS "rateb_offline_sync_conflicts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "queue_id" INTEGER NOT NULL,
  "idempotency_key" TEXT NOT NULL,
  "reason" TEXT NOT NULL DEFAULT 'server_newer',
  "client_payload" TEXT NOT NULL,
  "server_payload" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "resolved_by" INTEGER DEFAULT NULL,
  "resolved_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_offline_sync_conflicts_idx_offline_sync_conflict_status" ON "rateb_offline_sync_conflicts" ("company_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_sync_conflicts_idx_offline_sync_conflict_queue" ON "rateb_offline_sync_conflicts" ("queue_id");

CREATE TABLE IF NOT EXISTS "rateb_offline_sync_queue" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "device_id" TEXT DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "module" TEXT NOT NULL DEFAULT 'offline_meta',
  "action" TEXT NOT NULL DEFAULT 'offline.ack',
  "idempotency_key" TEXT NOT NULL,
  "payload" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "version" INTEGER NOT NULL DEFAULT 1,
  "retry_count" INTEGER NOT NULL DEFAULT 0,
  "last_error" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "synced_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_offline_sync_queue_uq_offline_sync_idem" ON "rateb_offline_sync_queue" ("company_id", "idempotency_key");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_sync_queue_idx_offline_sync_status" ON "rateb_offline_sync_queue" ("company_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_offline_sync_queue_idx_offline_sync_device" ON "rateb_offline_sync_queue" ("company_id", "device_id");

CREATE TABLE IF NOT EXISTS "rateb_password_resets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "user_id" INTEGER NOT NULL,
  "token_hash" TEXT NOT NULL,
  "expires_at" TEXT NOT NULL,
  "used_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_password_resets_token_hash" ON "rateb_password_resets" ("token_hash");
CREATE INDEX IF NOT EXISTS "idx_rateb_password_resets_idx_pr_user" ON "rateb_password_resets" ("user_id");

CREATE TABLE IF NOT EXISTS "rateb_payments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "subscription_id" INTEGER DEFAULT NULL,
  "invoice_id" INTEGER DEFAULT NULL,
  "amount" REAL NOT NULL,
  "currency" TEXT NOT NULL DEFAULT 'SAR',
  "method" TEXT DEFAULT NULL,
  "reference_no" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "paid_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_payments_idx_payments_company" ON "rateb_payments" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payments_idx_payments_invoice" ON "rateb_payments" ("invoice_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_adjustments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "payroll_item_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "adjustment_type" TEXT NOT NULL DEFAULT 'correction',
  "amount" REAL NOT NULL DEFAULT 0.00,
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_adjustments_uq_pay_adj_uuid" ON "rateb_payroll_adjustments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_adjustments_idx_pay_adj_batch" ON "rateb_payroll_adjustments" ("company_id", "batch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_adjustments_fk_pay_adj_batch" ON "rateb_payroll_adjustments" ("batch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_adjustments_fk_pay_adj_item" ON "rateb_payroll_adjustments" ("payroll_item_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_advances" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "advance_date" TEXT NOT NULL,
  "recovery_amount" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_advances_uq_pay_adv_uuid" ON "rateb_payroll_advances" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_advances_uq_pay_adv_code" ON "rateb_payroll_advances" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_advances_idx_pay_adv_emp" ON "rateb_payroll_advances" ("company_id", "hrm_employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_advances_fk_pay_adv_batch" ON "rateb_payroll_advances" ("batch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER DEFAULT NULL,
  "role_label" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_assignments_uq_pay_assign_uuid" ON "rateb_payroll_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_assignments_idx_pay_assign_entity" ON "rateb_payroll_assignments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_attachments_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "doc_type" TEXT NOT NULL DEFAULT 'attachment',
  "title" TEXT NOT NULL,
  "file_name" TEXT DEFAULT NULL,
  "mime_type" TEXT DEFAULT NULL,
  "file_size" INTEGER DEFAULT NULL,
  "storage_key" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_attachments_meta_uq_pay_att_uuid" ON "rateb_payroll_attachments_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_attachments_meta_idx_pay_att_entity" ON "rateb_payroll_attachments_meta" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_audit" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "action" TEXT NOT NULL,
  "payload_json" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_audit_uq_pay_audit_uuid" ON "rateb_payroll_audit" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_audit_idx_pay_audit_entity" ON "rateb_payroll_audit" ("company_id", "entity_type", "entity_id", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_payroll_batches" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "cycle_id" INTEGER DEFAULT NULL,
  "run_period_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "title_ar" TEXT DEFAULT NULL,
  "period_start" TEXT DEFAULT NULL,
  "period_end" TEXT DEFAULT NULL,
  "pay_date" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "total_gross" REAL NOT NULL DEFAULT 0.00,
  "total_deductions" REAL NOT NULL DEFAULT 0.00,
  "total_net" REAL NOT NULL DEFAULT 0.00,
  "employee_count" INTEGER NOT NULL DEFAULT 0,
  "accounting_post_ref" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_batches_uq_pay_batch_uuid" ON "rateb_payroll_batches" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_batches_uq_pay_batch_code" ON "rateb_payroll_batches" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_batches_idx_pay_batch_wf" ON "rateb_payroll_batches" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_batches_fk_pay_batch_cycle" ON "rateb_payroll_batches" ("cycle_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_batches_fk_pay_batch_runp" ON "rateb_payroll_batches" ("run_period_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_bonuses" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "bonus_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_bonuses_uq_pay_bonus_uuid" ON "rateb_payroll_bonuses" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_bonuses_idx_pay_bonus_emp" ON "rateb_payroll_bonuses" ("company_id", "hrm_employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_bonuses_fk_pay_bonus_batch" ON "rateb_payroll_bonuses" ("batch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "comment_text" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_comments_uq_pay_comment_uuid" ON "rateb_payroll_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_comments_idx_pay_comment_entity" ON "rateb_payroll_comments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_commissions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "commission_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_commissions_uq_pay_comm_uuid" ON "rateb_payroll_commissions" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_commissions_idx_pay_comm_emp" ON "rateb_payroll_commissions" ("company_id", "hrm_employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_commissions_fk_pay_comm_batch" ON "rateb_payroll_commissions" ("batch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_cycles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "frequency" TEXT NOT NULL DEFAULT 'monthly',
  "start_day" INTEGER NOT NULL DEFAULT 1,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_cycles_uq_pay_cycle_uuid" ON "rateb_payroll_cycles" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_cycles_uq_pay_cycle_code" ON "rateb_payroll_cycles" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_cycles_idx_pay_cycle_company" ON "rateb_payroll_cycles" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_payroll_deduction_types" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "statutory" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_deduction_types_uq_pay_ded_uuid" ON "rateb_payroll_deduction_types" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_deduction_types_uq_pay_ded_code" ON "rateb_payroll_deduction_types" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_deduction_types_idx_pay_ded_company" ON "rateb_payroll_deduction_types" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_payroll_earning_types" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "taxable" INTEGER NOT NULL DEFAULT 1,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_earning_types_uq_pay_earn_uuid" ON "rateb_payroll_earning_types" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_earning_types_uq_pay_earn_code" ON "rateb_payroll_earning_types" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_earning_types_idx_pay_earn_company" ON "rateb_payroll_earning_types" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_payroll_employee_salary" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "structure_id" INTEGER DEFAULT NULL,
  "basic_salary" REAL NOT NULL DEFAULT 0.00,
  "currency_code" TEXT NOT NULL DEFAULT 'SAR',
  "effective_from" TEXT DEFAULT NULL,
  "effective_to" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_employee_salary_uq_pay_emp_sal_uuid" ON "rateb_payroll_employee_salary" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_employee_salary_idx_pay_emp_sal_profile" ON "rateb_payroll_employee_salary" ("company_id", "hrm_employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_employee_salary_idx_pay_emp_sal_legacy" ON "rateb_payroll_employee_salary" ("company_id", "legacy_employee_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_employee_salary_fk_pay_emp_sal_struct" ON "rateb_payroll_employee_salary" ("structure_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_items" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER NOT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "employee_salary_id" INTEGER DEFAULT NULL,
  "basic_salary" REAL NOT NULL DEFAULT 0.00,
  "gross_amount" REAL NOT NULL DEFAULT 0.00,
  "deduction_amount" REAL NOT NULL DEFAULT 0.00,
  "net_amount" REAL NOT NULL DEFAULT 0.00,
  "attendance_ref" TEXT DEFAULT NULL,
  "leave_ref" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_items_uq_pay_item_uuid" ON "rateb_payroll_items" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_items_idx_pay_item_batch" ON "rateb_payroll_items" ("company_id", "batch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_items_idx_pay_item_emp" ON "rateb_payroll_items" ("company_id", "hrm_employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_items_fk_pay_item_batch" ON "rateb_payroll_items" ("batch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_items_fk_pay_item_emp_sal" ON "rateb_payroll_items" ("employee_salary_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "period_id" INTEGER NOT NULL,
  "employee_id" INTEGER NOT NULL,
  "basic_salary" REAL NOT NULL DEFAULT 0.00,
  "allowances" REAL NOT NULL DEFAULT 0.00,
  "deductions" REAL NOT NULL DEFAULT 0.00,
  "net_salary" REAL NOT NULL DEFAULT 0.00,
  "notes" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_lines_uk_payroll_line" ON "rateb_payroll_lines" ("period_id", "employee_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_lines_idx_payroll_line_company" ON "rateb_payroll_lines" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_lines_idx_pl_branch" ON "rateb_payroll_lines" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_loan_installments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "loan_id" INTEGER NOT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "installment_no" INTEGER NOT NULL DEFAULT 1,
  "due_date" TEXT NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "paid_amount" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_loan_installments_uq_pay_loan_inst_uuid" ON "rateb_payroll_loan_installments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_loan_installments_idx_pay_loan_inst_loan" ON "rateb_payroll_loan_installments" ("company_id", "loan_id", "due_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_loan_installments_fk_pay_loan_inst_loan" ON "rateb_payroll_loan_installments" ("loan_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_loan_installments_fk_pay_loan_inst_batch" ON "rateb_payroll_loan_installments" ("batch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_loans" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "principal_amount" REAL NOT NULL DEFAULT 0.00,
  "outstanding_amount" REAL NOT NULL DEFAULT 0.00,
  "installment_amount" REAL NOT NULL DEFAULT 0.00,
  "installments_total" INTEGER NOT NULL DEFAULT 0,
  "installments_paid" INTEGER NOT NULL DEFAULT 0,
  "start_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_loans_uq_pay_loan_uuid" ON "rateb_payroll_loans" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_loans_uq_pay_loan_code" ON "rateb_payroll_loans" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_loans_idx_pay_loan_emp" ON "rateb_payroll_loans" ("company_id", "hrm_employee_profile_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_notes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_notes_uq_pay_note_uuid" ON "rateb_payroll_notes" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_notes_idx_pay_note_entity" ON "rateb_payroll_notes" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_overtime" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "overtime_date" TEXT NOT NULL,
  "hours" REAL NOT NULL DEFAULT 0.00,
  "rate_multiplier" REAL NOT NULL DEFAULT 1.50,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "attendance_ref" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_overtime_uq_pay_ot_uuid" ON "rateb_payroll_overtime" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_overtime_idx_pay_ot_emp" ON "rateb_payroll_overtime" ("company_id", "hrm_employee_profile_id", "overtime_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_overtime_fk_pay_ot_batch" ON "rateb_payroll_overtime" ("batch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_payslips" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER NOT NULL,
  "payroll_item_id" INTEGER NOT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "payslip_number" TEXT NOT NULL,
  "period_start" TEXT DEFAULT NULL,
  "period_end" TEXT DEFAULT NULL,
  "pay_date" TEXT DEFAULT NULL,
  "gross_amount" REAL NOT NULL DEFAULT 0.00,
  "deduction_amount" REAL NOT NULL DEFAULT 0.00,
  "net_amount" REAL NOT NULL DEFAULT 0.00,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_payslips_uq_pay_pslip_uuid" ON "rateb_payroll_payslips" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_payslips_uq_pay_pslip_no" ON "rateb_payroll_payslips" ("company_id", "payslip_number");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_payslips_idx_pay_pslip_batch" ON "rateb_payroll_payslips" ("company_id", "batch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_payslips_fk_pay_pslip_batch" ON "rateb_payroll_payslips" ("batch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_payslips_fk_pay_pslip_item" ON "rateb_payroll_payslips" ("payroll_item_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_periods" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "period_year" INTEGER NOT NULL,
  "period_month" INTEGER NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_periods_uk_payroll_period" ON "rateb_payroll_periods" ("company_id", "period_year", "period_month");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_periods_idx_payroll_company" ON "rateb_payroll_periods" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_periods_idx_pp_branch" ON "rateb_payroll_periods" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_reimbursements" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "expense_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_reimbursements_uq_pay_reimb_uuid" ON "rateb_payroll_reimbursements" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_reimbursements_idx_pay_reimb_emp" ON "rateb_payroll_reimbursements" ("company_id", "hrm_employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_reimbursements_fk_pay_reimb_batch" ON "rateb_payroll_reimbursements" ("batch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_run_periods" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "cycle_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "period_start" TEXT NOT NULL,
  "period_end" TEXT NOT NULL,
  "pay_date" TEXT DEFAULT NULL,
  "legacy_payroll_period_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_run_periods_uq_pay_runp_uuid" ON "rateb_payroll_run_periods" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_run_periods_uq_pay_runp_code" ON "rateb_payroll_run_periods" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_run_periods_idx_pay_runp_cycle" ON "rateb_payroll_run_periods" ("company_id", "cycle_id", "period_start");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_run_periods_fk_pay_runp_cycle" ON "rateb_payroll_run_periods" ("cycle_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_salary_components" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "structure_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "component_type" TEXT NOT NULL DEFAULT 'earning',
  "calc_method" TEXT NOT NULL DEFAULT 'fixed',
  "amount" REAL NOT NULL DEFAULT 0.00,
  "percent_value" REAL DEFAULT NULL,
  "earning_type_id" INTEGER DEFAULT NULL,
  "deduction_type_id" INTEGER DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_salary_components_uq_pay_scomp_uuid" ON "rateb_payroll_salary_components" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_salary_components_uq_pay_scomp_code" ON "rateb_payroll_salary_components" ("company_id", "structure_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_salary_components_idx_pay_scomp_struct" ON "rateb_payroll_salary_components" ("company_id", "structure_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_salary_components_fk_pay_scomp_struct" ON "rateb_payroll_salary_components" ("structure_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_salary_structures" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "currency_code" TEXT NOT NULL DEFAULT 'SAR',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_salary_structures_uq_pay_sstruct_uuid" ON "rateb_payroll_salary_structures" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_salary_structures_uq_pay_sstruct_code" ON "rateb_payroll_salary_structures" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_salary_structures_idx_pay_sstruct_company" ON "rateb_payroll_salary_structures" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_payroll_settlements" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "hrm_employee_profile_id" INTEGER DEFAULT NULL,
  "legacy_employee_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "settlement_type" TEXT NOT NULL DEFAULT 'final',
  "amount" REAL NOT NULL DEFAULT 0.00,
  "settlement_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_settlements_uq_pay_settle_uuid" ON "rateb_payroll_settlements" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_settlements_idx_pay_settle_emp" ON "rateb_payroll_settlements" ("company_id", "hrm_employee_profile_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_settlements_fk_pay_settle_batch" ON "rateb_payroll_settlements" ("batch_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_status_history_uq_pay_sh_uuid" ON "rateb_payroll_status_history" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_status_history_idx_pay_sh_entity" ON "rateb_payroll_status_history" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_payroll_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_payroll_timeline_uq_pay_tl_uuid" ON "rateb_payroll_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_timeline_idx_pay_tl_company" ON "rateb_payroll_timeline" ("company_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_payroll_timeline_idx_pay_tl_entity" ON "rateb_payroll_timeline" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_permissions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "slug" TEXT NOT NULL,
  "module" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "description_ar" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_permissions_slug" ON "rateb_permissions" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_pharmacy_dispenses" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "prescription_id" INTEGER NOT NULL,
  "drug_name" TEXT NOT NULL,
  "quantity" REAL NOT NULL DEFAULT 1.000,
  "dispensed_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pharmacy_dispenses_fk_disp_company" ON "rateb_pharmacy_dispenses" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pharmacy_dispenses_fk_disp_rx" ON "rateb_pharmacy_dispenses" ("prescription_id");

CREATE TABLE IF NOT EXISTS "rateb_pharmacy_prescriptions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "prescription_no" TEXT NOT NULL,
  "patient_ref" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "prescribed_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pharmacy_prescriptions_uq_rx" ON "rateb_pharmacy_prescriptions" ("company_id", "prescription_no");

CREATE TABLE IF NOT EXISTS "rateb_plans" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "slug" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "price_monthly" REAL NOT NULL DEFAULT 0.00,
  "price_yearly" REAL NOT NULL DEFAULT 0.00,
  "max_users" INTEGER NOT NULL DEFAULT 10,
  "max_storage_mb" INTEGER NOT NULL DEFAULT 1024,
  "max_branches" INTEGER NOT NULL DEFAULT 10,
  "modules" TEXT DEFAULT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_plans_slug" ON "rateb_plans" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_pos_approval_grants" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "request_id" INTEGER NOT NULL,
  "supervisor_user_id" INTEGER NOT NULL,
  "biometric_method" TEXT NOT NULL DEFAULT 'webauthn',
  "token_hash" TEXT NOT NULL,
  "verified_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "expires_at" TEXT NOT NULL,
  "consumed_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_approval_grants_uq_pos_approval_token_hash" ON "rateb_pos_approval_grants" ("token_hash");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_approval_grants_idx_pos_approval_grant_request" ON "rateb_pos_approval_grants" ("request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_approval_grants_fk_pos_grant_supervisor" ON "rateb_pos_approval_grants" ("supervisor_user_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_approval_requests" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "register_session_id" INTEGER DEFAULT NULL,
  "action_type" TEXT NOT NULL,
  "payload_json" TEXT NOT NULL,
  "requested_by" INTEGER NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "consumed_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_approval_requests_idx_pos_approval_company" ON "rateb_pos_approval_requests" ("company_id", "status", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_approval_requests_idx_pos_approval_requester" ON "rateb_pos_approval_requests" ("requested_by");

CREATE TABLE IF NOT EXISTS "rateb_pos_batch_ledger" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "order_id" INTEGER NOT NULL,
  "order_line_id" INTEGER DEFAULT NULL,
  "original_line_id" INTEGER DEFAULT NULL,
  "movement_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER NOT NULL,
  "direction" TEXT NOT NULL,
  "quantity" REAL NOT NULL DEFAULT 0.000,
  "reference_type" TEXT NOT NULL DEFAULT 'pos_order',
  "reference_id" INTEGER DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_batch_ledger_idx_pos_batch_ledger_line" ON "rateb_pos_batch_ledger" ("company_id", "order_line_id", "direction");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_batch_ledger_idx_pos_batch_ledger_orig" ON "rateb_pos_batch_ledger" ("company_id", "original_line_id", "batch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_batch_ledger_idx_pos_batch_ledger_order" ON "rateb_pos_batch_ledger" ("company_id", "order_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_branch_prices" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "inventory_id" INTEGER NOT NULL,
  "price" REAL NOT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_branch_prices_uq_pos_branch_price" ON "rateb_pos_branch_prices" ("company_id", "branch_id", "inventory_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_branch_prices_idx_pos_branch_price_inv" ON "rateb_pos_branch_prices" ("inventory_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_cash_drawer_events" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "drawer_id" INTEGER NOT NULL,
  "shift_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "notes" TEXT DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_cash_drawer_events_idx_pos_drawer_event_drawer" ON "rateb_pos_cash_drawer_events" ("drawer_id", "created_at");

CREATE TABLE IF NOT EXISTS "rateb_pos_cash_drawers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "terminal_id" INTEGER NOT NULL,
  "shift_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'closed',
  "expected_balance" REAL NOT NULL DEFAULT 0.00,
  "counted_balance" REAL DEFAULT NULL,
  "variance" REAL DEFAULT NULL,
  "opened_at" TEXT DEFAULT NULL,
  "closed_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_cash_drawers_idx_pos_drawer_branch" ON "rateb_pos_cash_drawers" ("company_id", "branch_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_cash_drawers_idx_pos_drawer_terminal" ON "rateb_pos_cash_drawers" ("terminal_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_cash_drawers_idx_pos_drawer_shift" ON "rateb_pos_cash_drawers" ("shift_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_coupon_redemptions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "coupon_id" INTEGER NOT NULL,
  "order_id" INTEGER NOT NULL,
  "discount_amount" REAL NOT NULL DEFAULT 0.00,
  "reversed_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_coupon_redemptions_uq_pos_coupon_order" ON "rateb_pos_coupon_redemptions" ("company_id", "coupon_id", "order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_coupon_redemptions_idx_pos_coupon_red_order" ON "rateb_pos_coupon_redemptions" ("order_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_coupons" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "discount_type" TEXT NOT NULL DEFAULT 'percent',
  "discount_value" REAL NOT NULL DEFAULT 0.00,
  "max_uses" INTEGER DEFAULT NULL,
  "used_count" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "reversible" INTEGER NOT NULL DEFAULT 1,
  "valid_from" TEXT DEFAULT NULL,
  "valid_to" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_coupons_uq_pos_coupon_code" ON "rateb_pos_coupons" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_pos_gift_card_ledger" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "gift_card_id" INTEGER NOT NULL,
  "order_id" INTEGER DEFAULT NULL,
  "entry_type" TEXT NOT NULL,
  "amount" REAL NOT NULL,
  "balance_after" REAL NOT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_gift_card_ledger_idx_pos_gc_ledger_card" ON "rateb_pos_gift_card_ledger" ("company_id", "gift_card_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_gift_cards" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "balance" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'active',
  "expires_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_gift_cards_uq_pos_gift_code" ON "rateb_pos_gift_cards" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_pos_gl_postings" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "order_id" INTEGER NOT NULL,
  "posting_kind" TEXT NOT NULL,
  "journal_entry_id" INTEGER NOT NULL,
  "source_type" TEXT NOT NULL,
  "source_id" INTEGER NOT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_gl_postings_uq_pos_gl_source" ON "rateb_pos_gl_postings" ("company_id", "source_type", "source_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_gl_postings_idx_pos_gl_order" ON "rateb_pos_gl_postings" ("company_id", "order_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_group_prices" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "price_group_id" INTEGER NOT NULL,
  "inventory_id" INTEGER NOT NULL,
  "price" REAL NOT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_group_prices_uq_pos_group_price" ON "rateb_pos_group_prices" ("company_id", "price_group_id", "inventory_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_group_prices_idx_pos_group_price_inv" ON "rateb_pos_group_prices" ("inventory_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_group_prices_fk_pos_group_price_group" ON "rateb_pos_group_prices" ("price_group_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_inventory_reservations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "inventory_id" INTEGER NOT NULL,
  "session_id" INTEGER DEFAULT NULL,
  "quantity" REAL NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "expires_at" TEXT NOT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_inventory_reservations_idx_pos_res_inv" ON "rateb_pos_inventory_reservations" ("company_id", "inventory_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_inventory_reservations_fk_pos_res_inv" ON "rateb_pos_inventory_reservations" ("inventory_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_loyalty_accounts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "customer_id" INTEGER NOT NULL,
  "points_balance" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'active',
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_loyalty_accounts_uq_pos_loyalty_customer" ON "rateb_pos_loyalty_accounts" ("company_id", "customer_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_loyalty_ledger" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "customer_id" INTEGER NOT NULL,
  "order_id" INTEGER DEFAULT NULL,
  "entry_type" TEXT NOT NULL,
  "points" REAL NOT NULL,
  "balance_after" REAL NOT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_loyalty_ledger_idx_pos_loyalty_ledger_cust" ON "rateb_pos_loyalty_ledger" ("company_id", "customer_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_order_lines" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "order_id" INTEGER NOT NULL,
  "company_id" INTEGER NOT NULL,
  "inventory_id" INTEGER DEFAULT NULL,
  "batch_id" INTEGER DEFAULT NULL,
  "batch_allocations_json" TEXT DEFAULT NULL,
  "serial_no" TEXT DEFAULT NULL,
  "serial_id" INTEGER DEFAULT NULL,
  "line_no" INTEGER NOT NULL DEFAULT 1,
  "line_kind" TEXT NOT NULL DEFAULT 'sale',
  "original_line_id" INTEGER DEFAULT NULL,
  "return_reason" TEXT DEFAULT NULL,
  "description" TEXT NOT NULL DEFAULT '',
  "quantity" REAL NOT NULL DEFAULT 0.000,
  "unit_price" REAL NOT NULL DEFAULT 0.00,
  "discount_amount" REAL NOT NULL DEFAULT 0.00,
  "tax_amount" REAL NOT NULL DEFAULT 0.00,
  "line_total" REAL NOT NULL DEFAULT 0.00
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_order_lines_idx_pos_line_order" ON "rateb_pos_order_lines" ("order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_order_lines_idx_pos_line_original" ON "rateb_pos_order_lines" ("original_line_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_orders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "warehouse_id" INTEGER DEFAULT NULL,
  "terminal_id" INTEGER DEFAULT NULL,
  "shift_id" INTEGER DEFAULT NULL,
  "session_id" INTEGER DEFAULT NULL,
  "order_no" TEXT NOT NULL,
  "idempotency_key" TEXT DEFAULT NULL,
  "order_type" TEXT NOT NULL DEFAULT 'sale',
  "status" TEXT NOT NULL DEFAULT 'draft',
  "completed_at" TEXT DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "original_order_id" INTEGER DEFAULT NULL,
  "linked_order_id" INTEGER DEFAULT NULL,
  "gift_receipt" INTEGER NOT NULL DEFAULT 0,
  "quote_expires_at" TEXT DEFAULT NULL,
  "suspended_payload" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "subtotal" REAL NOT NULL DEFAULT 0.00,
  "discount_total" REAL NOT NULL DEFAULT 0.00,
  "coupon_code" TEXT DEFAULT NULL,
  "promotion_id" INTEGER DEFAULT NULL,
  "loyalty_points_redeemed" REAL NOT NULL DEFAULT 0.00,
  "loyalty_points_earned" REAL NOT NULL DEFAULT 0.00,
  "gift_card_code" TEXT DEFAULT NULL,
  "tax" REAL NOT NULL DEFAULT 0.00,
  "total" REAL NOT NULL DEFAULT 0.00,
  "receipt_json" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_orders_uq_pos_order_no" ON "rateb_pos_orders" ("company_id", "order_no");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_orders_uq_pos_order_idempotency" ON "rateb_pos_orders" ("company_id", "idempotency_key");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_orders_idx_pos_order_branch" ON "rateb_pos_orders" ("company_id", "branch_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_orders_idx_pos_order_original" ON "rateb_pos_orders" ("original_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_orders_idx_pos_order_type_status" ON "rateb_pos_orders" ("company_id", "branch_id", "order_type", "status");

CREATE TABLE IF NOT EXISTS "rateb_pos_payments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "order_id" INTEGER NOT NULL,
  "company_id" INTEGER NOT NULL,
  "payment_method" TEXT NOT NULL DEFAULT 'cash',
  "amount" REAL NOT NULL DEFAULT 0.00,
  "reference_no" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_payments_idx_pos_payment_order" ON "rateb_pos_payments" ("order_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_price_groups" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_price_groups_uq_pos_price_group_code" ON "rateb_pos_price_groups" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_pos_promotion_applications" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "promotion_id" INTEGER NOT NULL,
  "order_id" INTEGER NOT NULL,
  "inventory_id" INTEGER DEFAULT NULL,
  "discount_amount" REAL NOT NULL DEFAULT 0.00,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_promotion_applications_idx_pos_promo_app_order" ON "rateb_pos_promotion_applications" ("company_id", "order_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_promotions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "rules_json" TEXT DEFAULT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 0,
  "valid_from" TEXT DEFAULT NULL,
  "valid_to" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_promotions_uq_pos_promotion_code" ON "rateb_pos_promotions" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_pos_refunds" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "order_id" INTEGER NOT NULL,
  "original_order_id" INTEGER DEFAULT NULL,
  "refund_method" TEXT NOT NULL DEFAULT 'cash',
  "amount" REAL NOT NULL,
  "reference_no" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'completed',
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_refunds_idx_pos_refund_order" ON "rateb_pos_refunds" ("order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_refunds_idx_pos_refund_branch" ON "rateb_pos_refunds" ("company_id", "branch_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_report_snapshots" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "terminal_id" INTEGER DEFAULT NULL,
  "shift_id" INTEGER DEFAULT NULL,
  "report_type" TEXT NOT NULL,
  "report_no" TEXT NOT NULL,
  "snapshot_json" TEXT NOT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_report_snapshots_uq_pos_report_no" ON "rateb_pos_report_snapshots" ("company_id", "report_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_report_snapshots_idx_pos_report_shift" ON "rateb_pos_report_snapshots" ("company_id", "shift_id", "report_type");

CREATE TABLE IF NOT EXISTS "rateb_pos_reports_snapshots" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "terminal_id" INTEGER DEFAULT NULL,
  "shift_id" INTEGER DEFAULT NULL,
  "report_type" TEXT NOT NULL,
  "snapshot_json" TEXT NOT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_reports_snapshots_idx_pos_report_shift" ON "rateb_pos_reports_snapshots" ("shift_id", "report_type");

CREATE TABLE IF NOT EXISTS "rateb_pos_returns" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "original_order_id" INTEGER DEFAULT NULL,
  "order_id" INTEGER DEFAULT NULL,
  "return_no" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "total" REAL NOT NULL DEFAULT 0.00,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_returns_uq_pos_return_no" ON "rateb_pos_returns" ("company_id", "return_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_returns_idx_pos_return_branch" ON "rateb_pos_returns" ("company_id", "branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_returns_idx_pos_return_order" ON "rateb_pos_returns" ("order_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_reward_reversals" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "return_order_id" INTEGER NOT NULL,
  "original_order_id" INTEGER NOT NULL,
  "reversal_kind" TEXT NOT NULL,
  "reference_id" INTEGER DEFAULT NULL,
  "reference_id_key" INTEGER,
  "points" REAL DEFAULT NULL,
  "amount" REAL DEFAULT NULL,
  "metadata_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_reward_reversals_uq_pos_reward_rev_key" ON "rateb_pos_reward_reversals" ("return_order_id", "reversal_kind", "reference_id_key");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_reward_reversals_idx_pos_reward_rev_orig" ON "rateb_pos_reward_reversals" ("company_id", "original_order_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_serial_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "serial_id" INTEGER DEFAULT NULL,
  "serial_no" TEXT NOT NULL,
  "inventory_id" INTEGER NOT NULL,
  "event_type" TEXT NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT DEFAULT NULL,
  "reference_type" TEXT DEFAULT NULL,
  "reference_id" INTEGER DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_serial_history_idx_pos_serial_hist_sn" ON "rateb_pos_serial_history" ("company_id", "serial_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_serial_history_idx_pos_serial_hist_ref" ON "rateb_pos_serial_history" ("company_id", "reference_type", "reference_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_sessions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "terminal_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "shift_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "started_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "ended_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_sessions_idx_pos_session_terminal" ON "rateb_pos_sessions" ("terminal_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_sessions_idx_pos_session_branch" ON "rateb_pos_sessions" ("company_id", "branch_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_settings" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "settings_json" TEXT NOT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_settings_uq_pos_settings_scope" ON "rateb_pos_settings" ("company_id", "branch_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_shifts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "terminal_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "shift_no" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "opened_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "closed_at" TEXT DEFAULT NULL,
  "opening_float" REAL NOT NULL DEFAULT 0.00,
  "closing_float" REAL DEFAULT NULL,
  "expected_cash" REAL DEFAULT NULL,
  "variance" REAL DEFAULT NULL,
  "last_x_report_at" TEXT DEFAULT NULL,
  "last_z_report_id" INTEGER DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_shifts_uq_pos_shift_no" ON "rateb_pos_shifts" ("company_id", "shift_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_shifts_idx_pos_shift_branch" ON "rateb_pos_shifts" ("company_id", "branch_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_shifts_idx_pos_shift_terminal" ON "rateb_pos_shifts" ("terminal_id", "status");

CREATE TABLE IF NOT EXISTS "rateb_pos_store_credit_accounts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "customer_id" INTEGER NOT NULL,
  "balance" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'active',
  "updated_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_store_credit_accounts_uq_pos_store_credit_customer" ON "rateb_pos_store_credit_accounts" ("company_id", "customer_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_store_credit_ledger" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "account_id" INTEGER NOT NULL,
  "order_id" INTEGER DEFAULT NULL,
  "refund_id" INTEGER DEFAULT NULL,
  "entry_type" TEXT NOT NULL,
  "amount" REAL NOT NULL,
  "balance_after" REAL NOT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_store_credit_ledger_idx_pos_sc_ledger_account" ON "rateb_pos_store_credit_ledger" ("account_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_store_credit_ledger_fk_pos_sc_ledger_company" ON "rateb_pos_store_credit_ledger" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_sync_conflicts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "queue_id" INTEGER NOT NULL,
  "idempotency_key" TEXT NOT NULL,
  "reason" TEXT NOT NULL DEFAULT 'server_newer',
  "client_payload" TEXT NOT NULL,
  "server_payload" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "resolved_by" INTEGER DEFAULT NULL,
  "resolved_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_pos_sync_conflicts_idx_pos_sync_conflict_status" ON "rateb_pos_sync_conflicts" ("company_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_sync_conflicts_idx_pos_sync_conflict_queue" ON "rateb_pos_sync_conflicts" ("queue_id");

CREATE TABLE IF NOT EXISTS "rateb_pos_sync_queue" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "terminal_id" INTEGER DEFAULT NULL,
  "idempotency_key" TEXT NOT NULL,
  "payload" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "version" INTEGER NOT NULL DEFAULT 1,
  "retry_count" INTEGER NOT NULL DEFAULT 0,
  "last_error" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "synced_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_sync_queue_uq_pos_sync_idem" ON "rateb_pos_sync_queue" ("company_id", "idempotency_key");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_sync_queue_idx_pos_sync_status" ON "rateb_pos_sync_queue" ("company_id", "status");

CREATE TABLE IF NOT EXISTS "rateb_pos_terminals" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "warehouse_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "device_meta" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_pos_terminals_uq_pos_terminal_code" ON "rateb_pos_terminals" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_terminals_idx_pos_terminal_branch" ON "rateb_pos_terminals" ("company_id", "branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_pos_terminals_idx_pos_terminal_wh" ON "rateb_pos_terminals" ("warehouse_id");

CREATE TABLE IF NOT EXISTS "rateb_product_categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "code" TEXT DEFAULT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description_en" TEXT DEFAULT NULL,
  "description_ar" TEXT DEFAULT NULL,
  "parent_id" INTEGER DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "is_visible" INTEGER NOT NULL DEFAULT 1,
  "icon" TEXT DEFAULT NULL,
  "image_path" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_product_categories_idx_pc_company" ON "rateb_product_categories" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_product_categories_idx_pc_parent" ON "rateb_product_categories" ("parent_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_product_categories_idx_pc_sort" ON "rateb_product_categories" ("company_id", "sort_order");

CREATE TABLE IF NOT EXISTS "rateb_project_activities" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "task_id" INTEGER DEFAULT NULL,
  "activity_type" TEXT NOT NULL DEFAULT 'note',
  "subject" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "activity_at" TEXT DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_activities_uq_prj_act_uuid" ON "rateb_project_activities" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_activities_idx_prj_act_project" ON "rateb_project_activities" ("company_id", "project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_activities_idx_prj_act_task" ON "rateb_project_activities" ("task_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_activities_fk_prj_act_project" ON "rateb_project_activities" ("project_id");

CREATE TABLE IF NOT EXISTS "rateb_project_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "related_type" TEXT NOT NULL,
  "related_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER NOT NULL,
  "role_label" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_assignments_uq_prj_asg_uuid" ON "rateb_project_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_assignments_idx_prj_asg_related" ON "rateb_project_assignments" ("company_id", "related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_assignments_idx_prj_asg_user" ON "rateb_project_assignments" ("assignee_user_id");

CREATE TABLE IF NOT EXISTS "rateb_project_budgets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "category" TEXT NOT NULL DEFAULT 'general',
  "planned_amount" REAL NOT NULL DEFAULT 0.00,
  "currency_code" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_budgets_uq_prj_bud_uuid" ON "rateb_project_budgets" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_budgets_idx_prj_bud_project" ON "rateb_project_budgets" ("company_id", "project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_budgets_fk_prj_bud_project" ON "rateb_project_budgets" ("project_id");

CREATE TABLE IF NOT EXISTS "rateb_project_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "task_id" INTEGER DEFAULT NULL,
  "body" TEXT NOT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_comments_uq_prj_cmt_uuid" ON "rateb_project_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_comments_idx_prj_cmt_project" ON "rateb_project_comments" ("company_id", "project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_comments_idx_prj_cmt_task" ON "rateb_project_comments" ("task_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_comments_fk_prj_cmt_project" ON "rateb_project_comments" ("project_id");

CREATE TABLE IF NOT EXISTS "rateb_project_costs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "budget_id" INTEGER DEFAULT NULL,
  "cost_date" TEXT NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "currency_code" TEXT DEFAULT NULL,
  "category" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'recorded',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_costs_uq_prj_cost_uuid" ON "rateb_project_costs" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_costs_idx_prj_cost_project" ON "rateb_project_costs" ("company_id", "project_id", "cost_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_costs_fk_prj_cost_project" ON "rateb_project_costs" ("project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_costs_fk_prj_cost_budget" ON "rateb_project_costs" ("budget_id");

CREATE TABLE IF NOT EXISTS "rateb_project_entity_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "tag_id" INTEGER NOT NULL,
  "related_type" TEXT NOT NULL,
  "related_id" INTEGER NOT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_entity_tags_uq_prj_etag" ON "rateb_project_entity_tags" ("company_id", "tag_id", "related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_entity_tags_idx_prj_etag_related" ON "rateb_project_entity_tags" ("related_type", "related_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_entity_tags_fk_prj_etag_tag" ON "rateb_project_entity_tags" ("tag_id");

CREATE TABLE IF NOT EXISTS "rateb_project_issues" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "task_id" INTEGER DEFAULT NULL,
  "issue_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "severity" TEXT NOT NULL DEFAULT 'medium',
  "status" TEXT NOT NULL DEFAULT 'open',
  "assignee_user_id" INTEGER DEFAULT NULL,
  "due_date" TEXT DEFAULT NULL,
  "resolved_at" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_issues_uq_prj_iss_uuid" ON "rateb_project_issues" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_issues_uq_prj_iss_no" ON "rateb_project_issues" ("project_id", "issue_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_issues_idx_prj_iss_project" ON "rateb_project_issues" ("company_id", "project_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_issues_fk_prj_iss_task" ON "rateb_project_issues" ("task_id");

CREATE TABLE IF NOT EXISTS "rateb_project_members" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "role_id" INTEGER DEFAULT NULL,
  "role_label" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_members_uq_prj_mem_uuid" ON "rateb_project_members" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_members_uq_prj_mem_user" ON "rateb_project_members" ("project_id", "user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_members_idx_prj_mem_company" ON "rateb_project_members" ("company_id", "project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_members_fk_prj_mem_role" ON "rateb_project_members" ("role_id");

CREATE TABLE IF NOT EXISTS "rateb_project_milestones" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "phase_id" INTEGER DEFAULT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "due_date" TEXT DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_milestones_uq_prj_ms_uuid" ON "rateb_project_milestones" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_milestones_idx_prj_ms_project" ON "rateb_project_milestones" ("company_id", "project_id", "due_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_milestones_fk_prj_ms_project" ON "rateb_project_milestones" ("project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_milestones_fk_prj_ms_phase" ON "rateb_project_milestones" ("phase_id");

CREATE TABLE IF NOT EXISTS "rateb_project_phases" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "start_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'planned',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_phases_uq_prj_phase_uuid" ON "rateb_project_phases" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_phases_uq_prj_phase_code" ON "rateb_project_phases" ("project_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_phases_idx_prj_phase_project" ON "rateb_project_phases" ("company_id", "project_id", "sort_order");

CREATE TABLE IF NOT EXISTS "rateb_project_resources" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "resource_type" TEXT NOT NULL DEFAULT 'user',
  "name" TEXT NOT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "allocation_percent" REAL DEFAULT NULL,
  "start_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "cost_rate" REAL DEFAULT NULL,
  "currency_code" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'planned',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_resources_uq_prj_res_uuid" ON "rateb_project_resources" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_resources_idx_prj_res_project" ON "rateb_project_resources" ("company_id", "project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_resources_fk_prj_res_project" ON "rateb_project_resources" ("project_id");

CREATE TABLE IF NOT EXISTS "rateb_project_risks" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "risk_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "probability" TEXT NOT NULL DEFAULT 'medium',
  "impact" TEXT NOT NULL DEFAULT 'medium',
  "status" TEXT NOT NULL DEFAULT 'identified',
  "owner_user_id" INTEGER DEFAULT NULL,
  "mitigation_plan" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_risks_uq_prj_risk_uuid" ON "rateb_project_risks" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_risks_uq_prj_risk_no" ON "rateb_project_risks" ("project_id", "risk_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_risks_idx_prj_risk_project" ON "rateb_project_risks" ("company_id", "project_id", "status");

CREATE TABLE IF NOT EXISTS "rateb_project_roles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_roles_uq_prj_role_uuid" ON "rateb_project_roles" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_roles_uq_prj_role_code" ON "rateb_project_roles" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_roles_idx_prj_role_company" ON "rateb_project_roles" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_project_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "project_id" INTEGER DEFAULT NULL,
  "task_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL DEFAULT 'project',
  "from_status" TEXT NOT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_project_status_history_idx_prj_sh_project" ON "rateb_project_status_history" ("company_id", "project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_status_history_idx_prj_sh_task" ON "rateb_project_status_history" ("task_id");

CREATE TABLE IF NOT EXISTS "rateb_project_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_tags_uq_prj_tag_uuid" ON "rateb_project_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_tags_uq_prj_tag_code" ON "rateb_project_tags" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_tags_idx_prj_tag_company" ON "rateb_project_tags" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_project_tasks" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "phase_id" INTEGER DEFAULT NULL,
  "milestone_id" INTEGER DEFAULT NULL,
  "parent_task_id" INTEGER DEFAULT NULL,
  "task_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'new',
  "priority" TEXT NOT NULL DEFAULT 'normal',
  "assignee_user_id" INTEGER DEFAULT NULL,
  "start_date" TEXT DEFAULT NULL,
  "due_date" TEXT DEFAULT NULL,
  "estimated_hours" REAL DEFAULT NULL,
  "actual_hours" REAL DEFAULT NULL,
  "percent_complete" REAL NOT NULL DEFAULT 0.00,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "version" INTEGER NOT NULL DEFAULT 1,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_tasks_uq_prj_task_uuid" ON "rateb_project_tasks" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_tasks_uq_prj_task_no" ON "rateb_project_tasks" ("project_id", "task_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_tasks_idx_prj_task_project" ON "rateb_project_tasks" ("company_id", "project_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_tasks_idx_prj_task_workflow" ON "rateb_project_tasks" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_tasks_idx_prj_task_parent" ON "rateb_project_tasks" ("parent_task_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_tasks_idx_prj_task_assignee" ON "rateb_project_tasks" ("assignee_user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_tasks_fk_prj_task_phase" ON "rateb_project_tasks" ("phase_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_tasks_fk_prj_task_ms" ON "rateb_project_tasks" ("milestone_id");

CREATE TABLE IF NOT EXISTS "rateb_project_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER DEFAULT NULL,
  "task_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "related_type" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_timeline_uq_prj_tl_uuid" ON "rateb_project_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_timeline_idx_prj_tl_project" ON "rateb_project_timeline" ("company_id", "project_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_timeline_idx_prj_tl_task" ON "rateb_project_timeline" ("task_id");

CREATE TABLE IF NOT EXISTS "rateb_project_timesheets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_id" INTEGER NOT NULL,
  "task_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER NOT NULL,
  "work_date" TEXT NOT NULL,
  "hours" REAL NOT NULL DEFAULT 0.00,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_project_timesheets_uq_prj_ts_uuid" ON "rateb_project_timesheets" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_timesheets_idx_prj_ts_project" ON "rateb_project_timesheets" ("company_id", "project_id", "work_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_timesheets_idx_prj_ts_user" ON "rateb_project_timesheets" ("user_id", "work_date");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_timesheets_fk_prj_ts_project" ON "rateb_project_timesheets" ("project_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_project_timesheets_fk_prj_ts_task" ON "rateb_project_timesheets" ("task_id");

CREATE TABLE IF NOT EXISTS "rateb_projects" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "project_no" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "customer_id" INTEGER DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "priority" TEXT NOT NULL DEFAULT 'normal',
  "start_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "planned_start" TEXT DEFAULT NULL,
  "planned_end" TEXT DEFAULT NULL,
  "percent_complete" REAL NOT NULL DEFAULT 0.00,
  "currency_code" TEXT DEFAULT NULL,
  "budget_amount" REAL DEFAULT NULL,
  "cost_center_id" INTEGER DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_projects_uq_prj_uuid" ON "rateb_projects" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_projects_uq_prj_no" ON "rateb_projects" ("company_id", "project_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_projects_idx_prj_company" ON "rateb_projects" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_projects_idx_prj_branch" ON "rateb_projects" ("company_id", "branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_projects_idx_prj_workflow" ON "rateb_projects" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_projects_idx_prj_owner" ON "rateb_projects" ("owner_user_id");

CREATE TABLE IF NOT EXISTS "rateb_purchase_invoices" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "purchase_order_id" INTEGER NOT NULL,
  "supplier_id" INTEGER DEFAULT NULL,
  "invoice_no" TEXT NOT NULL,
  "invoice_date" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "currency" TEXT NOT NULL DEFAULT 'SAR',
  "line_subtotal" REAL NOT NULL DEFAULT 0.00,
  "discount_amount" REAL NOT NULL DEFAULT 0.00,
  "tax_amount" REAL NOT NULL DEFAULT 0.00,
  "shipping_amount" REAL NOT NULL DEFAULT 0.00,
  "customs_clearance_amount" REAL NOT NULL DEFAULT 0.00,
  "total_amount" REAL NOT NULL DEFAULT 0.00,
  "customs_declaration_no" TEXT DEFAULT NULL,
  "customs_clearance_date" TEXT DEFAULT NULL,
  "customs_broker_id" INTEGER DEFAULT NULL,
  "customs_clearance_status" TEXT NOT NULL DEFAULT '',
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_purchase_invoices_uq_pi_company_no" ON "rateb_purchase_invoices" ("company_id", "invoice_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_invoices_idx_pi_po" ON "rateb_purchase_invoices" ("purchase_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_invoices_idx_pi_supplier" ON "rateb_purchase_invoices" ("supplier_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_invoices_idx_pi_customs_broker" ON "rateb_purchase_invoices" ("customs_broker_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_invoices_idx_pi_branch" ON "rateb_purchase_invoices" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_purchase_items" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "purchase_order_id" INTEGER NOT NULL,
  "inventory_id" INTEGER DEFAULT NULL,
  "item_name" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "sku" TEXT DEFAULT NULL,
  "quantity" REAL NOT NULL DEFAULT 1.000,
  "delivered_qty" REAL NOT NULL DEFAULT 0.000,
  "invoiced_qty" REAL NOT NULL DEFAULT 0.000,
  "unit" TEXT NOT NULL DEFAULT 'unit',
  "unit_price" REAL NOT NULL DEFAULT 0.00,
  "tax_name" TEXT DEFAULT 'Local Sales 0%',
  "tax_rate" REAL NOT NULL DEFAULT 0.00,
  "excluding_tax" INTEGER NOT NULL DEFAULT 1,
  "total_price" REAL NOT NULL DEFAULT 0.00,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_items_idx_pi_company" ON "rateb_purchase_items" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_items_fk_pi_po" ON "rateb_purchase_items" ("purchase_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_items_idx_pi_inventory" ON "rateb_purchase_items" ("inventory_id");

CREATE TABLE IF NOT EXISTS "rateb_purchase_orders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "order_no" TEXT NOT NULL,
  "title" TEXT NOT NULL DEFAULT '',
  "barcode" TEXT DEFAULT NULL,
  "qr_code" TEXT DEFAULT NULL,
  "supplier_id" INTEGER DEFAULT NULL,
  "cost_center_id" INTEGER DEFAULT NULL,
  "warehouse_id" INTEGER DEFAULT NULL,
  "purchase_request_id" INTEGER DEFAULT NULL,
  "quotation_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "order_date" TEXT NOT NULL,
  "expected_date" TEXT DEFAULT NULL,
  "subtotal" REAL NOT NULL DEFAULT 0.00,
  "tax_amount" REAL NOT NULL DEFAULT 0.00,
  "total_amount" REAL NOT NULL DEFAULT 0.00,
  "currency" TEXT NOT NULL DEFAULT 'SAR',
  "discount_amount" REAL NOT NULL DEFAULT 0.00,
  "shipping_amount" REAL NOT NULL DEFAULT 0.00,
  "customs_clearance_amount" REAL NOT NULL DEFAULT 0.00,
  "customs_declaration_no" TEXT DEFAULT NULL,
  "customs_clearance_date" TEXT DEFAULT NULL,
  "customs_broker_id" INTEGER DEFAULT NULL,
  "customs_clearance_status" TEXT NOT NULL DEFAULT '',
  "notes" TEXT DEFAULT NULL,
  "notes_history" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_purchase_orders_uq_po_company_no" ON "rateb_purchase_orders" ("company_id", "order_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_orders_idx_po_company" ON "rateb_purchase_orders" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_orders_fk_po_supplier" ON "rateb_purchase_orders" ("supplier_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_orders_idx_po_cost_center" ON "rateb_purchase_orders" ("cost_center_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_orders_idx_po_barcode" ON "rateb_purchase_orders" ("barcode");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_orders_idx_po_warehouse" ON "rateb_purchase_orders" ("warehouse_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_orders_idx_po_customs_broker" ON "rateb_purchase_orders" ("customs_broker_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_orders_idx_po_branch" ON "rateb_purchase_orders" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_purchase_request_items" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "purchase_request_id" INTEGER NOT NULL,
  "inventory_id" INTEGER DEFAULT NULL,
  "item_name" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "needed_by" TEXT DEFAULT NULL,
  "supplier_id" INTEGER DEFAULT NULL,
  "warehouse_id" INTEGER DEFAULT NULL,
  "account_id" INTEGER DEFAULT NULL,
  "attachment_path" TEXT DEFAULT NULL,
  "attachment_name" TEXT DEFAULT NULL,
  "sku" TEXT DEFAULT NULL,
  "quantity" REAL NOT NULL DEFAULT 1.000,
  "unit" TEXT NOT NULL DEFAULT 'unit',
  "unit_price" REAL NOT NULL DEFAULT 0.00,
  "tax_name" TEXT DEFAULT 'Local Sales 0%',
  "tax_rate" REAL NOT NULL DEFAULT 0.00,
  "excluding_tax" INTEGER NOT NULL DEFAULT 1,
  "total_price" REAL NOT NULL DEFAULT 0.00,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_request_items_idx_pri_company" ON "rateb_purchase_request_items" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_request_items_fk_pri_pr" ON "rateb_purchase_request_items" ("purchase_request_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_request_items_idx_pri_inventory" ON "rateb_purchase_request_items" ("inventory_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_request_items_idx_pri_supplier" ON "rateb_purchase_request_items" ("supplier_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_request_items_idx_pri_warehouse" ON "rateb_purchase_request_items" ("warehouse_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_request_items_idx_pri_account" ON "rateb_purchase_request_items" ("account_id");

CREATE TABLE IF NOT EXISTS "rateb_purchase_requests" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "request_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "department" TEXT DEFAULT NULL,
  "expected_date" TEXT DEFAULT NULL,
  "priority" TEXT NOT NULL DEFAULT 'medium',
  "status" TEXT NOT NULL DEFAULT 'draft',
  "requested_by" INTEGER DEFAULT NULL,
  "approved_by" INTEGER DEFAULT NULL,
  "total_estimated" REAL NOT NULL DEFAULT 0.00,
  "currency" TEXT NOT NULL DEFAULT 'SAR',
  "notes" TEXT DEFAULT NULL,
  "notes_history" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_purchase_requests_uq_pr_company_no" ON "rateb_purchase_requests" ("company_id", "request_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_requests_idx_pr_company" ON "rateb_purchase_requests" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_purchase_requests_idx_pr_branch" ON "rateb_purchase_requests" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER DEFAULT NULL,
  "role_label" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_assignments_uq_qms_asg_uuid" ON "rateb_qms_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_assignments_idx_qms_asg_entity" ON "rateb_qms_assignments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_audit_findings" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "audit_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "finding_type" TEXT NOT NULL DEFAULT 'observation',
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_audit_findings_uq_qms_af_uuid" ON "rateb_qms_audit_findings" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_audit_findings_idx_qms_af_aud" ON "rateb_qms_audit_findings" ("company_id", "audit_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_audit_findings_fk_qms_af_aud" ON "rateb_qms_audit_findings" ("audit_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_audits" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "program_id" INTEGER DEFAULT NULL,
  "standard_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "audit_type" TEXT NOT NULL DEFAULT 'internal',
  "planned_start" TEXT DEFAULT NULL,
  "planned_end" TEXT DEFAULT NULL,
  "lead_auditor_user_id" INTEGER DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'planned',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_audits_uq_qms_aud_uuid" ON "rateb_qms_audits" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_audits_uq_qms_aud_code" ON "rateb_qms_audits" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_audits_idx_qms_aud_wf" ON "rateb_qms_audits" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_audits_fk_qms_aud_prog" ON "rateb_qms_audits" ("program_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_audits_fk_qms_aud_std" ON "rateb_qms_audits" ("standard_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_checklist_items" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "checklist_id" INTEGER NOT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "item_code" TEXT DEFAULT NULL,
  "item_text" TEXT NOT NULL,
  "is_required" INTEGER NOT NULL DEFAULT 1,
  "pass_criteria" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_checklist_items_uq_qms_chki_uuid" ON "rateb_qms_checklist_items" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_checklist_items_idx_qms_chki_chk" ON "rateb_qms_checklist_items" ("company_id", "checklist_id", "sort_order");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_checklist_items_fk_qms_chki_chk" ON "rateb_qms_checklist_items" ("checklist_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_checklists" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "plan_id" INTEGER DEFAULT NULL,
  "standard_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "checklist_type" TEXT NOT NULL DEFAULT 'inspection',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_checklists_uq_qms_chk_uuid" ON "rateb_qms_checklists" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_checklists_uq_qms_chk_code" ON "rateb_qms_checklists" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_checklists_idx_qms_chk_company" ON "rateb_qms_checklists" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_checklists_fk_qms_chk_plan" ON "rateb_qms_checklists" ("plan_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_checklists_fk_qms_chk_std" ON "rateb_qms_checklists" ("standard_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_comments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "comment_text" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_comments_uq_qms_cmt_uuid" ON "rateb_qms_comments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_comments_idx_qms_cmt_entity" ON "rateb_qms_comments" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_complaints" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "complainant" TEXT DEFAULT NULL,
  "complaint_date" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "severity" TEXT NOT NULL DEFAULT 'medium',
  "status" TEXT NOT NULL DEFAULT 'open',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_complaints_uq_qms_cmp_uuid" ON "rateb_qms_complaints" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_complaints_uq_qms_cmp_code" ON "rateb_qms_complaints" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_complaints_idx_qms_cmp_company" ON "rateb_qms_complaints" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_qms_corrective_actions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "nonconformity_id" INTEGER DEFAULT NULL,
  "root_cause_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "assignee_user_id" INTEGER DEFAULT NULL,
  "due_date" TEXT DEFAULT NULL,
  "verified_at" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_corrective_actions_uq_qms_ca_uuid" ON "rateb_qms_corrective_actions" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_corrective_actions_uq_qms_ca_code" ON "rateb_qms_corrective_actions" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_corrective_actions_idx_qms_ca_wf" ON "rateb_qms_corrective_actions" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_corrective_actions_fk_qms_ca_nc" ON "rateb_qms_corrective_actions" ("nonconformity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_corrective_actions_fk_qms_ca_rc" ON "rateb_qms_corrective_actions" ("root_cause_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_defects" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "inspection_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "severity" TEXT NOT NULL DEFAULT 'medium',
  "defect_type" TEXT DEFAULT NULL,
  "quantity" REAL NOT NULL DEFAULT 1.00,
  "status" TEXT NOT NULL DEFAULT 'open',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_defects_uq_qms_def_uuid" ON "rateb_qms_defects" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_defects_uq_qms_def_code" ON "rateb_qms_defects" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_defects_idx_qms_def_insp" ON "rateb_qms_defects" ("company_id", "inspection_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_defects_fk_qms_def_insp" ON "rateb_qms_defects" ("inspection_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_documents_meta" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "doc_type" TEXT NOT NULL DEFAULT 'attachment',
  "title" TEXT NOT NULL,
  "file_name" TEXT DEFAULT NULL,
  "mime_type" TEXT DEFAULT NULL,
  "file_size" INTEGER DEFAULT NULL,
  "storage_key" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_documents_meta_uq_qms_doc_uuid" ON "rateb_qms_documents_meta" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_documents_meta_idx_qms_doc_entity" ON "rateb_qms_documents_meta" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_inspections" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "plan_id" INTEGER DEFAULT NULL,
  "checklist_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "title_ar" TEXT DEFAULT NULL,
  "planned_at" TEXT DEFAULT NULL,
  "started_at" TEXT DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "inspector_user_id" INTEGER DEFAULT NULL,
  "mfg_quality_check_id" INTEGER DEFAULT NULL,
  "eam_inspection_id" INTEGER DEFAULT NULL,
  "result_summary" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'planned',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_inspections_uq_qms_insp_uuid" ON "rateb_qms_inspections" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_inspections_uq_qms_insp_code" ON "rateb_qms_inspections" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_inspections_idx_qms_insp_wf" ON "rateb_qms_inspections" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_inspections_fk_qms_insp_plan" ON "rateb_qms_inspections" ("plan_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_inspections_fk_qms_insp_chk" ON "rateb_qms_inspections" ("checklist_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_nonconformities" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "inspection_id" INTEGER DEFAULT NULL,
  "defect_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "severity" TEXT NOT NULL DEFAULT 'medium',
  "detected_at" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'open',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_nonconformities_uq_qms_nc_uuid" ON "rateb_qms_nonconformities" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_nonconformities_uq_qms_nc_code" ON "rateb_qms_nonconformities" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_nonconformities_idx_qms_nc_company" ON "rateb_qms_nonconformities" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_nonconformities_fk_qms_nc_insp" ON "rateb_qms_nonconformities" ("inspection_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_nonconformities_fk_qms_nc_def" ON "rateb_qms_nonconformities" ("defect_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_plans" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "program_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "scope_text" TEXT DEFAULT NULL,
  "effective_from" TEXT DEFAULT NULL,
  "effective_to" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_plans_uq_qms_plan_uuid" ON "rateb_qms_plans" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_plans_uq_qms_plan_code" ON "rateb_qms_plans" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_plans_idx_qms_plan_company" ON "rateb_qms_plans" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_plans_fk_qms_plan_prog" ON "rateb_qms_plans" ("program_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_preventive_actions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "nonconformity_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "assignee_user_id" INTEGER DEFAULT NULL,
  "due_date" TEXT DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_preventive_actions_uq_qms_pa_uuid" ON "rateb_qms_preventive_actions" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_preventive_actions_uq_qms_pa_code" ON "rateb_qms_preventive_actions" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_preventive_actions_idx_qms_pa_wf" ON "rateb_qms_preventive_actions" ("company_id", "workflow_status", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_preventive_actions_fk_qms_pa_nc" ON "rateb_qms_preventive_actions" ("nonconformity_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_programs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "owner_user_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_programs_uq_qms_prog_uuid" ON "rateb_qms_programs" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_programs_uq_qms_prog_code" ON "rateb_qms_programs" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_programs_idx_qms_prog_company" ON "rateb_qms_programs" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_qms_results" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "inspection_id" INTEGER NOT NULL,
  "checklist_item_id" INTEGER DEFAULT NULL,
  "result_value" TEXT DEFAULT NULL,
  "result_status" TEXT NOT NULL DEFAULT 'pending',
  "measured_value" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_results_uq_qms_res_uuid" ON "rateb_qms_results" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_results_idx_qms_res_insp" ON "rateb_qms_results" ("company_id", "inspection_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_results_fk_qms_res_insp" ON "rateb_qms_results" ("inspection_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_root_causes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "nonconformity_id" INTEGER NOT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "analysis_method" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_root_causes_uq_qms_rc_uuid" ON "rateb_qms_root_causes" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_root_causes_idx_qms_rc_nc" ON "rateb_qms_root_causes" ("company_id", "nonconformity_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_root_causes_fk_qms_rc_nc" ON "rateb_qms_root_causes" ("nonconformity_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_standards" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "name_ar" TEXT DEFAULT NULL,
  "standard_ref" TEXT DEFAULT NULL,
  "revision" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_standards_uq_qms_std_uuid" ON "rateb_qms_standards" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_standards_uq_qms_std_code" ON "rateb_qms_standards" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_standards_idx_qms_std_company" ON "rateb_qms_standards" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_qms_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "entity_type" TEXT NOT NULL,
  "entity_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_status_history_uq_qms_sh_uuid" ON "rateb_qms_status_history" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_status_history_idx_qms_sh_entity" ON "rateb_qms_status_history" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_supplier_quality" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "supplier_name" TEXT NOT NULL,
  "legacy_supplier_id" INTEGER DEFAULT NULL,
  "eproc_profile_id" INTEGER DEFAULT NULL,
  "score" REAL DEFAULT NULL,
  "rating" TEXT DEFAULT NULL,
  "last_review_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_supplier_quality_uq_qms_sq_uuid" ON "rateb_qms_supplier_quality" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_supplier_quality_uq_qms_sq_code" ON "rateb_qms_supplier_quality" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_supplier_quality_idx_qms_sq_company" ON "rateb_qms_supplier_quality" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_qms_tags" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "color" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_tags_uq_qms_tag_uuid" ON "rateb_qms_tags" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_tags_uq_qms_tag_code" ON "rateb_qms_tags" ("company_id", "code");

CREATE TABLE IF NOT EXISTS "rateb_qms_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "entity_type" TEXT DEFAULT NULL,
  "entity_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_timeline_uq_qms_tl_uuid" ON "rateb_qms_timeline" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_timeline_idx_qms_tl_company" ON "rateb_qms_timeline" ("company_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_timeline_idx_qms_tl_entity" ON "rateb_qms_timeline" ("company_id", "entity_type", "entity_id");

CREATE TABLE IF NOT EXISTS "rateb_qms_training" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "training_date" TEXT DEFAULT NULL,
  "audience" TEXT DEFAULT NULL,
  "hrm_training_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'planned',
  "notes" TEXT DEFAULT NULL,
  "version" INTEGER NOT NULL DEFAULT 1,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_training_uq_qms_trn_uuid" ON "rateb_qms_training" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_qms_training_uq_qms_trn_code" ON "rateb_qms_training" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_qms_training_idx_qms_trn_company" ON "rateb_qms_training" ("company_id", "deleted_at");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_activities" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "activity_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "meta_json" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_activities_idx_rec_act_cand" ON "rateb_recruitment_activities" ("company_id", "candidate_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_activities_fk_rec_act_cand" ON "rateb_recruitment_activities" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_agencies" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "code" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "contact_name" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "country_code" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_agencies_uq_rec_agency_uuid" ON "rateb_recruitment_agencies" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_agencies_uq_rec_agency_code" ON "rateb_recruitment_agencies" ("company_id", "code");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_agencies_idx_rec_agency_company" ON "rateb_recruitment_agencies" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_agencies_idx_rec_agency_branch" ON "rateb_recruitment_agencies" ("company_id", "branch_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_assignments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "assignee_user_id" INTEGER NOT NULL,
  "role_label" TEXT NOT NULL DEFAULT 'recruiter',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_assignments_uq_rec_asg_uuid" ON "rateb_recruitment_assignments" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_assignments_idx_rec_asg_cand" ON "rateb_recruitment_assignments" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_assignments_idx_rec_asg_user" ON "rateb_recruitment_assignments" ("assignee_user_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_assignments_fk_rec_asg_cand" ON "rateb_recruitment_assignments" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_candidate_languages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "language_id" INTEGER NOT NULL,
  "proficiency" TEXT NOT NULL DEFAULT 'basic',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidate_languages_uq_rec_cand_lang" ON "rateb_recruitment_candidate_languages" ("candidate_id", "language_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidate_languages_fk_rec_cl_lang" ON "rateb_recruitment_candidate_languages" ("language_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_candidate_skills" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "skill_id" INTEGER NOT NULL,
  "level" TEXT NOT NULL DEFAULT 'basic',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidate_skills_uq_rec_cand_skill" ON "rateb_recruitment_candidate_skills" ("candidate_id", "skill_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidate_skills_fk_rec_cs_skill" ON "rateb_recruitment_candidate_skills" ("skill_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_candidates" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "agency_id" INTEGER DEFAULT NULL,
  "candidate_no" TEXT NOT NULL,
  "full_name" TEXT NOT NULL,
  "full_name_ar" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "nationality" TEXT DEFAULT NULL,
  "gender" TEXT NOT NULL DEFAULT 'unspecified',
  "date_of_birth" TEXT DEFAULT NULL,
  "national_id" TEXT DEFAULT NULL,
  "job_title_target" TEXT DEFAULT NULL,
  "source" TEXT DEFAULT NULL,
  "recruiter_user_id" INTEGER DEFAULT NULL,
  "workflow_status" TEXT NOT NULL DEFAULT 'draft',
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidates_uq_rec_cand_uuid" ON "rateb_recruitment_candidates" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidates_uq_rec_cand_no" ON "rateb_recruitment_candidates" ("company_id", "candidate_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidates_idx_rec_cand_company" ON "rateb_recruitment_candidates" ("company_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidates_idx_rec_cand_status" ON "rateb_recruitment_candidates" ("company_id", "workflow_status");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidates_idx_rec_cand_agency" ON "rateb_recruitment_candidates" ("agency_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidates_idx_rec_cand_recruiter" ON "rateb_recruitment_candidates" ("recruiter_user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_candidates_idx_rec_cand_branch" ON "rateb_recruitment_candidates" ("company_id", "branch_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_contracts" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "contract_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "start_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "salary" REAL NOT NULL DEFAULT 0.00,
  "currency" TEXT NOT NULL DEFAULT 'SAR',
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_contracts_uq_rec_ctr_uuid" ON "rateb_recruitment_contracts" ("public_uuid");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_contracts_uq_rec_ctr_no" ON "rateb_recruitment_contracts" ("company_id", "contract_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_contracts_idx_rec_ctr_cand" ON "rateb_recruitment_contracts" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_contracts_fk_rec_ctr_cand" ON "rateb_recruitment_contracts" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_educations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "institution" TEXT NOT NULL,
  "degree" TEXT DEFAULT NULL,
  "field_of_study" TEXT DEFAULT NULL,
  "country_code" TEXT DEFAULT NULL,
  "start_year" INTEGER DEFAULT NULL,
  "end_year" INTEGER DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_educations_idx_rec_edu_cand" ON "rateb_recruitment_educations" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_educations_fk_rec_edu_cand" ON "rateb_recruitment_educations" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_experiences" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "employer_name" TEXT NOT NULL,
  "job_title" TEXT DEFAULT NULL,
  "country_code" TEXT DEFAULT NULL,
  "start_date" TEXT DEFAULT NULL,
  "end_date" TEXT DEFAULT NULL,
  "is_current" INTEGER NOT NULL DEFAULT 0,
  "description" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_experiences_idx_rec_exp_cand" ON "rateb_recruitment_experiences" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_experiences_fk_rec_exp_cand" ON "rateb_recruitment_experiences" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_interviews" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "candidate_id" INTEGER NOT NULL,
  "interviewer_user_id" INTEGER DEFAULT NULL,
  "scheduled_at" TEXT DEFAULT NULL,
  "location" TEXT DEFAULT NULL,
  "mode" TEXT NOT NULL DEFAULT 'in_person',
  "result" TEXT NOT NULL DEFAULT 'pending',
  "score" REAL DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_interviews_uq_rec_int_uuid" ON "rateb_recruitment_interviews" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_interviews_idx_rec_int_cand" ON "rateb_recruitment_interviews" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_interviews_idx_rec_int_sched" ON "rateb_recruitment_interviews" ("company_id", "scheduled_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_interviews_fk_rec_int_cand" ON "rateb_recruitment_interviews" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_languages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "code" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_languages_uq_rec_lang" ON "rateb_recruitment_languages" ("company_id", "name");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_medicals" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "clinic_name" TEXT DEFAULT NULL,
  "exam_date" TEXT DEFAULT NULL,
  "result" TEXT NOT NULL DEFAULT 'pending',
  "expiry_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'scheduled',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_medicals_uq_rec_med_uuid" ON "rateb_recruitment_medicals" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_medicals_idx_rec_med_cand" ON "rateb_recruitment_medicals" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_medicals_fk_rec_med_cand" ON "rateb_recruitment_medicals" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_notes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "body" TEXT NOT NULL,
  "visibility" TEXT NOT NULL DEFAULT 'internal',
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_notes_idx_rec_notes_cand" ON "rateb_recruitment_notes" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_notes_fk_rec_notes_cand" ON "rateb_recruitment_notes" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_passports" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "passport_no" TEXT NOT NULL,
  "nationality" TEXT DEFAULT NULL,
  "issue_date" TEXT DEFAULT NULL,
  "expiry_date" TEXT DEFAULT NULL,
  "issue_place" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_passports_uq_rec_pass_uuid" ON "rateb_recruitment_passports" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_passports_idx_rec_pass_cand" ON "rateb_recruitment_passports" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_passports_fk_rec_pass_cand" ON "rateb_recruitment_passports" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_skills" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_skills_uq_rec_skill" ON "rateb_recruitment_skills" ("company_id", "name");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_status_history" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "from_status" TEXT DEFAULT NULL,
  "to_status" TEXT NOT NULL,
  "reason" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_status_history_idx_rec_hist_cand" ON "rateb_recruitment_status_history" ("company_id", "candidate_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_status_history_fk_rec_hist_cand" ON "rateb_recruitment_status_history" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "event_type" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "body" TEXT DEFAULT NULL,
  "related_entity" TEXT DEFAULT NULL,
  "related_id" INTEGER DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_timeline_idx_rec_tl_cand" ON "rateb_recruitment_timeline" ("company_id", "candidate_id", "created_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_timeline_fk_rec_tl_cand" ON "rateb_recruitment_timeline" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_recruitment_visas" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "public_uuid" TEXT NOT NULL,
  "company_id" INTEGER NOT NULL,
  "candidate_id" INTEGER NOT NULL,
  "visa_no" TEXT DEFAULT NULL,
  "visa_type" TEXT DEFAULT NULL,
  "destination_country" TEXT DEFAULT NULL,
  "issue_date" TEXT DEFAULT NULL,
  "expiry_date" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "updated_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "deleted_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_recruitment_visas_uq_rec_visa_uuid" ON "rateb_recruitment_visas" ("public_uuid");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_visas_idx_rec_visa_cand" ON "rateb_recruitment_visas" ("company_id", "candidate_id", "deleted_at");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_visas_idx_rec_visa_status" ON "rateb_recruitment_visas" ("company_id", "status");
CREATE INDEX IF NOT EXISTS "idx_rateb_recruitment_visas_fk_rec_visa_cand" ON "rateb_recruitment_visas" ("candidate_id");

CREATE TABLE IF NOT EXISTS "rateb_remember_tokens" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "user_id" INTEGER NOT NULL,
  "token_hash" TEXT NOT NULL,
  "device_name" TEXT DEFAULT NULL,
  "expires_at" TEXT NOT NULL,
  "last_used_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "revoked_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_remember_tokens_uq_remember_hash" ON "rateb_remember_tokens" ("token_hash");
CREATE INDEX IF NOT EXISTS "idx_rateb_remember_tokens_idx_remember_user" ON "rateb_remember_tokens" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_remember_tokens_idx_remember_expires" ON "rateb_remember_tokens" ("expires_at");

CREATE TABLE IF NOT EXISTS "rateb_rfq" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "rfq_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "deadline" TEXT DEFAULT NULL,
  "description" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_rfq_uq_rfq_company_no" ON "rateb_rfq" ("company_id", "rfq_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_rfq_idx_rfq_branch" ON "rateb_rfq" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_role_permissions" (
  "role_id" INTEGER NOT NULL,
  "permission_id" INTEGER NOT NULL,
  PRIMARY KEY ("role_id", "permission_id")
);

CREATE INDEX IF NOT EXISTS "idx_rateb_role_permissions_fk_rp_permission" ON "rateb_role_permissions" ("permission_id");

CREATE TABLE IF NOT EXISTS "rateb_roles" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "name" TEXT NOT NULL,
  "slug" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "is_system" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_roles_uq_roles_company_slug" ON "rateb_roles" ("company_id", "slug");
CREATE INDEX IF NOT EXISTS "idx_rateb_roles_idx_roles_company" ON "rateb_roles" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_sms_templates" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL,
  "body" TEXT NOT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_sms_templates_slug" ON "rateb_sms_templates" ("slug");

CREATE TABLE IF NOT EXISTS "rateb_stock_movements" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "movement_no" TEXT DEFAULT NULL,
  "inventory_id" INTEGER NOT NULL,
  "warehouse_id" INTEGER DEFAULT NULL,
  "movement_type" TEXT NOT NULL,
  "quantity" REAL NOT NULL,
  "reference_type" TEXT DEFAULT NULL,
  "reference_id" INTEGER DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_stock_movements_uq_sm_company_movement_no" ON "rateb_stock_movements" ("company_id", "movement_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_stock_movements_fk_sm_inventory" ON "rateb_stock_movements" ("inventory_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_stock_movements_idx_sm_branch" ON "rateb_stock_movements" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_subscriptions" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "plan_id" INTEGER NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'trial',
  "billing_cycle" TEXT NOT NULL DEFAULT 'monthly',
  "amount" REAL NOT NULL DEFAULT 0.00,
  "starts_at" TEXT NOT NULL,
  "ends_at" TEXT DEFAULT NULL,
  "auto_renew" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_subscriptions_idx_subscriptions_company" ON "rateb_subscriptions" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_subscriptions_fk_subscriptions_plan" ON "rateb_subscriptions" ("plan_id");

CREATE TABLE IF NOT EXISTS "rateb_supplier_classifications" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "slug" TEXT NOT NULL,
  "color" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_supplier_classifications_uq_sc_company_slug" ON "rateb_supplier_classifications" ("company_id", "slug");

CREATE TABLE IF NOT EXISTS "rateb_supplier_comm_timeline" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "comm_id" INTEGER NOT NULL,
  "event_type" TEXT NOT NULL,
  "summary" TEXT NOT NULL,
  "details" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_comm_timeline_idx_sct_comm" ON "rateb_supplier_comm_timeline" ("comm_id", "id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_comm_timeline_idx_sct_company" ON "rateb_supplier_comm_timeline" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_supplier_communications" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "supplier_id" INTEGER NOT NULL,
  "channel" TEXT NOT NULL DEFAULT 'email',
  "subject" TEXT DEFAULT NULL,
  "comm_date" TEXT DEFAULT NULL,
  "comm_time" TEXT DEFAULT NULL,
  "details" TEXT DEFAULT NULL,
  "body" TEXT NOT NULL,
  "responsible_name" TEXT DEFAULT NULL,
  "supplier_contact" TEXT DEFAULT NULL,
  "supplier_phone" TEXT DEFAULT NULL,
  "supplier_email" TEXT DEFAULT NULL,
  "comm_status" TEXT NOT NULL DEFAULT 'new',
  "send_status" TEXT NOT NULL DEFAULT 'not_sent',
  "sent_at" TEXT DEFAULT NULL,
  "response_rating" TEXT DEFAULT NULL,
  "response_notes" TEXT DEFAULT NULL,
  "follow_up_date" TEXT DEFAULT NULL,
  "follow_up_priority" TEXT NOT NULL DEFAULT 'medium',
  "follow_up_reminded_at" TEXT DEFAULT NULL,
  "no_response_notified_at" TEXT DEFAULT NULL,
  "purchase_order_id" INTEGER DEFAULT NULL,
  "rfq_id" INTEGER DEFAULT NULL,
  "is_archived" INTEGER NOT NULL DEFAULT 0,
  "archived_at" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_communications_idx_scomm_supplier" ON "rateb_supplier_communications" ("supplier_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_communications_fk_scomm_company" ON "rateb_supplier_communications" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_communications_idx_sc_branch" ON "rateb_supplier_communications" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_supplier_evaluations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "evaluation_no" TEXT DEFAULT NULL,
  "supplier_id" INTEGER NOT NULL,
  "evaluated_by" INTEGER DEFAULT NULL,
  "evaluator_name" TEXT DEFAULT NULL,
  "evaluation_date" TEXT NOT NULL,
  "period_start" TEXT DEFAULT NULL,
  "period_end" TEXT DEFAULT NULL,
  "quality_score" INTEGER NOT NULL DEFAULT 0,
  "delivery_score" INTEGER NOT NULL DEFAULT 0,
  "price_score" INTEGER NOT NULL DEFAULT 0,
  "service_score" INTEGER NOT NULL DEFAULT 0,
  "overall_score" REAL NOT NULL DEFAULT 0.00,
  "score_percent" REAL NOT NULL DEFAULT 0.00,
  "rating_tier" TEXT DEFAULT NULL,
  "comments" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'published',
  "manager_approval" TEXT NOT NULL DEFAULT 'pending',
  "approved_by" INTEGER DEFAULT NULL,
  "approved_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_supplier_evaluations_uq_se_company_eval_no" ON "rateb_supplier_evaluations" ("company_id", "evaluation_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_evaluations_idx_eval_company" ON "rateb_supplier_evaluations" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_evaluations_idx_eval_supplier" ON "rateb_supplier_evaluations" ("supplier_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_evaluations_idx_se_branch" ON "rateb_supplier_evaluations" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_supplier_payments" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "supplier_id" INTEGER DEFAULT NULL,
  "purchase_order_id" INTEGER DEFAULT NULL,
  "payment_no" TEXT NOT NULL,
  "payment_date" TEXT NOT NULL,
  "amount" REAL NOT NULL,
  "bank_account_id" INTEGER DEFAULT NULL,
  "payment_method" TEXT DEFAULT 'bank',
  "reference_no" TEXT DEFAULT NULL,
  "journal_entry_id" INTEGER DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'posted',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "posted_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "invoice_id" INTEGER DEFAULT NULL,
  "due_date" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_supplier_payments_uq_sp_company_no" ON "rateb_supplier_payments" ("company_id", "payment_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_payments_idx_sp_company" ON "rateb_supplier_payments" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_payments_idx_sp_po" ON "rateb_supplier_payments" ("purchase_order_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_payments_idx_sp_invoice" ON "rateb_supplier_payments" ("invoice_id");

CREATE TABLE IF NOT EXISTS "rateb_supplier_quotations" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "rfq_id" INTEGER NOT NULL,
  "supplier_id" INTEGER NOT NULL,
  "quotation_no" TEXT NOT NULL,
  "amount" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'submitted',
  "valid_until" TEXT DEFAULT NULL,
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_quotations_fk_sq_company" ON "rateb_supplier_quotations" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_quotations_fk_sq_rfq" ON "rateb_supplier_quotations" ("rfq_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_quotations_fk_sq_supplier" ON "rateb_supplier_quotations" ("supplier_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_supplier_quotations_idx_sq_branch" ON "rateb_supplier_quotations" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_suppliers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "name" TEXT NOT NULL,
  "code" TEXT DEFAULT NULL,
  "email" TEXT DEFAULT NULL,
  "phone" TEXT DEFAULT NULL,
  "address" TEXT DEFAULT NULL,
  "rating" REAL DEFAULT 0.00,
  "classification_id" INTEGER DEFAULT NULL,
  "performance_kpi" REAL DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_suppliers_idx_suppliers_company" ON "rateb_suppliers" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_suppliers_idx_sup_branch" ON "rateb_suppliers" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_support_tickets" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "user_id" INTEGER DEFAULT NULL,
  "ticket_no" TEXT NOT NULL,
  "subject" TEXT NOT NULL,
  "priority" TEXT NOT NULL DEFAULT 'medium',
  "status" TEXT NOT NULL DEFAULT 'open',
  "message" TEXT NOT NULL,
  "assigned_to" INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_support_tickets_ticket_no" ON "rateb_support_tickets" ("ticket_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_support_tickets_idx_ticket_branch" ON "rateb_support_tickets" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_system_settings" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "setting_key" TEXT NOT NULL,
  "setting_value" TEXT DEFAULT NULL,
  "setting_group" TEXT NOT NULL DEFAULT 'general',
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_system_settings_setting_key" ON "rateb_system_settings" ("setting_key");

CREATE TABLE IF NOT EXISTS "rateb_tender_comparisons" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "tender_id" INTEGER NOT NULL,
  "supplier_id" INTEGER NOT NULL,
  "quotation_id" INTEGER DEFAULT NULL,
  "rank_order" INTEGER NOT NULL DEFAULT 0,
  "score" REAL NOT NULL DEFAULT 0.00,
  "notes" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_tender_comparisons_idx_tc_company" ON "rateb_tender_comparisons" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_tender_comparisons_fk_tc_tender" ON "rateb_tender_comparisons" ("tender_id");

CREATE TABLE IF NOT EXISTS "rateb_tenders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "tender_no" TEXT NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT DEFAULT NULL,
  "publish_date" TEXT DEFAULT NULL,
  "closing_date" TEXT DEFAULT NULL,
  "estimated_value" REAL NOT NULL DEFAULT 0.00,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_tenders_uq_tenders_company_no" ON "rateb_tenders" ("company_id", "tender_no");
CREATE INDEX IF NOT EXISTS "idx_rateb_tenders_idx_tender_branch" ON "rateb_tenders" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_two_factor_backup_codes" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "user_id" INTEGER NOT NULL,
  "code_hash" TEXT NOT NULL,
  "used_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_two_factor_backup_codes_idx_2fa_backup_user" ON "rateb_two_factor_backup_codes" ("user_id");

CREATE TABLE IF NOT EXISTS "rateb_user_branches" (
  "user_id" INTEGER NOT NULL,
  "branch_id" INTEGER NOT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY ("user_id", "branch_id")
);

CREATE INDEX IF NOT EXISTS "idx_rateb_user_branches_idx_ub_user" ON "rateb_user_branches" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_user_branches_idx_ub_branch" ON "rateb_user_branches" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_user_roles" (
  "user_id" INTEGER NOT NULL,
  "role_id" INTEGER NOT NULL,
  PRIMARY KEY ("user_id", "role_id")
);

CREATE INDEX IF NOT EXISTS "idx_rateb_user_roles_fk_ur_role" ON "rateb_user_roles" ("role_id");

CREATE TABLE IF NOT EXISTS "rateb_users" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER DEFAULT NULL,
  "name" TEXT NOT NULL,
  "email" TEXT NOT NULL,
  "login_barcode" TEXT DEFAULT NULL,
  "password" TEXT NOT NULL,
  "phone" TEXT DEFAULT NULL,
  "avatar_path" TEXT DEFAULT NULL,
  "is_super_admin" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'active',
  "two_factor_secret" TEXT DEFAULT NULL,
  "two_factor_enabled" INTEGER NOT NULL DEFAULT 0,
  "locale" TEXT NOT NULL DEFAULT 'en',
  "last_login_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL,
  "failed_attempts" INTEGER NOT NULL DEFAULT 0,
  "locked_until" TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_users_email" ON "rateb_users" ("email");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_users_uq_users_login_barcode" ON "rateb_users" ("login_barcode");
CREATE INDEX IF NOT EXISTS "idx_rateb_users_idx_users_company" ON "rateb_users" ("company_id");

CREATE TABLE IF NOT EXISTS "rateb_warehouse_transfers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "source_branch_id" INTEGER DEFAULT NULL,
  "dest_branch_id" INTEGER DEFAULT NULL,
  "transfer_no" TEXT NOT NULL,
  "source_warehouse_id" INTEGER NOT NULL,
  "destination_warehouse_id" INTEGER NOT NULL,
  "inventory_id" INTEGER NOT NULL,
  "quantity" REAL NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "notes" TEXT DEFAULT NULL,
  "created_by" INTEGER DEFAULT NULL,
  "approved_by" INTEGER DEFAULT NULL,
  "completed_at" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS "idx_rateb_warehouse_transfers_idx_wt_company" ON "rateb_warehouse_transfers" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_warehouse_transfers_idx_wt_status" ON "rateb_warehouse_transfers" ("status");
CREATE INDEX IF NOT EXISTS "idx_rateb_warehouse_transfers_idx_wt_src_branch" ON "rateb_warehouse_transfers" ("source_branch_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_warehouse_transfers_idx_wt_dest_branch" ON "rateb_warehouse_transfers" ("dest_branch_id");

CREATE TABLE IF NOT EXISTS "rateb_warehouses" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "company_id" INTEGER NOT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "name" TEXT NOT NULL,
  "code" TEXT DEFAULT NULL,
  "location" TEXT DEFAULT NULL,
  "manager_name" TEXT DEFAULT NULL,
  "status" TEXT NOT NULL DEFAULT 'active',
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS "idx_rateb_warehouses_fk_wh_company" ON "rateb_warehouses" ("company_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_warehouses_idx_wh_branch" ON "rateb_warehouses" ("branch_id");

CREATE TABLE IF NOT EXISTS "rateb_webauthn_credentials" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "user_id" INTEGER NOT NULL,
  "company_id" INTEGER DEFAULT NULL,
  "branch_id" INTEGER DEFAULT NULL,
  "credential_id" BLOB NOT NULL,
  "public_key" TEXT NOT NULL,
  "sign_count" INTEGER NOT NULL DEFAULT 0,
  "last_used" TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_rateb_webauthn_credentials_uq_webauthn_credential" ON "rateb_webauthn_credentials" ("credential_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_webauthn_credentials_idx_webauthn_user" ON "rateb_webauthn_credentials" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_webauthn_credentials_idx_webauthn_company_user" ON "rateb_webauthn_credentials" ("company_id", "user_id");
CREATE INDEX IF NOT EXISTS "idx_rateb_webauthn_credentials_idx_webauthn_company_branch" ON "rateb_webauthn_credentials" ("company_id", "branch_id");

PRAGMA foreign_keys=ON;
