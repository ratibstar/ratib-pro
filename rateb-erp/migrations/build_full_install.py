#!/usr/bin/env python3
"""Build 000_full_install_admin_rateb-erp.sql from migration parts."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "000_full_install_admin_rateb-erp.sql"

schema = (ROOT / "001_initial_schema.sql").read_text(encoding="utf-8")
schema = schema.replace(
    """CREATE TABLE IF NOT EXISTS rateb_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,""",
    """CREATE TABLE IF NOT EXISTS rateb_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,""",
)
schema = schema.replace(
    """    module VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,""",
    """    module VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    description_ar VARCHAR(255) NULL,""",
    1,
)
schema = re.sub(r"\nSET FOREIGN_KEY_CHECKS = 1;\s*$", "", schema.strip())

extra_tables = (ROOT / "003_permissions_ar_evaluations.sql").read_text(encoding="utf-8")
m = re.search(
    r"(CREATE TABLE IF NOT EXISTS rateb_supplier_evaluations[\s\S]+?ENGINE=InnoDB[^;]+;)",
    extra_tables,
)
supplier_eval = m.group(1) if m else ""

extra_acc = (ROOT / "004_accounting_access.sql").read_text(encoding="utf-8")
acc_tables = []
for tbl in ("rateb_chart_of_accounts", "rateb_journal_entries", "rateb_journal_lines"):
    m = re.search(rf"(CREATE TABLE IF NOT EXISTS {tbl}[\s\S]+?ENGINE=InnoDB[^;]+;)", extra_acc)
    if m:
        acc_tables.append(m.group(1))

drops = [
    "rateb_journal_lines",
    "rateb_journal_entries",
    "rateb_chart_of_accounts",
    "rateb_supplier_evaluations",
    "rateb_user_roles",
    "rateb_role_permissions",
    "rateb_api_tokens",
    "rateb_stock_movements",
    "rateb_purchase_items",
    "rateb_supplier_quotations",
    "rateb_medical_devices",
    "rateb_purchase_orders",
    "rateb_purchase_requests",
    "rateb_subscriptions",
    "rateb_payments",
    "rateb_invoice_lines",
    "rateb_invoices",
    "rateb_inventory",
    "rateb_warehouses",
    "rateb_rfq",
    "rateb_contracts",
    "rateb_tenders",
    "rateb_assets",
    "rateb_suppliers",
    "rateb_users",
    "rateb_roles",
    "rateb_permissions",
    "rateb_companies",
    "rateb_plans",
    "rateb_notifications",
    "rateb_audit_logs",
    "rateb_login_activity",
    "rateb_email_templates",
    "rateb_sms_templates",
    "rateb_support_tickets",
    "rateb_system_settings",
]

header = """-- =============================================================================
-- RATEB ERP — Full database install for admin_rateb-erp
-- =============================================================================
-- Database: admin_rateb-erp
-- Import via cPanel phpMyAdmin → select database → Import → choose this file
--
-- WARNING: Drops all existing rateb_* tables before recreating (fresh install).
-- Default login after import: admin@rateb.sa / password
-- =============================================================================

USE `admin_rateb-erp`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

"""

drop_sql = "\n".join(f"DROP TABLE IF EXISTS `{t}`;" for t in drops) + "\n\n"

seed = (ROOT / "000_full_install_seed.sql").read_text(encoding="utf-8")

body = (
    header
    + drop_sql
    + schema
    + "\n\n"
    + supplier_eval
    + "\n\n"
    + "\n\n".join(acc_tables)
    + "\n\n"
    + seed
)
OUT.write_text(body, encoding="utf-8")
print(f"Written {OUT} ({len(body)} bytes)")
