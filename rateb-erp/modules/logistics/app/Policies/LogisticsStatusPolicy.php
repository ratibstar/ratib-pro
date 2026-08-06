<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Policies;

/**
 * Status transition rules for logistics entities (sole authority for graph checks).
 */
final class LogisticsStatusPolicy
{
    public const ENTITY_VEHICLE = 'vehicle';
    public const ENTITY_DRIVER = 'driver';
    public const ENTITY_ROUTE = 'route';
    public const ENTITY_DELIVERY_ORDER = 'delivery_order';
    public const ENTITY_TRIP = 'trip';
    public const ENTITY_SHIPMENT = 'shipment';
    public const ENTITY_EXPENSE = 'expense';

    /** @return list<string> */
    public static function statuses(string $entityType): array
    {
        return array_keys(self::allowedTransitions($entityType));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(string $entityType): array
    {
        return match ($entityType) {
            self::ENTITY_VEHICLE => [
                'available' => ['assigned', 'maintenance', 'inactive'],
                'assigned' => ['available', 'maintenance', 'inactive'],
                'maintenance' => ['available', 'inactive'],
                'inactive' => ['available'],
            ],
            self::ENTITY_DRIVER => [
                'active' => ['inactive', 'suspended'],
                'inactive' => ['active'],
                'suspended' => ['active', 'inactive'],
            ],
            self::ENTITY_ROUTE => [
                'active' => ['inactive'],
                'inactive' => ['active'],
            ],
            self::ENTITY_DELIVERY_ORDER => [
                'draft' => ['confirmed', 'cancelled'],
                'confirmed' => ['dispatched', 'cancelled'],
                'dispatched' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => [],
            ],
            self::ENTITY_TRIP => [
                'draft' => ['assigned', 'cancelled'],
                'assigned' => ['started', 'cancelled'],
                'started' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => [],
            ],
            self::ENTITY_SHIPMENT => [
                'created' => ['picked', 'failed'],
                'picked' => ['packed', 'failed'],
                'packed' => ['shipped', 'failed'],
                'shipped' => ['out_for_delivery', 'failed'],
                'out_for_delivery' => ['delivered', 'failed'],
                'delivered' => [],
                'failed' => ['created'],
            ],
            self::ENTITY_EXPENSE => [
                'draft' => ['posted', 'cancelled'],
                'posted' => [],
                'cancelled' => [],
            ],
            default => [],
        };
    }

    public static function canTransition(string $entityType, string $from, string $to): bool
    {
        $from = trim($from);
        $to = trim($to);
        if ($from === '' || $to === '' || $from === $to) {
            return false;
        }
        $allowed = self::allowedTransitions($entityType)[$from] ?? null;
        if ($allowed === null) {
            return false;
        }

        return in_array($to, $allowed, true);
    }

    public static function assertTransition(string $entityType, string $from, string $to): void
    {
        if (!self::canTransition($entityType, $from, $to)) {
            throw new \RuntimeException('logistics_transition_denied:' . $entityType . ':' . $from . '->' . $to);
        }
    }

    public static function isTerminal(string $entityType, string $status): bool
    {
        $allowed = self::allowedTransitions($entityType)[$status] ?? null;

        return is_array($allowed) && $allowed === [];
    }
}
