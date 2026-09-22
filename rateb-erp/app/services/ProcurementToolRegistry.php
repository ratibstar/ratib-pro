<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Procurement Tool Registry
 * Closed allowlist for the Procurement Ops Agent (Phase 1 + Phase 2 + Phase 3 + Phase 4).
 */
final class ProcurementToolRegistry
{
    /**
     * @return array<string, array{
     *     name: string,
     *     description: string,
     *     permission: string,
     *     module: string,
     *     write: bool,
     *     parameters: array
     * }>
     */
    public static function getTools(): array
    {
        return [
            'list_purchase_requests' => [
                'name' => 'list_purchase_requests',
                'description' => 'List purchase requests (tenant-scoped). Supports status, search, overdue, pending_approval, date range. Returns amounts, dates, and statuses.',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'search' => ['type' => 'string', 'default' => ''],
                        'status' => ['type' => 'string', 'default' => ''],
                        'overdue' => ['type' => 'boolean', 'default' => false],
                        'pending_approval' => ['type' => 'boolean', 'default' => false],
                        'date_from' => ['type' => 'string', 'format' => 'date'],
                        'date_to' => ['type' => 'string', 'format' => 'date'],
                    ],
                    'required' => [],
                ],
            ],
            'get_purchase_request' => [
                'name' => 'get_purchase_request',
                'description' => 'Get one purchase request by ID with line items, amounts, dates, and linked approval status (tenant-scoped)',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_purchase_orders' => [
                'name' => 'list_purchase_orders',
                'description' => 'List purchase orders (tenant-scoped). Supports status, search, overdue, pending_approval, date range. Returns amounts, dates, supplier, and statuses.',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'search' => ['type' => 'string', 'default' => ''],
                        'status' => ['type' => 'string', 'default' => ''],
                        'overdue' => ['type' => 'boolean', 'default' => false],
                        'pending_approval' => ['type' => 'boolean', 'default' => false],
                        'date_from' => ['type' => 'string', 'format' => 'date'],
                        'date_to' => ['type' => 'string', 'format' => 'date'],
                        'supplier_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'get_purchase_order' => [
                'name' => 'get_purchase_order',
                'description' => 'Get one purchase order by ID with amounts, dates, supplier, and linked approval status (tenant-scoped)',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'search_suppliers' => [
                'name' => 'search_suppliers',
                'description' => 'Search suppliers for the current company (tenant-scoped)',
                'permission' => 'suppliers.manage',
                'module' => 'suppliers',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'search' => ['type' => 'string', 'default' => ''],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'list_pending_approvals' => [
                'name' => 'list_pending_approvals',
                'description' => 'List pending approvals for purchase_request and purchase_order entities only (existing approval workflow)',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
                    ],
                    'required' => [],
                ],
            ],
            'get_approval_detail' => [
                'name' => 'get_approval_detail',
                'description' => 'Get approval workflow detail by instance ID (tenant-scoped, procurement entities only)',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'instance_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['instance_id'],
                ],
            ],
            'summarize_procurement' => [
                'name' => 'summarize_procurement',
                'description' => 'Procurement summary/report from live tenant data only: counts by status, total estimated/order amounts, overdue counts, pending approvals, and suppliers linked to orders/requests. Never invent numbers.',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'include_suppliers' => ['type' => 'boolean', 'default' => true],
                        'supplier_limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_procurement_intelligence' => [
                'name' => 'analyze_procurement_intelligence',
                'description' => 'Deep procurement intelligence from live tenant data only: cycle summary, pending, overdue, abnormal cases, and PR↔approval↔PO↔amount links. Never invent numbers; say when a bucket is empty.',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'include_links' => ['type' => 'boolean', 'default' => true],
                    ],
                    'required' => [],
                ],
            ],
            'get_purchase_request_cycle' => [
                'name' => 'get_purchase_request_cycle',
                'description' => 'Get one purchase request cycle from live tenant data: request + approval + linked purchase orders + amounts. Returns not-found when id is outside tenant.',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'analyze_advanced_procurement_operations' => [
                'name' => 'analyze_advanced_procurement_operations',
                'description' => 'Advanced procurement operations intelligence from live tenant data only: full cycle, spend/value/frequency, bottlenecks, abnormal cases, operational priorities, and an actionable executive summary. Never invent numbers.',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 15],
                        'lookback_days' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 180, 'default' => 30],
                    ],
                    'required' => [],
                ],
            ],
            'get_procurement_operational_guidance' => [
                'name' => 'get_procurement_operational_guidance',
                'description' => 'Operational guidance for “what should I do now?” from live tenant data. Links issues to PR/PO/approval entities and suggests next steps. Never executes WRITE actions.',
                'permission' => 'procurement.view',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 10],
                    ],
                    'required' => [],
                ],
            ],
            'create_draft_purchase_request' => [
                'name' => 'create_draft_purchase_request',
                'description' => 'Create a draft purchase request (WRITE — requires user confirmation)',
                'permission' => 'procurement.create',
                'module' => 'procurement',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'department' => ['type' => 'string', 'maxLength' => 100],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'default' => 'medium'],
                        'expected_date' => ['type' => 'string', 'format' => 'date'],
                        'currency' => ['type' => 'string', 'default' => 'SAR'],
                        'total_estimated' => ['type' => 'number', 'minimum' => 0],
                        'notes' => ['type' => 'string'],
                        'line_items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'item_name' => ['type' => 'string'],
                                    'quantity' => ['type' => 'number', 'minimum' => 0],
                                    'unit_price' => ['type' => 'number', 'minimum' => 0],
                                    'unit' => ['type' => 'string'],
                                    'supplier_id' => ['type' => 'integer'],
                                    'warehouse_id' => ['type' => 'integer'],
                                    'tax_rate' => ['type' => 'number', 'default' => 15],
                                ],
                                'required' => ['item_name', 'quantity', 'unit_price'],
                            ],
                        ],
                    ],
                    'required' => ['title'],
                ],
            ],
            'update_purchase_request' => [
                'name' => 'update_purchase_request',
                'description' => 'Update an existing purchase request fields/line items (WRITE — requires user confirmation). Draft/rejected only.',
                'permission' => 'procurement.update',
                'module' => 'procurement',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'department' => ['type' => 'string', 'maxLength' => 100],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                        'expected_date' => ['type' => 'string', 'format' => 'date'],
                        'currency' => ['type' => 'string'],
                        'total_estimated' => ['type' => 'number', 'minimum' => 0],
                        'notes' => ['type' => 'string'],
                        'line_items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'item_name' => ['type' => 'string'],
                                    'quantity' => ['type' => 'number', 'minimum' => 0],
                                    'unit_price' => ['type' => 'number', 'minimum' => 0],
                                    'unit' => ['type' => 'string'],
                                    'supplier_id' => ['type' => 'integer'],
                                    'warehouse_id' => ['type' => 'integer'],
                                    'tax_rate' => ['type' => 'number', 'default' => 15],
                                ],
                                'required' => ['item_name', 'quantity', 'unit_price'],
                            ],
                        ],
                    ],
                    'required' => ['id'],
                ],
            ],
            'cancel_purchase_request' => [
                'name' => 'cancel_purchase_request',
                'description' => 'Cancel a purchase request by setting status=cancelled (WRITE — requires user confirmation). Not allowed when already approved/cancelled.',
                'permission' => 'procurement.update',
                'module' => 'procurement',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'reason' => ['type' => 'string', 'maxLength' => 500],
                    ],
                    'required' => ['id'],
                ],
            ],
            'submit_purchase_request' => [
                'name' => 'submit_purchase_request',
                'description' => 'Submit a draft purchase request for approval workflow (WRITE — requires user confirmation)',
                'permission' => 'procurement.submit',
                'module' => 'procurement',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'create_draft_purchase_order' => [
                'name' => 'create_draft_purchase_order',
                'description' => 'Create a draft purchase order (WRITE — requires confirmation).',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                        'supplier_id' => ['type' => 'integer', 'minimum' => 1],
                        'total_amount' => ['type' => 'number', 'minimum' => 0],
                    ],
                    'required' => [],
                ],
            ],
            'create_draft_rfq' => [
                'name' => 'create_draft_rfq',
                'description' => 'Create a draft RFQ (WRITE — requires confirmation). Title required.',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 1],
                        'description' => ['type' => 'string'],
                        'deadline' => ['type' => 'string'],
                    ],
                    'required' => ['title'],
                ],
            ],
            'create_draft_quotation' => [
                'name' => 'create_draft_quotation',
                'description' => 'Create a draft supplier quotation (WRITE — requires confirmation).',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'notes' => ['type' => 'string'],
                        'amount' => ['type' => 'number', 'minimum' => 0],
                        'rfq_id' => ['type' => 'integer', 'minimum' => 1],
                        'supplier_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    public static function getOpenAiToolDefinitions(): array
    {
        $tools = [];
        foreach (self::getTools() as $toolName => $config) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $toolName,
                    'description' => $config['description'],
                    'parameters' => $config['parameters'],
                ],
            ];
        }
        return $tools;
    }

    public static function isAllowed(string $toolName): bool
    {
        return isset(self::getTools()[$toolName]);
    }

    public static function getTool(string $toolName): ?array
    {
        return self::getTools()[$toolName] ?? null;
    }

    public static function validateArguments(string $toolName, array $arguments): array
    {
        $tool = self::getTool($toolName);
        if (!$tool) {
            throw new \InvalidArgumentException("Tool not in allowlist: {$toolName}");
        }

        $required = $tool['parameters']['required'] ?? [];
        $properties = $tool['parameters']['properties'] ?? [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $arguments)) {
                throw new \InvalidArgumentException("Missing required argument: {$field}");
            }
            $value = $arguments[$field];
            $prop = is_array($properties[$field] ?? null) ? $properties[$field] : [];
            $type = (string) ($prop['type'] ?? '');
            if ($type === 'integer' || $type === 'number') {
                if (!is_numeric($value) || (float) $value < (float) ($prop['minimum'] ?? 0)) {
                    throw new \InvalidArgumentException("Invalid required argument: {$field}");
                }
            } elseif ($type === 'string') {
                $str = trim((string) $value);
                $minLen = (int) ($prop['minLength'] ?? 1);
                if ($str === '' || mb_strlen($str) < $minLen) {
                    throw new \InvalidArgumentException("Invalid required argument: {$field}");
                }
                $arguments[$field] = $str;
            } elseif ($value === null) {
                throw new \InvalidArgumentException("Invalid required argument: {$field}");
            }
        }

        return $arguments;
    }

    /**
     * Whether write/read arguments are sufficient to execute safely.
     * Used by PolicyGuard before confirmation/execution.
     */
    public static function hasSufficientParameters(string $toolName, array $arguments): bool
    {
        $tool = self::getTool($toolName);
        if (!$tool) {
            return false;
        }

        try {
            self::validateArguments($toolName, $arguments);
        } catch (\Throwable $e) {
            return false;
        }

        if ($toolName === 'create_draft_purchase_request') {
            return trim((string) ($arguments['title'] ?? '')) !== '';
        }
        if ($toolName === 'update_purchase_request') {
            $id = (int) ($arguments['id'] ?? 0);
            if ($id < 1) {
                return false;
            }
            $mutable = ['title', 'department', 'priority', 'expected_date', 'currency', 'total_estimated', 'notes', 'line_items'];
            foreach ($mutable as $field) {
                if (array_key_exists($field, $arguments)) {
                    return true;
                }
            }
            return false;
        }
        if ($toolName === 'cancel_purchase_request' || $toolName === 'submit_purchase_request' || $toolName === 'get_purchase_request_cycle') {
            return (int) ($arguments['id'] ?? 0) > 0;
        }
        if ($toolName === 'get_purchase_request' || $toolName === 'get_purchase_order') {
            return (int) ($arguments['id'] ?? 0) > 0;
        }
        if ($toolName === 'get_approval_detail') {
            return (int) ($arguments['instance_id'] ?? 0) > 0;
        }

        return true;
    }
}
