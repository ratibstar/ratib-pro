<?php

declare(strict_types=1);

/**
 * Phase 2C — POS Offline Auth + Device Governance tests.
 *
 * Run: php modules/pos/tests/run-offline-sync-tests.php
 */

use Rateb\App\Offline\Models\OfflineDevice;
use Rateb\App\Pos\Services\PosOfflineDeviceService;
use Rateb\App\Services\BiometricAuthService;

final class PosOfflinePhase2CTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testPinIsClientOnlyPbkdf2();
        $this->testDeviceStatusGatePendingBlocked();
        $this->testDeviceStatusGateActiveAllowed();
        $this->testDeviceStatusGateUnknownAllowsUntilCached();
        $this->testPublicDeviceIsActiveFlag();
        $this->testOfflineDeviceStatusConstants();
        $this->testCredentialCompanyScopeInService();
        $this->testCredentialCompanyScopeMigration();
        $this->testFaceVerifyNeverSucceeds();
        $this->testClientNoFaceStubSuccess();
        $this->testClientLockOfflinePath();
        $this->testSwBiometricRedirectsToRegister();
        $this->testSyncGatesOnSessionReauth();
        $this->testDeviceApiTenantIsolationSource();
        $this->testDeviceAdminPermissionWired();
        $this->testMigrationCollisionResolved();
        $this->testChallengeBindingPresent();

        return $this->results;
    }

    /**
     * Mirrors pos-auth-lock.js isDeviceAllowedOffline() for unit assertion.
     *
     * @param array{status?: string, is_active?: bool}|null $cached
     */
    private function deviceAllowedOffline(?array $cached): bool
    {
        if ($cached === null || !isset($cached['status']) || $cached['status'] === '') {
            return true;
        }

        return ($cached['is_active'] ?? false) === true || ($cached['status'] ?? '') === 'active';
    }

    private function testPinIsClientOnlyPbkdf2(): void
    {
        $lock = (string) file_get_contents(RATEB_ROOT . '/public/assets/pos/js/pos-auth-lock.js');
        $ok = str_contains($lock, 'Never stores raw passwords')
            && str_contains($lock, 'PBKDF2')
            && str_contains($lock, 'hashPin')
            && str_contains($lock, 'verifyPin')
            && str_contains($lock, 'pin_hash')
            && str_contains($lock, 'pin_salt')
            && !preg_match('/\bpassword\s*[:=]\s*[\'"][^\'"]+[\'"]/', $lock);
        // No server-side PIN verify in POS device/auth services.
        $deviceSvc = (string) file_get_contents(RATEB_ROOT . '/modules/pos/app/Services/PosOfflineDeviceService.php');
        $bio = (string) file_get_contents(RATEB_ROOT . '/app/services/BiometricAuthService.php');
        $ok = $ok
            && !str_contains($deviceSvc, 'pin_hash')
            && !str_contains($bio, 'pin_hash')
            && !str_contains($bio, 'verifyPin');
        $this->record(
            'PIN is client-only PBKDF2 (no server PIN verify)',
            $ok,
            $ok ? 'client vault only' : 'unexpected server PIN or missing client hash'
        );
    }

    private function testDeviceStatusGatePendingBlocked(): void
    {
        $allowed = $this->deviceAllowedOffline([
            'status' => OfflineDevice::STATUS_PENDING,
            'is_active' => false,
        ]);
        $lock = (string) file_get_contents(RATEB_ROOT . '/public/assets/pos/js/pos-auth-lock.js');
        $wired = str_contains($lock, 'isDeviceAllowedOffline')
            && str_contains($lock, 'unlockWithPin')
            && str_contains($lock, 'unlockWithWebAuthn')
            && str_contains($lock, 'pos_lock_device_inactive');
        $this->record(
            'device status gate: pending cannot unlock',
            $allowed === false && $wired,
            $allowed ? 'pending allowed (bug)' : ($wired ? 'blocked' : 'gate not wired')
        );
    }

    private function testDeviceStatusGateActiveAllowed(): void
    {
        $allowed = $this->deviceAllowedOffline([
            'status' => OfflineDevice::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $revoked = $this->deviceAllowedOffline([
            'status' => OfflineDevice::STATUS_REVOKED,
            'is_active' => false,
        ]);
        $this->record(
            'device status gate: active can unlock / revoke blocks',
            $allowed === true && $revoked === false,
            $allowed && !$revoked ? 'active ok, revoked blocked' : 'gate mismatch'
        );
    }

    private function testDeviceStatusGateUnknownAllowsUntilCached(): void
    {
        $unknown = $this->deviceAllowedOffline(null);
        $empty = $this->deviceAllowedOffline(['status' => '']);
        $this->record(
            'device status gate: unknown status allows until cached',
            $unknown === true && $empty === true,
            'residual: unlock allowed before first heartbeat cache'
        );
    }

    private function testPublicDeviceIsActiveFlag(): void
    {
        $ref = new ReflectionClass(PosOfflineDeviceService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('publicDevice');
        $method->setAccessible(true);

        $pending = $method->invoke($svc, [
            'id' => 1,
            'company_id' => 10,
            'branch_id' => 2,
            'device_id' => 'dev-1',
            'status' => OfflineDevice::STATUS_PENDING,
        ]);
        $active = $method->invoke($svc, [
            'id' => 2,
            'company_id' => 10,
            'device_id' => 'dev-2',
            'status' => OfflineDevice::STATUS_ACTIVE,
        ]);

        $ok = ($pending['is_active'] ?? null) === false
            && ($active['is_active'] ?? null) === true
            && ($pending['status'] ?? '') === 'pending'
            && ($active['status'] ?? '') === 'active';
        $this->record('publicDevice is_active mirrors status', $ok, json_encode([
            'pending' => $pending['is_active'] ?? null,
            'active' => $active['is_active'] ?? null,
        ]));
    }

    private function testOfflineDeviceStatusConstants(): void
    {
        $ok = OfflineDevice::STATUS_PENDING === 'pending'
            && OfflineDevice::STATUS_ACTIVE === 'active'
            && OfflineDevice::STATUS_INACTIVE === 'inactive'
            && OfflineDevice::STATUS_REVOKED === 'revoked';
        $fillable = (string) file_get_contents(RATEB_ROOT . '/offline/server/Models/OfflineDevice.php');
        $ok = $ok
            && str_contains($fillable, 'activated_by')
            && str_contains($fillable, 'activated_at')
            && str_contains($fillable, 'approved_by');
        $this->record('OfflineDevice status + activation fillable', $ok, $ok ? 'ok' : 'missing');
    }

    private function testCredentialCompanyScopeInService(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/BiometricAuthService.php');
        $ok = str_contains($src, 'companyColumnReady()')
            && substr_count($src, 'company_id = :cid OR company_id IS NULL') >= 3
            && str_contains($src, 'ADD COLUMN company_id') === false // migration separate
            && str_contains($src, 'assertClientDataChallenge')
            && str_contains($src, 'clientDataJSON');
        $this->record('WebAuthn credential company scope in BiometricAuthService', $ok, $ok ? 'scoped queries' : 'missing filters');
    }

    private function testCredentialCompanyScopeMigration(): void
    {
        $path = RATEB_ROOT . '/migrations/178_pos_webauthn_company_scope.sql';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $src !== ''
            && str_contains($src, 'rateb_webauthn_credentials')
            && str_contains($src, 'company_id')
            && str_contains($src, 'branch_id')
            && str_contains($src, 'idx_webauthn_company_user');
        $this->record('migration 178 webauthn company_id/branch_id', $ok, $ok ? 'present' : 'missing');
    }

    private function testFaceVerifyNeverSucceeds(): void
    {
        if (!function_exists('__')) {
            eval('function __(string $key, ...$args): string { return $key; }');
        }
        $result = (new BiometricAuthService())->verifyFace(1, ['template' => 'stub-success']);
        $ok = ($result['ok'] ?? true) === false
            && str_contains((string) ($result['error'] ?? ''), 'face');
        $this->record('server verifyFace never succeeds', $ok, json_encode($result));
    }

    private function testClientNoFaceStubSuccess(): void
    {
        $gate = (string) file_get_contents(RATEB_ROOT . '/public/assets/pos/js/pos-biometric-gate.js');
        $ok = str_contains($gate, 'Face recognition is not enabled')
            && str_contains($gate, 'never call the face API')
            && str_contains($gate, 'faceBtn.disabled = true')
            && str_contains($gate, 'pos_biometric_face_coming_soon')
            && !preg_match('/runFace[\s\S]{0,400}ok\s*:\s*true/', $gate)
            && !preg_match('/face[\s\S]{0,200}stub[\s\S]{0,80}success/i', $gate);
        $this->record('client face: disabled, no stub success', $ok, $ok ? 'ok' : 'stub path found');
    }

    private function testClientLockOfflinePath(): void
    {
        $lock = (string) file_get_contents(RATEB_ROOT . '/public/assets/pos/js/pos-auth-lock.js');
        $shell = (string) file_get_contents(RATEB_ROOT . '/modules/pos/views/layouts/pos-shell.php');
        $header = (string) file_get_contents(RATEB_ROOT . '/modules/pos/views/partials/pos-register-header.php');
        $ok = str_contains($shell, 'pos-auth-lock.js')
            && str_contains($header, 'data-pos-auth-lock-now')
            && str_contains($lock, 'data-pos-auth-lock')
            && str_contains($lock, 'showOverlay')
            && str_contains($lock, 'rateb-pos--auth-locked')
            && str_contains($lock, 'enrollFromGate');
        $this->record('lock shown offline path wired (shell + vault)', $ok, $ok ? 'wired' : 'missing');
    }

    private function testSwBiometricRedirectsToRegister(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/pos-sw.js');
        $ok = str_contains($sw, 'isBiometricGatePath')
            && str_contains($sw, 'registerPathFromBiometric')
            && str_contains($sw, 'Do not pin biometric gate HTML')
            && str_contains($sw, 'rateb-pos-shell-v7');
        $this->record('SW biometric offline → register shell', $ok, $ok ? 'v7 redirect' : 'missing');
    }

    private function testSyncGatesOnSessionReauth(): void
    {
        $sync = (string) file_get_contents(RATEB_ROOT . '/public/assets/pos/js/pos-offline-sync.js');
        $lock = (string) file_get_contents(RATEB_ROOT . '/public/assets/pos/js/pos-auth-lock.js');
        $ok = str_contains($sync, 'sessionNeedsReauth')
            && str_contains($sync, 'session_reauth_required')
            && str_contains($lock, 'pos_lock_session_renew')
            && str_contains($lock, 'markSessionNeedsReauth');
        $this->record('sync gate for session renew', $ok, $ok ? 'gated' : 'missing');
    }

    private function testDeviceApiTenantIsolationSource(): void
    {
        $svc = (string) file_get_contents(RATEB_ROOT . '/modules/pos/app/Services/PosOfflineDeviceService.php');
        $model = (string) file_get_contents(RATEB_ROOT . '/offline/server/Models/OfflineDevice.php');
        $api = (string) file_get_contents(RATEB_ROOT . '/modules/pos/app/Controllers/PosDeviceApiController.php');
        $ok = str_contains($svc, '(int) ($row[\'company_id\'] ?? 0) !== $companyId')
            && substr_count($svc, 'TenantContext::setCompanyId($companyId)') >= 2
            && str_contains($model, 'WHERE company_id = :cid AND device_id = :did')
            && str_contains($model, 'tenantScoped = true')
            && str_contains($api, '$this->companyId()')
            && str_contains($svc, 'STATUS_PENDING')
            && str_contains($svc, 'STATUS_ACTIVE');
        $this->record('device APIs tenant-isolated (company_id)', $ok, $ok ? 'ok' : 'leak risk');
    }

    private function testDeviceAdminPermissionWired(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/modules/pos/routes/pos.php');
        $perms = (string) file_get_contents(RATEB_ROOT . '/modules/pos/config/entity-permissions.php');
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/modules/pos/app/Controllers/PosDevicesController.php');
        $view = RATEB_ROOT . '/modules/pos/views/devices/index.php';
        $mig = (string) file_get_contents(RATEB_ROOT . '/migrations/179_offline_device_activation.sql');
        $ok = str_contains($perms, 'pos.devices.manage')
            && str_contains($ctrl, 'pos.devices.manage')
            && is_file($view)
            && str_contains($routes, 'api/device/register')
            && str_contains($routes, 'api/device/heartbeat')
            && str_contains($mig, 'pos.devices.manage')
            && str_contains($mig, 'pending');
        $this->record('device admin UI + pos.devices.manage + APIs', $ok, $ok ? 'wired' : 'missing');
    }

    private function testMigrationCollisionResolved(): void
    {
        $webauthn = RATEB_ROOT . '/migrations/178_pos_webauthn_company_scope.sql';
        $device179 = RATEB_ROOT . '/migrations/179_offline_device_activation.sql';
        $device178legacy = RATEB_ROOT . '/migrations/178_offline_device_activation.sql';
        $ok = is_file($webauthn)
            && is_file($device179)
            && !is_file($device178legacy);
        $this->record(
            'migration collision resolved (178 webauthn, 179 devices)',
            $ok,
            $ok ? '178+179 unique' : 'still colliding or missing'
        );
    }

    private function testChallengeBindingPresent(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/BiometricAuthService.php');
        $ok = str_contains($src, 'assertClientDataChallenge')
            && str_contains($src, 'webauthn.get')
            && str_contains($src, 'webauthn.create')
            && str_contains($src, 'hash_equals($expectedChallenge, $clientChallenge)');
        $this->record('WebAuthn challenge binding via clientDataJSON', $ok, $ok ? 'bound' : 'missing');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
