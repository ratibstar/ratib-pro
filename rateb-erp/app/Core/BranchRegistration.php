<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D — Secure Branch registration (local generation; cloud approval offline-capable).
 * Produces Branch UUID, Device UUID, certificate, public key, registration payload.
 */
final class BranchRegistration
{
    /**
     * @return array{
     *   branch_uuid:string,device_uuid:string,public_key:string,certificate:string,
     *   registration_payload:array<string,mixed>,payload_path:string
     * }
     */
    public function ensureLocalIdentity(): array
    {
        BranchAppliancePaths::ensureLayout();
        $dir = BranchAppliancePaths::identityDir();
        $metaPath = $dir . '/identity.json';

        if (is_file($metaPath)) {
            $existing = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($existing) && ($existing['branch_uuid'] ?? '') !== '') {
                return $this->hydrate($existing);
            }
        }

        $branchUuid = HybridSyncCrypto::uuidV4();
        $deviceUuid = HybridSyncCrypto::uuidV4();
        $keyPair = $this->generateKeyPair();
        $certificate = $this->buildCertificate($branchUuid, $deviceUuid, $keyPair['public']);
        $payload = [
            'type' => 'rateb_branch_registration',
            'version' => 1,
            'created_at' => gmdate('c'),
            'branch_uuid' => $branchUuid,
            'device_uuid' => $deviceUuid,
            'public_key' => $keyPair['public'],
            'certificate' => $certificate,
            'hostname' => gethostname() ?: 'branch-appliance',
            'php_version' => PHP_VERSION,
            'appliance_version' => BranchAppliancePaths::readVersion(),
            'status' => 'pending_cloud_approval',
        ];
        $payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $sig = hash_hmac('sha256', $payloadJson, $keyPair['private']);
        $payload['signature'] = $sig;

        $record = [
            'branch_uuid' => $branchUuid,
            'device_uuid' => $deviceUuid,
            'public_key' => $keyPair['public'],
            'private_key' => $keyPair['private'],
            'certificate' => $certificate,
            'created_at' => gmdate('c'),
        ];
        file_put_contents($metaPath, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($metaPath, 0600);
        file_put_contents($dir . '/public.key', $keyPair['public']);
        file_put_contents($dir . '/private.key', $keyPair['private']);
        @chmod($dir . '/private.key', 0600);
        file_put_contents($dir . '/device.cert', $certificate);

        $regDir = BranchAppliancePaths::root() . '/registration';
        $payloadPath = $regDir . '/registration-payload.json';
        file_put_contents($payloadPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $this->hydrate($record + ['registration_payload' => $payload, 'payload_path' => $payloadPath]);
    }

    /** @return array{ok:bool,payload:array<string,mixed>,path:string} */
    public function generateRegistrationPayload(): array
    {
        $id = $this->ensureLocalIdentity();
        $path = BranchAppliancePaths::root() . '/registration/registration-payload.json';
        $payload = is_array($id['registration_payload'] ?? null)
            ? $id['registration_payload']
            : (json_decode((string) @file_get_contents($path), true) ?: []);

        return ['ok' => ($payload['branch_uuid'] ?? '') !== '', 'payload' => $payload, 'path' => $path];
    }

    /** Mark local registration as approved (cloud operator / offline approval file). */
    public function markApproved(string $approvalToken = ''): array
    {
        $path = BranchAppliancePaths::root() . '/registration/approval.json';
        $data = [
            'status' => 'approved',
            'approved_at' => gmdate('c'),
            'token_hash' => $approvalToken !== '' ? hash('sha256', $approvalToken) : null,
        ];
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        return ['ok' => true] + $data;
    }

    /** @param array<string,mixed> $record */
    private function hydrate(array $record): array
    {
        $path = BranchAppliancePaths::root() . '/registration/registration-payload.json';
        $payload = is_array($record['registration_payload'] ?? null)
            ? $record['registration_payload']
            : (is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : []);

        return [
            'branch_uuid' => (string) ($record['branch_uuid'] ?? ''),
            'device_uuid' => (string) ($record['device_uuid'] ?? ''),
            'public_key' => (string) ($record['public_key'] ?? ''),
            'certificate' => (string) ($record['certificate'] ?? ''),
            'registration_payload' => $payload,
            'payload_path' => $path,
        ];
    }

    /** @return array{public:string,private:string} */
    private function generateKeyPair(): array
    {
        if (function_exists('openssl_pkey_new')) {
            $res = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            if ($res !== false) {
                openssl_pkey_export($res, $private);
                $details = openssl_pkey_get_details($res);
                $public = is_array($details) ? (string) ($details['key'] ?? '') : '';
                if ($private !== '' && $public !== '') {
                    return ['public' => $public, 'private' => $private];
                }
            }
        }
        // Offline-capable fallback: HMAC device keys (still unique per install)
        $private = bin2hex(random_bytes(32));
        $public = hash('sha256', 'rateb-pub|' . $private);

        return ['public' => $public, 'private' => $private];
    }

    private function buildCertificate(string $branchUuid, string $deviceUuid, string $publicKey): string
    {
        $body = [
            'iss' => 'rateb-branch-appliance',
            'sub' => $deviceUuid,
            'branch' => $branchUuid,
            'iat' => time(),
            'alg' => 'sha256',
            'pub' => hash('sha256', $publicKey),
        ];
        $json = json_encode($body, JSON_UNESCAPED_SLASHES) ?: '{}';

        return base64_encode($json) . '.' . hash_hmac('sha256', $json, $branchUuid . '|' . $deviceUuid);
    }
}
