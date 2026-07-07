<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Core\Database;

function phase28_integration_db(): ?PDO
{
    static $checked = false;
    static $pdo = null;

    if ($checked) {
        return $pdo;
    }

    $checked = true;
    if (!catalog_integration_enabled()) {
        return null;
    }

    try {
        if (!Database::ping(false)) {
            return null;
        }
        $pdo = Database::writeConnection();
    } catch (Throwable) {
        $pdo = null;
    }

    return $pdo;
}

/**
 * @return array{uuid: string, id: int, lock_version: int, version_number: int, status: string}|null
 */
function phase28_find_product(PDO $pdo, string $uuid): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, uuid, status, lock_version, version_number FROM products WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute(['uuid' => $uuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function phase28_count_versions(PDO $pdo, int $productId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM product_versions WHERE product_id = :id');
    $stmt->execute(['id' => $productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['c'] ?? 0);
}

function phase28_count_audit(PDO $pdo, string $entityUuid, string $action): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM audit_events WHERE entity_uuid = :uuid AND action = :action'
    );
    $stmt->execute(['uuid' => $entityUuid, 'action' => $action]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['c'] ?? 0);
}

function phase28_count_workflow_history(PDO $pdo, string $productUuid, ?string $action = null): int
{
    if ($action === null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM product_workflow_history WHERE product_uuid = :uuid');
        $stmt->execute(['uuid' => $productUuid]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c FROM product_workflow_history WHERE product_uuid = :uuid AND action = :action'
        );
        $stmt->execute(['uuid' => $productUuid, 'action' => $action]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['c'] ?? 0);
}

function phase28_count_search_index_queue(PDO $pdo, string $productUuid): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM search_index_queue
         WHERE entity_type = "product" AND entity_uuid = :uuid AND status = "pending"'
    );
    $stmt->execute(['uuid' => $productUuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['c'] ?? 0);
}

function phase28_count_job_queue_reindex(PDO $pdo, string $productUuid): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM job_queue
         WHERE job_type = "search_reindex" AND idempotency_key = :idempotency_key'
    );
    $stmt->execute(['idempotency_key' => 'search_reindex:' . $productUuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['c'] ?? 0);
}

function phase28_count_completeness_scores(PDO $pdo, int $productId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM product_completeness_scores WHERE product_id = :id');
    $stmt->execute(['id' => $productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['c'] ?? 0);
}

/**
 * @return array{uuid: string, id: int, lock_version: int, version_number: int}|null
 */
function phase28_pick_approved_product(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        "SELECT id, uuid, lock_version, version_number FROM products
         WHERE status = 'approved' AND deleted_at IS NULL
         ORDER BY id DESC LIMIT 1"
    );
    $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    return is_array($row) ? $row : null;
}

/**
 * @return array{uuid: string, id: int, lock_version: int, version_number: int}
 */
function phase28_ensure_approved_product(PDO $pdo): array
{
    $product = phase28_pick_approved_product($pdo);
    if ($product !== null) {
        $full = phase28_find_product($pdo, (string) $product['uuid']);
        if ($full !== null) {
            $full['_original_status'] = (string) $full['status'];
            if ((string) $full['status'] !== 'approved') {
                $pdo->prepare("UPDATE products SET status = 'approved' WHERE uuid = :uuid")->execute(['uuid' => $full['uuid']]);
                $full['status'] = 'approved';
            }

            return $full;
        }
    }

    $stmt = $pdo->query(
        'SELECT id, uuid, lock_version, version_number, status FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
    );
    $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if (!is_array($row)) {
        throw new RuntimeException('No product available for integration test setup');
    }

    $originalStatus = (string) $row['status'];
    $uuid = (string) $row['uuid'];
    $pdo->prepare("UPDATE products SET status = 'approved' WHERE uuid = :uuid")->execute(['uuid' => $uuid]);
    $approved = phase28_find_product($pdo, $uuid);
    if ($approved === null) {
        throw new RuntimeException('Failed to promote product to approved');
    }

    $approved['_original_status'] = $originalStatus;

    return $approved;
}

/**
 * @param array<string, mixed> $product
 */
function phase28_restore_product_status(PDO $pdo, array $product): void
{
    if (!isset($product['uuid'])) {
        return;
    }

    $status = isset($product['_original_status']) ? (string) $product['_original_status'] : null;
    if ($status === null) {
        return;
    }

    $pdo->prepare('UPDATE products SET status = :status WHERE uuid = :uuid')->execute([
        'status' => $status,
        'uuid' => (string) $product['uuid'],
    ]);
}

/**
 * @return array{uuid: string, product_uuid: string, current_version: int, lock_version?: int, version_number?: int}|null
 */
function phase28_pick_approved_change_request(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        "SELECT cr.uuid, p.uuid AS product_uuid, cr.current_version, p.lock_version, p.version_number
         FROM change_requests cr
         INNER JOIN products p ON p.id = cr.product_id AND p.deleted_at IS NULL
         WHERE cr.status = 'approved' AND cr.deleted_at IS NULL
         ORDER BY cr.id DESC LIMIT 1"
    );
    $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    return is_array($row) ? $row : null;
}

/**
 * @return array{uuid: string, version_number: int, snapshot: array<string, mixed>}|null
 */
function phase28_pick_version_snapshot(PDO $pdo, string $productUuid): ?array
{
    $stmt = $pdo->prepare(
        'SELECT pv.uuid, pv.version_number, pv.snapshot_json
         FROM product_versions pv
         INNER JOIN products p ON p.id = pv.product_id
         WHERE p.uuid = :uuid
         ORDER BY pv.version_number DESC
         LIMIT 1'
    );
    $stmt->execute(['uuid' => $productUuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    $snapshot = json_decode((string) ($row['snapshot_json'] ?? '{}'), true);

    return [
        'uuid' => (string) $row['uuid'],
        'version_number' => (int) $row['version_number'],
        'snapshot' => is_array($snapshot) ? $snapshot : [],
    ];
}
