<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Resources;

/**
 * Globally unique, provider-agnostic resource identities (not plan_id / sku / provider ids).
 */
final class ResourceIdentityManager
{
    private const PREFIX_GRAPH = 'res';

    public static function newResourcePublicId(): string
    {
        return self::randomUuidV4Style();
    }

    public static function newIntentId(): string
    {
        return 'intent_' . self::randomUuidV4Style();
    }

    /**
     * Stable graph node key for a resource_public_id.
     */
    public static function graphNodeId(string $resourcePublicId): string
    {
        return self::PREFIX_GRAPH . ':' . strtolower(trim($resourcePublicId));
    }

    /**
     * Tenant overlay row key uses the same public id string (immutable once issued).
     */
    public static function tenantResourceKey(string $resourcePublicId): string
    {
        return trim($resourcePublicId);
    }

    /**
     * @param array<string, mixed> $providerReference opaque provider handle (stored in metadata elsewhere)
     */
    public static function attachProviderReferenceMetadata(array $base, string $providerCode, string $reference): array
    {
        $base['provider_resource_reference'] = [
            'provider_code' => $providerCode,
            'reference' => $reference,
        ];

        return $base;
    }

    public static function assertPublicIdFormat(string $id): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            trim($id)
        );
    }

    private static function randomUuidV4Style(): string
    {
        $hex = bin2hex(random_bytes(16));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
