<?php

declare(strict_types=1);

/**
 * Phase 11 — ERP Offline Auth unlock tests.
 *
 * Run: php offline/tests/run-erp-offline-auth-tests.php
 */

use Rateb\App\Core\SessionManager;
use Rateb\App\Offline\Services\ErpOfflineAuthPolicy;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class ErpOfflineAuthPhase11Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();
        $this->testFlagsDefaultOff();
        $this->testAuthRequiresReadCache();
        $this->testSchemaV2AuthVault();
        $this->testNoNewIndexedDbDatabase();
        $this->testPolicyDeniesSuperAdmin();
        $this->testPolicyRequiresSession();
        $this->testAdapterSecuritySource();
        $this->testQueueFlushReauthGate();
        $this->testLayoutAuthGated();
        $this->testPosAndTier1Untouched();
        $this->testPhase10ShellIntact();
        $this->testSdkExposesAuth();
        $this->testRoutesAdditive();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_AUTH_UNLOCK',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function enable(bool $master, bool $read, bool $auth): void
    {
        $this->clearEnv();
        if ($master) {
            putenv('RATEB_OFFLINE_ENABLED=1');
            $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        }
        if ($read) {
            putenv('RATEB_OFFLINE_READ_CACHE=1');
            $_ENV['RATEB_OFFLINE_READ_CACHE'] = '1';
        }
        if ($auth) {
            putenv('RATEB_OFFLINE_AUTH_UNLOCK=1');
            $_ENV['RATEB_OFFLINE_AUTH_UNLOCK'] = '1';
        }
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.auth.unlock') === false
            && $svc->isAuthUnlockEnabled() === false;
        $this->record('auth.unlock default OFF', $ok, $ok ? 'ok' : 'on');
    }

    private function testAuthRequiresReadCache(): void
    {
        $this->enable(true, false, true);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.auth.unlock') === true
            && $svc->isAuthUnlockEnabled() === false;
        $this->record('auth.unlock requires read_cache', $ok, $ok ? 'ok' : 'enabled without read_cache');
        $this->enable(true, true, true);
        $svc2 = new OfflineFeatureFlagService();
        $ok2 = $svc2->isAuthUnlockEnabled() === true;
        $this->record('auth.unlock ON with master+read_cache', $ok2, $ok2 ? 'ok' : 'false');
        $this->clearEnv();
    }

    private function testSchemaV2AuthVault(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($schema, "DB_VERSION = 2")
            && str_contains($schema, 'AUTH_VAULT')
            && str_contains($schema, "'auth_vault'")
            && str_contains($bundle, 'auth_vault')
            && str_contains($bundle, 'DB_VERSION = 2')
            && !str_contains($schema, 'rateb_erp_auth_lock');
        $this->record('IDB v2 auth_vault on rateb_erp_offline', $ok, $ok ? 'ok' : 'fail');
    }

    private function testNoNewIndexedDbDatabase(): void
    {
        $auth = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = !str_contains($auth, "indexedDB.open('rateb_erp_auth_lock'")
            && !str_contains($auth, 'rateb_pos_auth_lock')
            && str_contains($auth, 'AUTH_VAULT')
            && str_contains($auth, 'RatebOfflineSchema');
        $this->record('no duplicate IndexedDB database', $ok, $ok ? 'ok' : 'duplicate db');
    }

    private function testPolicyDeniesSuperAdmin(): void
    {
        SessionManager::set('rateb_is_super_admin', true);
        SessionManager::set('rateb_user_id', 1);
        SessionManager::set('rateb_company_id', 1);
        $this->enable(true, true, true);
        $r = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        $ok = ($r['ok'] ?? true) === false && ($r['error'] ?? '') === 'super_admin_denied';
        $this->record('policy denies super-admin', $ok, $ok ? 'ok' : json_encode($r));
        SessionManager::forget('rateb_is_super_admin');
        SessionManager::forget('rateb_user_id');
        SessionManager::forget('rateb_company_id');
        $this->clearEnv();
    }

    private function testPolicyRequiresSession(): void
    {
        SessionManager::forget('rateb_is_super_admin');
        SessionManager::forget('rateb_user_id');
        SessionManager::forget('rateb_company_id');
        $this->enable(true, true, true);
        $r = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        $ok = ($r['ok'] ?? true) === false && ($r['error'] ?? '') === 'online_session_required';
        $this->record('policy requires online session', $ok, $ok ? 'ok' : json_encode($r));
        $this->clearEnv();
    }

    private function testAdapterSecuritySource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = str_contains($src, 'PBKDF2')
            && str_contains($src, 'pin_hash')
            && str_contains($src, 'pin_salt')
            && str_contains($src, 'webauthn_credential_id')
            && str_contains($src, 'company_id')
            && str_contains($src, 'tenant_mismatch')
            && str_contains($src, 'super_admin_denied')
            && str_contains($src, 'device_unknown')
            && !preg_match('/password\s*[:=]/i', $src)
            && !str_contains($src, 'document.write')
            && str_contains($src, 'textContent');
        $this->record('adapter: PBKDF2 only, no password fields, safe DOM', $ok, $ok ? 'ok' : 'fail');
    }

    private function testQueueFlushReauthGate(): void
    {
        $q = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($q, 'session_reauth_required')
            && str_contains($q, 'RatebOfflineAuthLock')
            && str_contains($bundle, 'session_reauth_required');
        $this->record('queue flush blocked on sessionNeedsReauth', $ok, $ok ? 'ok' : 'missing gate');
    }

    private function testLayoutAuthGated(): void
    {
        $layout = (string) file_get_contents(RATEB_ROOT . '/views/layouts/main.php');
        $ok = str_contains($layout, 'isAuthUnlockEnabled')
            && str_contains($layout, 'erp-auth-bootstrap.js')
            && str_contains($layout, '$ratebOfflineAuthUnlock');
        $this->record('layout auth JS gated', $ok, $ok ? 'ok' : 'ungated');
    }

    private function testPosAndTier1Untouched(): void
    {
        $posLock = RATEB_ROOT . '/public/assets/pos/js/pos-auth-lock.js';
        $queue = RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php';
        $replay = RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php';
        $ok = is_file($posLock)
            && str_contains((string) file_get_contents($posLock), 'rateb_pos_auth_lock')
            && is_file($queue) && is_file($replay);
        $engine = new OfflineReplayEngine();
        $skip = $engine->replay(['module' => 'x', 'action' => 'y']);
        $ok = $ok && ($skip['status'] ?? '') === 'skipped';
        $this->record('POS auth + Tier-1 replay untouched', $ok, $ok ? 'ok' : 'changed');
    }

    private function testPhase10ShellIntact(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $shell = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/shell-adapter.js');
        $ok = str_contains($sw, 'inlineOfflineShellResponse')
            && !preg_match('/self\.clients\.claim\s*\(/', $sw)
            && str_contains($shell, 'erp_shell_chrome')
            && str_contains($shell, 'SNAPSHOT_PREFIX');
        $this->record('Phase 10 shell intact', $ok, $ok ? 'ok' : 'regressed');
    }

    private function testSdkExposesAuth(): void
    {
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = str_contains($sdk, 'isAuthUnlockEnabled')
            && str_contains($sdk, 'auth:')
            && (str_contains($sdk, '11.0.0') || str_contains($sdk, '12.0.0') || str_contains($sdk, '13.0.0'));
        $this->record('SDK exposes auth unlock', $ok, $ok ? 'ok' : 'fail');
    }

    private function testRoutesAdditive(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/offline/server/routes/offline-api.php');
        $ok = str_contains($routes, 'ErpOfflineAuthApiController')
            && str_contains($routes, '/api/v1/offline/auth/device/register')
            && str_contains($routes, '/api/v1/offline/auth/policy');
        $this->record('auth API routes additive', $ok, $ok ? 'ok' : 'missing');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
