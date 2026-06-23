-- 026: normalize rcc_agents.status (fixes agent_provision SQLSTATE 1265 on some servers)

UPDATE rcc_agents
SET status = 'active'
WHERE status IS NULL
   OR TRIM(COALESCE(status, '')) = ''
   OR status NOT IN ('active', 'inactive');

ALTER TABLE rcc_agents
    MODIFY COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';
