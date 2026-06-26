<?php
declare(strict_types=1);

/**
 * Safe QA manifest resolver (read-only DB lookup by exact QA slug/email/code).
 *
 * Auth: X-Rateb-Migrate-Token (same as run-migrations.php).
 *
 * POST JSON body:
 *   { "type": "company|user|role|branch", "slug": "...", "email": "...", "code": "...", "company_id": 0 }
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

define('RATEB_ROOT', str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)));

$tokenOk = false;
require_once RATEB_ROOT . '/app/Core/HealthProbeAuth.php';
if (\Rateb\App\Core\HealthProbeAuth::verifyRequest()) {
    $tokenOk = true;
}

$sessionOk = false;
if (!$tokenOk) {
    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    if (\Rateb\App\Core\Auth::check() && (int) (\Rateb\App\Core\SessionManager::get('rateb_is_super_admin') ?? 0) === 1) {
        $sessionOk = true;
    }
}

if (!$tokenOk && !$sessionOk) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST JSON required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = (string) file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$sessionOk) {
    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
}
require_once RATEB_ROOT . '/bin/QaManifestResolver.php';

$type = strtolower(trim((string) ($body['type'] ?? '')));
$resolver = new QaManifestResolver(\Rateb\App\Core\Database::connection());

try {
    $result = match ($type) {
        'company' => $resolver->resolveCompanyBySlug(trim((string) ($body['slug'] ?? ''))),
        'user' => $resolver->resolveUserByEmail(trim((string) ($body['email'] ?? ''))),
        'role' => $resolver->resolveRoleBySlug(trim((string) ($body['slug'] ?? ''))),
        'branch' => $resolver->resolveBranchByCode(
            (int) ($body['company_id'] ?? 0),
            trim((string) ($body['code'] ?? ''))
        ),
        'subscription' => $resolver->resolveSubscriptionByCompanyId((int) ($body['company_id'] ?? 0)),
        default => ['ok' => false, 'error' => 'unknown_type'],
    };
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error'], JSON_UNESCAPED_UNICODE);
}
