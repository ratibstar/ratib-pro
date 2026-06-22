-- RATIB Contact Center — 016 call recordings (Phase 10D)

CREATE TABLE IF NOT EXISTS rcc_recordings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL,
    call_id INT UNSIGNED NULL,
    conversation_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    agent_id INT UNSIGNED NULL,
    ticket_id INT UNSIGNED NULL,
    channel VARCHAR(32) NOT NULL DEFAULT 'voice',
    direction ENUM('inbound','outbound','internal') NOT NULL DEFAULT 'inbound',
    caller_number VARCHAR(40) NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    file_path VARCHAR(512) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    mime_type VARCHAR(64) NOT NULL DEFAULT 'audio/wav',
    asterisk_uniqueid VARCHAR(64) NULL,
    indexed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_recordings_uuid (uuid),
    KEY idx_rcc_recordings_tenant (tenant_id, created_at),
    KEY idx_rcc_recordings_call (tenant_id, call_id),
    KEY idx_rcc_recordings_contact (tenant_id, contact_id),
    KEY idx_rcc_recordings_agent (tenant_id, agent_id),
    CONSTRAINT fk_rcc_recordings_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_recording_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    recording_id INT UNSIGNED NOT NULL,
    tag VARCHAR(64) NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_recording_tag (tenant_id, recording_id, tag),
    CONSTRAINT fk_rcc_recording_tags_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_recording_tags_rec FOREIGN KEY (recording_id) REFERENCES rcc_recordings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_recording_reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    recording_id INT UNSIGNED NOT NULL,
    qa_review_id INT UNSIGNED NULL,
    reviewer_user_id INT UNSIGNED NULL,
    notes TEXT NULL,
    rating TINYINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_recording_reviews_rec (tenant_id, recording_id),
    CONSTRAINT fk_rcc_recording_reviews_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_recording_reviews_rec FOREIGN KEY (recording_id) REFERENCES rcc_recordings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.recordings.view', 'View Recordings', 'recordings'),
('rcc.recordings.play', 'Play Recordings', 'recordings'),
('rcc.recordings.download', 'Download Recordings', 'recordings'),
('rcc.recordings.manage', 'Manage Recordings', 'recordings');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.recordings.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN (
    'rcc.recordings.view', 'rcc.recordings.play', 'rcc.recordings.download'
);

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 3, id FROM rcc_permissions WHERE slug IN ('rcc.recordings.view', 'rcc.recordings.play');
