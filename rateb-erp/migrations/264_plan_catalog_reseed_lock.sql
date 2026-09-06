-- Remember that an admin owns the plans table so catchup must not re-insert deleted packages.
CREATE TABLE IF NOT EXISTS rateb_plan_catalog_state (
    k VARCHAR(32) NOT NULL PRIMARY KEY,
    v VARCHAR(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
