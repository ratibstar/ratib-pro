<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/helpers/get_tenant_db.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/helpers/get_tenant_db.php`.
 */
/**
 * Tenant DB + app default DB accessors (request lifecycle).
 *
 * Place this file in includes/helpers/ and load it from includes/config.php
 * after core/bootstrap.php so Database + $GLOBALS['conn'] exist when available.
 *
 * - getTenantDB(): tenant PDO when domain middleware set REQUEST_TENANT_ID, else app default (mysqli|PDO).
 * - ratib_app_default_db_connection(): always the legacy app connection (never TenantDatabaseManager).
 */

if (!function_exists('ratib_app_default_db_connection')) {
    /**
     * @return PDO|mysqli
     */
    function ratib_app_default_db_connection()
    {
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }
        if (!class_exists('Database', false)) {
            require_once __DIR__ . '/../../api/core/Database.php';
        }
        return Database::getInstance()->getConnection();
    }
}

if (!function_exists('getTenantDB')) {
    /**
     * Connection for business data: tenant DB when context exists, otherwise existing app DB.
     *
     * @return PDO|mysqli
     */
    function getTenantDB()
    {
        if (!class_exists('TenantExecutionContext', false)) {
            require_once __DIR__ . '/../../core/TenantExecutionContext.php';
        }

        // Preserve system/admin behavior: system context uses existing default DB.
        if (TenantExecutionContext::isInitialized() && TenantExecutionContext::isSystemContext()) {
            return ratib_app_default_db_connection();
        }

        if (!class_exists('TenantDatabaseManager', false)) {
            require_once __DIR__ . '/../../api/core/TenantDatabaseManager.php';
        }

        return TenantDatabaseManager::pdoForCurrentContext();
    }
}

if (!function_exists('getCurrentTenantId')) {
    /**
     * Strict tenant id accessor for query isolation.
     *
     * @throws RuntimeException when tenant context is missing.
     */
    function getCurrentTenantId(): int
    {
        if (!class_exists('TenantExecutionContext', false)) {
            require_once __DIR__ . '/../../core/TenantExecutionContext.php';
        }
        if (!TenantExecutionContext::isInitialized()) {
            throw new RuntimeException('TenantExecutionContext is not initialized.');
        }
        if (TenantExecutionContext::isSystemContext()) {
            throw new RuntimeException('Missing tenant context: tenant endpoint called in system context.');
        }
        $tid = TenantExecutionContext::getTenantId();
        if ($tid === null || $tid <= 0) {
            throw new RuntimeException('Missing tenant context: tenant_id is required.');
        }
        return (int) $tid;
    }
}
