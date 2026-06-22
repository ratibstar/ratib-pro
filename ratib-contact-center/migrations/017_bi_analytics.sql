-- RATIB Contact Center — 017 BI analytics (Phase 10E)

CREATE TABLE IF NOT EXISTS rcc_metrics_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    metric_date DATE NOT NULL,
    metric_key VARCHAR(64) NOT NULL,
    metric_value DECIMAL(18,4) NOT NULL DEFAULT 0,
    dimensions_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_metrics_daily (tenant_id, metric_date, metric_key),
    KEY idx_rcc_metrics_daily_date (tenant_id, metric_date),
    CONSTRAINT fk_rcc_metrics_daily_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_metrics_hourly (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    metric_hour DATETIME NOT NULL,
    metric_key VARCHAR(64) NOT NULL,
    metric_value DECIMAL(18,4) NOT NULL DEFAULT 0,
    dimensions_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_metrics_hourly (tenant_id, metric_hour, metric_key),
    KEY idx_rcc_metrics_hourly_hour (tenant_id, metric_hour),
    CONSTRAINT fk_rcc_metrics_hourly_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_kpis (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    kpi_key VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    name_ar VARCHAR(128) NULL,
    target_value DECIMAL(18,4) NULL,
    warning_below DECIMAL(18,4) NULL,
    critical_below DECIMAL(18,4) NULL,
    unit VARCHAR(16) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_kpis_key (tenant_id, kpi_key),
    CONSTRAINT fk_rcc_kpis_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_dashboard_widgets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    dashboard_key VARCHAR(64) NOT NULL,
    widget_key VARCHAR(64) NOT NULL,
    title VARCHAR(128) NOT NULL,
    title_ar VARCHAR(128) NULL,
    widget_type VARCHAR(32) NOT NULL,
    config_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_dashboard_widget (tenant_id, dashboard_key, widget_key),
    CONSTRAINT fk_rcc_dashboard_widgets_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_kpis (tenant_id, kpi_key, name, name_ar, target_value, warning_below, critical_below, unit)
SELECT t.id, 'sla_pct', 'SLA Compliance %', 'نسبة الالتزام باتفاقية الخدمة', 90, 85, 75, '%'
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_kpis (tenant_id, kpi_key, name, name_ar, target_value, warning_below, critical_below, unit)
SELECT t.id, 'occupancy_pct', 'Agent Occupancy %', 'نسبة إشغال الوكلاء', 80, 70, 60, '%'
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_kpis (tenant_id, kpi_key, name, name_ar, target_value, warning_below, critical_below, unit)
SELECT t.id, 'ticket_backlog', 'Open Ticket Backlog', 'تذاكر مفتوحة', 50, 100, 200, 'count'
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_dashboard_widgets (tenant_id, dashboard_key, widget_key, title, title_ar, widget_type, sort_order, config_json)
SELECT t.id, 'executive', 'live_calls', 'Live Calls', 'مكالمات حية', 'kpi', 10, '{"source":"calls.live"}'
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_dashboard_widgets (tenant_id, dashboard_key, widget_key, title, title_ar, widget_type, sort_order, config_json)
SELECT t.id, 'executive', 'sla_risk', 'SLA Risk', 'مخاطر اتفاقية الخدمة', 'kpi', 20, '{"source":"sla.risk"}'
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_dashboard_widgets (tenant_id, dashboard_key, widget_key, title, title_ar, widget_type, sort_order, config_json)
SELECT t.id, 'executive', 'ticket_backlog', 'Ticket Backlog', 'تراكم التذاكر', 'kpi', 30, '{"source":"tickets.open"}'
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.analytics.view', 'View Analytics', 'analytics'),
('rcc.analytics.export', 'Export Analytics', 'analytics'),
('rcc.analytics.admin', 'Manage KPIs & Widgets', 'analytics'),
('rcc.command.view', 'Executive Command Center', 'command');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.analytics.%' OR slug = 'rcc.command.view';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.analytics.view', 'rcc.command.view');
