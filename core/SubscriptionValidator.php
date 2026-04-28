<?php
/**
 * EN: Handles core framework/runtime behavior in `core/SubscriptionValidator.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/SubscriptionValidator.php`.
 */
/**
 * SubscriptionValidator - Check tenant subscription before access
 *
 * Call at start of each request (after TenantResolver).
 * Redirects to suspended page if expired.
 */
if (defined('SUBSCRIPTION_VALIDATOR_LOADED')) {
    return;
}

function validateTenantSubscription(): bool
{
    if (!defined('TENANT_ID')) {
        return true; // No tenant context
    }

    require_once __DIR__ . '/Database.php';
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT status, subscription_expiry FROM countries WHERE id = ? LIMIT 1");
    $stmt->execute([TENANT_ID]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    if ($row['status'] === 'suspended' || $row['status'] === 'inactive') {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>Tenant Suspended</h1><p>Contact administrator.</p>';
        exit;
    }

    $today = date('Y-m-d');
    if (!empty($row['subscription_expiry']) && $row['subscription_expiry'] < $today) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>Subscription Expired</h1><p>Renew to continue.</p>';
        exit;
    }

    return true;
}

define('SUBSCRIPTION_VALIDATOR_LOADED', true);
