<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\BranchTransfer;
use Rateb\App\Models\JournalEntry;
use Rateb\App\Models\StockMovement;
use Rateb\App\Services\DocumentCodeService;

/**
 * Executes approved inter-branch transfers inside a single DB transaction.
 * On failure the transaction rolls back and transfer status becomes failed.
 */
final class InterBranchTransferService
{
    /** @return array{ok:bool,transfer_id:int,meta:array<string,mixed>} */
    public function approveAndExecute(int $transferId, int $approvedBy): array
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $transfer = $this->lockTransfer($transferId);
            $status = (string) ($transfer['status'] ?? '');
            if ($status !== 'pending') {
                throw new \RuntimeException('Transfer is not pending: ' . $status);
            }

            $oldSnapshot = $this->transferSnapshot($transfer);
            $this->updateTransferRow($transferId, [
                'status' => 'approved',
                'approved_by' => $approvedBy > 0 ? $approvedBy : null,
            ]);

            $meta = $this->executeByType($transfer, $approvedBy);
            $payload = $this->mergePayloadJson($transfer, ['execution' => $meta]);

            $this->updateTransferRow($transferId, [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            $db->commit();

            $fresh = $this->loadTransfer($transferId);
            (new AuditService())->logTransfer(
                'inter_branch_transfer_completed',
                $fresh,
                $oldSnapshot,
                $this->transferSnapshot($fresh),
                $approvedBy
            );
            $this->notifyCompleted($fresh, $meta);

            return ['ok' => true, 'transfer_id' => $transferId, 'meta' => $meta];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->markFailed($transferId, $e->getMessage(), $approvedBy);
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function executeByType(array $transfer, int $approvedBy): array
    {
        return match ((string) ($transfer['transfer_type'] ?? '')) {
            'employee' => $this->executeEmployee($transfer),
            'inventory' => $this->executeInventory($transfer, $approvedBy),
            'asset' => $this->executeAsset($transfer, $approvedBy),
            'accounting' => $this->executeAccounting($transfer, $approvedBy),
            default => throw new \InvalidArgumentException('Unsupported transfer type'),
        };
    }

    /** @param array<string,mixed> $transfer @return array<string,mixed> */
    private function executeEmployee(array $transfer): array
    {
        $companyId = (int) $transfer['company_id'];
        $sourceBranch = (int) $transfer['source_branch_id'];
        $destBranch = (int) $transfer['dest_branch_id'];
        $employeeId = (int) ($transfer['source_entity_id'] ?? 0);
        if ($employeeId < 1) {
            throw new \InvalidArgumentException('Employee id required');
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $employeeId, 'cid' => $companyId]);
        $employee = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$employee) {
            throw new \RuntimeException('Employee not found');
        }
        if ((int) ($employee['branch_id'] ?? 0) !== $sourceBranch) {
            throw new \RuntimeException('Employee is not at source branch');
        }

        $payload = $this->decodePayload($transfer);
        $updates = ['branch_id' => $destBranch];
        if (isset($payload['department_id']) && (int) $payload['department_id'] > 0) {
            $updates['department_id'] = (int) $payload['department_id'];
        }

        $set = [];
        $params = ['id' => $employeeId, 'cid' => $companyId];
        foreach ($updates as $col => $val) {
            $key = 'u_' . $col;
            $set[] = $col . ' = :' . $key;
            $params[$key] = $val;
        }
        $db->prepare(
            'UPDATE rateb_employees SET ' . implode(', ', $set) . ' WHERE id = :id AND company_id = :cid'
        )->execute($params);

        $userId = (int) ($employee['user_id'] ?? 0);
        if ($userId > 0 && $this->tableExists('rateb_user_branches')) {
            $db->prepare('DELETE FROM rateb_user_branches WHERE user_id = :uid')->execute(['uid' => $userId]);
            $db->prepare('INSERT INTO rateb_user_branches (user_id, branch_id) VALUES (:uid, :bid)')
                ->execute(['uid' => $userId, 'bid' => $destBranch]);
        }

        if ($this->columnExists('rateb_attendance_records', 'branch_id')) {
            $db->prepare(
                'UPDATE rateb_attendance_records SET branch_id = :dest
                 WHERE company_id = :cid AND employee_id = :eid AND branch_id = :src'
            )->execute(['dest' => $destBranch, 'cid' => $companyId, 'eid' => $employeeId, 'src' => $sourceBranch]);
        }
        if ($this->columnExists('rateb_leave_requests', 'branch_id')) {
            $db->prepare(
                'UPDATE rateb_leave_requests SET branch_id = :dest
                 WHERE company_id = :cid AND employee_id = :eid AND branch_id = :src AND status IN (\'pending\',\'approved\')'
            )->execute(['dest' => $destBranch, 'cid' => $companyId, 'eid' => $employeeId, 'src' => $sourceBranch]);
        }
        if ($this->columnExists('rateb_payroll_lines', 'branch_id')) {
            $db->prepare(
                'UPDATE rateb_payroll_lines pl
                 INNER JOIN rateb_payroll_periods pp ON pp.id = pl.period_id
                 SET pl.branch_id = :dest
                 WHERE pl.company_id = :cid AND pl.employee_id = :eid
                   AND pp.status IN (\'draft\',\'open\')'
            )->execute(['dest' => $destBranch, 'cid' => $companyId, 'eid' => $employeeId]);
        }

        return [
            'employee_id' => $employeeId,
            'old_branch_id' => $sourceBranch,
            'new_branch_id' => $destBranch,
            'old' => $employee,
            'new' => array_merge($employee, $updates),
        ];
    }

    /** @param array<string,mixed> $transfer @return array<string,mixed> */
    private function executeInventory(array $transfer, int $approvedBy): array
    {
        $companyId = (int) $transfer['company_id'];
        $sourceBranch = (int) $transfer['source_branch_id'];
        $destBranch = (int) $transfer['dest_branch_id'];
        $transferId = (int) $transfer['id'];
        $inventoryId = (int) ($transfer['source_entity_id'] ?? 0);
        $qty = (float) ($transfer['quantity'] ?? 0);
        if ($inventoryId < 1 || $qty <= 0) {
            throw new \InvalidArgumentException('Inventory id and quantity required');
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_inventory WHERE id = :id AND company_id = :cid LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $inventoryId, 'cid' => $companyId]);
        $sourceItem = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$sourceItem) {
            throw new \RuntimeException('Inventory item not found');
        }
        if ((int) ($sourceItem['branch_id'] ?? 0) !== $sourceBranch) {
            throw new \RuntimeException('Inventory item is not at source branch');
        }
        $currentQty = (float) ($sourceItem['quantity'] ?? 0);
        if ($currentQty < $qty) {
            throw new \RuntimeException('Insufficient stock at source branch');
        }

        $unitCost = (float) ($sourceItem['unit_cost'] ?? 0);
        $newSourceQty = max(0, $currentQty - $qty);
        $db->prepare('UPDATE rateb_inventory SET quantity = :q WHERE id = :id')
            ->execute(['q' => $newSourceQty, 'id' => $inventoryId]);

        $outId = $this->insertStockMovement($companyId, $inventoryId, 'out', $qty, $transferId, $sourceBranch, $approvedBy, 'Inter-branch out');
        if ($this->tableExists('rateb_inventory_batches')) {
            (new InventoryWorkflowService())->consumeBatches($inventoryId, $qty, 'fefo');
        }

        $destItem = $this->resolveDestInventory($sourceItem, $destBranch, $companyId);
        $destId = (int) $destItem['id'];
        $destQty = (float) ($destItem['quantity'] ?? 0);
        $destCost = (float) ($destItem['unit_cost'] ?? 0);
        $newDestQty = $destQty + $qty;
        $newAvgCost = $newDestQty > 0
            ? round((($destQty * $destCost) + ($qty * $unitCost)) / $newDestQty, 4)
            : $unitCost;
        $db->prepare('UPDATE rateb_inventory SET quantity = :q, unit_cost = :c WHERE id = :id')
            ->execute(['q' => $newDestQty, 'c' => $newAvgCost, 'id' => $destId]);

        $inId = $this->insertStockMovement($companyId, $destId, 'in', $qty, $transferId, $destBranch, $approvedBy, 'Inter-branch in');

        $amount = round($qty * $unitCost, 2);
        $journalIds = [];
        if ($amount > 0) {
            $journalIds = $this->postInventoryGl($companyId, $transferId, $sourceBranch, $destBranch, $amount);
        }

        return [
            'source_inventory_id' => $inventoryId,
            'dest_inventory_id' => $destId,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'amount' => $amount,
            'movements' => ['out' => $outId, 'in' => $inId],
            'journal_entry_ids' => $journalIds,
        ];
    }

    /** @param array<string,mixed> $transfer @return array<string,mixed> */
    private function executeAsset(array $transfer, int $approvedBy): array
    {
        $companyId = (int) $transfer['company_id'];
        $sourceBranch = (int) $transfer['source_branch_id'];
        $destBranch = (int) $transfer['dest_branch_id'];
        $transferId = (int) $transfer['id'];
        $assetId = (int) ($transfer['source_entity_id'] ?? 0);
        if ($assetId < 1) {
            throw new \InvalidArgumentException('Asset id required');
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_assets WHERE id = :id AND company_id = :cid LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $assetId, 'cid' => $companyId]);
        $asset = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$asset) {
            throw new \RuntimeException('Asset not found');
        }
        if ((int) ($asset['branch_id'] ?? 0) !== $sourceBranch) {
            throw new \RuntimeException('Asset is not at source branch');
        }

        $bookValue = (float) ($asset['current_value'] ?? $asset['purchase_cost'] ?? 0);
        $db->prepare('UPDATE rateb_assets SET branch_id = :bid WHERE id = :id AND company_id = :cid')
            ->execute(['bid' => $destBranch, 'id' => $assetId, 'cid' => $companyId]);

        $journalIds = [];
        if ($bookValue > 0) {
            $journalIds = $this->postAssetGl($companyId, $transferId, $sourceBranch, $destBranch, $bookValue, (string) ($asset['name'] ?? 'Asset'));
        }

        return [
            'asset_id' => $assetId,
            'old_branch_id' => $sourceBranch,
            'new_branch_id' => $destBranch,
            'book_value' => $bookValue,
            'journal_entry_ids' => $journalIds,
        ];
    }

    /** @param array<string,mixed> $transfer @return array<string,mixed> */
    private function executeAccounting(array $transfer, int $approvedBy): array
    {
        $companyId = (int) $transfer['company_id'];
        $sourceBranch = (int) $transfer['source_branch_id'];
        $destBranch = (int) $transfer['dest_branch_id'];
        $transferId = (int) $transfer['id'];
        $amount = round((float) ($transfer['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Accounting transfer amount required');
        }

        $acct = new AccountingService();
        $dueFrom = $acct->accountIdByCode($companyId, '1350');
        $dueTo = $acct->accountIdByCode($companyId, '2150');
        $clearing = $acct->accountIdByCode($companyId, '5100') ?: $acct->accountIdByCode($companyId, '1000');
        if (!$dueFrom || !$dueTo || !$clearing) {
            throw new \RuntimeException('Inter-branch GL accounts missing (1350/2150/clearing)');
        }

        $entryDate = date('Y-m-d');
        $ref = (string) ($transfer['transfer_no'] ?? $transferId);
        $sourceEntryId = $this->postBranchJournal(
            $companyId,
            $sourceBranch,
            $transferId,
            [
                ['account_id' => $dueFrom, 'debit' => $amount, 'credit' => 0, 'memo' => 'Due from dest ' . $ref],
                ['account_id' => $clearing, 'debit' => 0, 'credit' => $amount, 'memo' => 'IBT source ' . $ref],
            ],
            'Inter-branch transfer ' . $ref,
            'تحويل بين الفروع ' . $ref,
            $entryDate,
            $approvedBy
        );
        $destEntryId = $this->postBranchJournal(
            $companyId,
            $destBranch,
            $transferId,
            [
                ['account_id' => $clearing, 'debit' => $amount, 'credit' => 0, 'memo' => 'IBT dest ' . $ref],
                ['account_id' => $dueTo, 'debit' => 0, 'credit' => $amount, 'memo' => 'Due to source ' . $ref],
            ],
            'Inter-branch transfer ' . $ref,
            'تحويل بين الفروع ' . $ref,
            $entryDate,
            $approvedBy
        );

        return [
            'amount' => $amount,
            'due_from_account' => $dueFrom,
            'due_to_account' => $dueTo,
            'journal_entry_ids' => [$sourceEntryId, $destEntryId],
        ];
    }

    /** @param array<string,mixed> $sourceItem @return array<string,mixed> */
    private function resolveDestInventory(array $sourceItem, int $destBranch, int $companyId): array
    {
        $db = Database::connection();
        $sku = trim((string) ($sourceItem['sku'] ?? ''));
        $itemCode = trim((string) ($sourceItem['item_code'] ?? ''));
        $params = ['cid' => $companyId, 'bid' => $destBranch];
        $sql = 'SELECT * FROM rateb_inventory WHERE company_id = :cid AND branch_id = :bid AND (';
        $parts = [];
        if ($sku !== '') {
            $parts[] = 'sku = :sku';
            $params['sku'] = $sku;
        }
        if ($itemCode !== '') {
            $parts[] = 'item_code = :ic';
            $params['ic'] = $itemCode;
        }
        if ($parts === []) {
            $parts[] = 'item_name = :nm';
            $params['nm'] = (string) ($sourceItem['item_name'] ?? '');
        }
        $sql .= implode(' OR ', $parts) . ') LIMIT 1 FOR UPDATE';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        $warehouseId = null;
        if ($this->tableExists('rateb_warehouses')) {
            $wh = $db->prepare(
                'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND branch_id = :bid AND status = :st ORDER BY id ASC LIMIT 1'
            );
            $wh->execute(['cid' => $companyId, 'bid' => $destBranch, 'st' => 'active']);
            $whRow = $wh->fetch(\PDO::FETCH_ASSOC);
            $warehouseId = $whRow ? (int) $whRow['id'] : null;
        }
        if ($warehouseId === null && class_exists(WarehouseService::class)) {
            try {
                TenantContext::setCompanyId($companyId);
                $warehouseId = (new WarehouseService())->ensureDefaultWarehouse($companyId);
            } catch (\Throwable $e) {
                $warehouseId = null;
            }
        }

        $insert = [
            'company_id' => $companyId,
            'branch_id' => $destBranch,
            'warehouse_id' => $warehouseId,
            'item_code' => $sourceItem['item_code'] ?? null,
            'item_name' => $sourceItem['item_name'] ?? 'Item',
            'sku' => $sourceItem['sku'] ?? null,
            'category' => $sourceItem['category'] ?? null,
            'quantity' => 0,
            'unit_cost' => $sourceItem['unit_cost'] ?? 0,
            'status' => $sourceItem['status'] ?? 'active',
        ];
        if ($this->columnExists('rateb_inventory', 'category_id') && isset($sourceItem['category_id'])) {
            $insert['category_id'] = $sourceItem['category_id'];
        }
        if ($this->columnExists('rateb_inventory', 'unit') && isset($sourceItem['unit'])) {
            $insert['unit'] = $sourceItem['unit'];
        }
        if ($this->columnExists('rateb_inventory', 'reorder_level')) {
            $insert['reorder_level'] = $sourceItem['reorder_level'] ?? 0;
        }
        $insert = array_filter($insert, static fn ($v) => $v !== null);
        $cols = array_keys($insert);
        $ph = array_map(static fn ($c) => ':' . $c, $cols);
        $db->prepare(
            'INSERT INTO rateb_inventory (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')'
        )->execute($insert);
        $newId = (int) $db->lastInsertId();
        $stmt = $db->prepare('SELECT * FROM rateb_inventory WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $newId]);
        $created = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$created) {
            throw new \RuntimeException('Failed to create destination inventory row');
        }
        return $created;
    }

    /** @return array<int,int> */
    private function postInventoryGl(int $companyId, int $transferId, int $sourceBranch, int $destBranch, float $amount): array
    {
        $acct = new AccountingService();
        $inventory = $acct->accountIdByCode($companyId, '1300');
        $dueFrom = $acct->accountIdByCode($companyId, '1350');
        $dueTo = $acct->accountIdByCode($companyId, '2150');
        if (!$inventory || !$dueFrom || !$dueTo) {
            return [];
        }
        $ref = 'IBT-' . $transferId;
        $entryDate = date('Y-m-d');
        $srcId = $this->postBranchJournal(
            $companyId,
            $sourceBranch,
            $transferId,
            [
                ['account_id' => $dueFrom, 'debit' => $amount, 'credit' => 0, 'memo' => 'IB stock ' . $ref],
                ['account_id' => $inventory, 'debit' => 0, 'credit' => $amount, 'memo' => 'IB stock out'],
            ],
            'Inter-branch inventory ' . $ref,
            'تحويل مخزون ' . $ref,
            $entryDate,
            null
        );
        $dstId = $this->postBranchJournal(
            $companyId,
            $destBranch,
            $transferId,
            [
                ['account_id' => $inventory, 'debit' => $amount, 'credit' => 0, 'memo' => 'IB stock in'],
                ['account_id' => $dueTo, 'debit' => 0, 'credit' => $amount, 'memo' => 'IB stock ' . $ref],
            ],
            'Inter-branch inventory ' . $ref,
            'تحويل مخزون ' . $ref,
            $entryDate,
            null
        );
        return [$srcId, $dstId];
    }

    /** @return array<int,int> */
    private function postAssetGl(int $companyId, int $transferId, int $sourceBranch, int $destBranch, float $amount, string $assetName): array
    {
        $acct = new AccountingService();
        $fixedAsset = $acct->accountIdByCode($companyId, '1500') ?: $acct->accountIdByCode($companyId, '1400');
        $dueFrom = $acct->accountIdByCode($companyId, '1350');
        $dueTo = $acct->accountIdByCode($companyId, '2150');
        if (!$fixedAsset || !$dueFrom || !$dueTo) {
            return [];
        }
        $ref = 'IBT-' . $transferId;
        $entryDate = date('Y-m-d');
        $srcId = $this->postBranchJournal(
            $companyId,
            $sourceBranch,
            $transferId,
            [
                ['account_id' => $dueFrom, 'debit' => $amount, 'credit' => 0, 'memo' => $assetName],
                ['account_id' => $fixedAsset, 'debit' => 0, 'credit' => $amount, 'memo' => 'Asset transfer out'],
            ],
            'Inter-branch asset ' . $ref,
            'تحويل أصل ' . $ref,
            $entryDate,
            null
        );
        $dstId = $this->postBranchJournal(
            $companyId,
            $destBranch,
            $transferId,
            [
                ['account_id' => $fixedAsset, 'debit' => $amount, 'credit' => 0, 'memo' => 'Asset transfer in'],
                ['account_id' => $dueTo, 'debit' => 0, 'credit' => $amount, 'memo' => $assetName],
            ],
            'Inter-branch asset ' . $ref,
            'تحويل أصل ' . $ref,
            $entryDate,
            null
        );
        return [$srcId, $dstId];
    }

    /**
     * @param array<int, array{account_id:int,debit:float,credit:float,memo?:string}> $lines
     */
    private function postBranchJournal(
        int $companyId,
        int $branchId,
        int $transferId,
        array $lines,
        string $description,
        string $descriptionAr,
        string $entryDate,
        ?int $createdBy
    ): int {
        $dr = 0.0;
        $cr = 0.0;
        foreach ($lines as $line) {
            $dr += (float) ($line['debit'] ?? 0);
            $cr += (float) ($line['credit'] ?? 0);
        }
        if (round($dr, 2) !== round($cr, 2)) {
            throw new \RuntimeException('Unbalanced inter-branch journal');
        }

        $entryNo = $this->nextEntryNo($companyId);
        $this->enforceInterBranchLedgerMutable($companyId, $entryDate, $branchId);
        $db = Database::connection();
        $entryData = [
            'company_id' => $companyId,
            'entry_no' => $entryNo,
            'entry_date' => $entryDate,
            'description' => $description,
            'description_ar' => $descriptionAr,
            'source_type' => 'branch_transfer',
            'source_id' => $transferId,
            'status' => 'posted',
            'posted_at' => date('Y-m-d H:i:s'),
            'branch_id' => $branchId,
            'created_by' => $createdBy,
        ];
        $cols = array_keys($entryData);
        $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
        $db->prepare(
            'INSERT INTO rateb_journal_entries (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')'
        )->execute($entryData);
        $entryId = (int) $db->lastInsertId();

        $hasBranchLine = $this->columnExists('rateb_journal_lines', 'branch_id');
        $hasCc = $this->columnExists('rateb_journal_lines', 'cost_center_id');
        foreach ($lines as $line) {
            if ($hasBranchLine && $hasCc) {
                $sql = 'INSERT INTO rateb_journal_lines (journal_entry_id, branch_id, account_id, cost_center_id, debit, credit, memo)
                        VALUES (:eid, :bid, :aid, NULL, :dr, :cr, :memo)';
            } elseif ($hasBranchLine) {
                $sql = 'INSERT INTO rateb_journal_lines (journal_entry_id, branch_id, account_id, debit, credit, memo)
                        VALUES (:eid, :bid, :aid, :dr, :cr, :memo)';
            } elseif ($hasCc) {
                $sql = 'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, cost_center_id, debit, credit, memo)
                        VALUES (:eid, :aid, NULL, :dr, :cr, :memo)';
            } else {
                $sql = 'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, debit, credit, memo)
                        VALUES (:eid, :aid, :dr, :cr, :memo)';
            }
            $params = [
                'eid' => $entryId,
                'aid' => (int) $line['account_id'],
                'dr' => $line['debit'],
                'cr' => $line['credit'],
                'memo' => $line['memo'] ?? null,
            ];
            if ($hasBranchLine) {
                $params['bid'] = $branchId;
            }
            $db->prepare($sql)->execute($params);
        }

        return $entryId;
    }

    private function insertStockMovement(
        int $companyId,
        int $inventoryId,
        string $type,
        float $qty,
        int $transferId,
        int $branchId,
        ?int $createdBy,
        string $notes
    ): int {
        $movementModel = new StockMovement();
        $movementNo = $movementModel->generateDocumentCode(DocumentCodeService::PREFIX_MOVEMENT, 'movement_no');
        $data = [
            'company_id' => $companyId,
            'movement_no' => $movementNo,
            'inventory_id' => $inventoryId,
            'movement_type' => $type,
            'quantity' => abs($qty),
            'reference_type' => 'branch_transfer',
            'reference_id' => $transferId,
            'notes' => $notes,
            'created_by' => $createdBy,
        ];
        if ($this->columnExists('rateb_stock_movements', 'branch_id')) {
            $data['branch_id'] = $branchId;
        }
        $cols = array_keys($data);
        $db = Database::connection();
        $ph = array_map(static fn ($c) => ':' . $c, $cols);
        $db->prepare(
            'INSERT INTO rateb_stock_movements (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')'
        )->execute($data);
        return (int) $db->lastInsertId();
    }

    /** @return array<string,mixed> */
    private function lockTransfer(int $transferId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_branch_transfers WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $transferId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException('Transfer not found');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function loadTransfer(int $transferId): array
    {
        $row = (new BranchTransfer())->queryOne('SELECT * FROM rateb_branch_transfers WHERE id = :id LIMIT 1', ['id' => $transferId]);
        if (!$row) {
            throw new \RuntimeException('Transfer not found');
        }
        return $row;
    }

    /** @param array<string,mixed> $transfer @return array<string,mixed> */
    private function transferSnapshot(array $transfer): array
    {
        return [
            'id' => (int) ($transfer['id'] ?? 0),
            'transfer_no' => $transfer['transfer_no'] ?? null,
            'transfer_type' => $transfer['transfer_type'] ?? null,
            'source_branch_id' => (int) ($transfer['source_branch_id'] ?? 0),
            'dest_branch_id' => (int) ($transfer['dest_branch_id'] ?? 0),
            'source_entity_id' => $transfer['source_entity_id'] ?? null,
            'quantity' => $transfer['quantity'] ?? null,
            'amount' => $transfer['amount'] ?? null,
            'status' => $transfer['status'] ?? null,
            'payload_json' => $transfer['payload_json'] ?? null,
        ];
    }

    /** @param array<string,mixed> $fields */
    private function updateTransferRow(int $transferId, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $set = [];
        $params = ['id' => $transferId];
        foreach ($fields as $col => $val) {
            $key = 'f_' . $col;
            $set[] = $col . ' = :' . $key;
            $params[$key] = $val;
        }
        Database::connection()->prepare(
            'UPDATE rateb_branch_transfers SET ' . implode(', ', $set) . ' WHERE id = :id'
        )->execute($params);
    }

    private function markFailed(int $transferId, string $error, int $approvedBy): void
    {
        try {
            $row = $this->loadTransfer($transferId);
            $payload = $this->mergePayloadJson($row, [
                'execution_error' => $error,
                'failed_at' => date('c'),
                'approved_by_attempt' => $approvedBy,
            ]);
            $this->updateTransferRow($transferId, [
                'status' => 'failed',
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            (new AuditService())->logTransfer(
                'inter_branch_transfer_failed',
                $row,
                $this->transferSnapshot($row),
                null,
                $approvedBy,
                ['error' => $error]
            );
        } catch (\Throwable $e) {
            error_log('InterBranchTransfer markFailed: ' . $e->getMessage());
        }
    }

    /** @param array<string,mixed> $transfer @param array<string,mixed> $extra @return array<string,mixed> */
    private function mergePayloadJson(array $transfer, array $extra): array
    {
        $base = $this->decodePayload($transfer);
        return array_merge($base, $extra);
    }

    /** @param array<string,mixed> $transfer @return array<string,mixed> */
    private function decodePayload(array $transfer): array
    {
        $raw = $transfer['payload_json'] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $transfer @param array<string,mixed> $meta */
    private function notifyCompleted(array $transfer, array $meta): void
    {
        try {
            $companyId = (int) ($transfer['company_id'] ?? 0);
            $sourceBranch = (int) ($transfer['source_branch_id'] ?? 0);
            $destBranch = (int) ($transfer['dest_branch_id'] ?? 0);
            $type = (string) ($transfer['transfer_type'] ?? '');
            $no = (string) ($transfer['transfer_no'] ?? '');
            $title = 'Inter-branch transfer completed';
            $message = sprintf(
                '%s %s: branch %d → %d completed.',
                ucfirst($type),
                $no,
                $sourceBranch,
                $destBranch
            );
            $notify = new NotificationService();
            foreach ($this->branchManagerUserIds($companyId, $sourceBranch) as $uid) {
                $notify->notifyUser($uid, $companyId, $title, $message, 'success', 'branch_transfer', 'branch_transfer', (int) $transfer['id']);
            }
            foreach ($this->branchManagerUserIds($companyId, $destBranch) as $uid) {
                $notify->notifyUser($uid, $companyId, $title, $message, 'success', 'branch_transfer', 'branch_transfer', (int) $transfer['id']);
            }
            foreach ($this->hqManagerUserIds($companyId) as $uid) {
                $notify->notifyUser($uid, $companyId, $title, $message, 'info', 'branch_transfer', 'branch_transfer', (int) $transfer['id']);
            }
        } catch (\Throwable $e) {
            error_log('InterBranchTransfer notify: ' . $e->getMessage());
        }
    }

    /** @return array<int,int> */
    private function branchManagerUserIds(int $companyId, int $branchId): array
    {
        if ($companyId < 1 || $branchId < 1 || !$this->tableExists('rateb_user_branches')) {
            return [];
        }
        $db = Database::connection();
        $sql = "SELECT DISTINCT u.id
                FROM rateb_users u
                INNER JOIN rateb_user_roles ur ON ur.user_id = u.id
                INNER JOIN rateb_roles r ON r.id = ur.role_id
                INNER JOIN rateb_user_branches ub ON ub.user_id = u.id
                WHERE u.company_id = :cid AND ub.branch_id = :bid
                  AND r.slug = 'branch_manager' AND u.status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->execute(['cid' => $companyId, 'bid' => $branchId]);
        return array_map('intval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'id'));
    }

    /** @return array<int,int> */
    private function hqManagerUserIds(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $db = Database::connection();
        $sql = "SELECT DISTINCT u.id
                FROM rateb_users u
                WHERE u.company_id = :cid AND u.status = 'active'
                  AND (
                    EXISTS (
                        SELECT 1 FROM rateb_user_roles ur
                        INNER JOIN rateb_roles r ON r.id = ur.role_id
                        WHERE ur.user_id = u.id
                          AND (r.company_id IS NULL OR r.company_id = 0 OR r.company_id = :rcid)
                          AND r.slug IN ('hq_manager', 'hq_admin', 'company-full-access')
                    )
                    OR EXISTS (
                        SELECT 1 FROM rateb_permissions p
                        INNER JOIN rateb_role_permissions rp ON rp.permission_id = p.id
                        INNER JOIN rateb_user_roles ur ON ur.role_id = rp.role_id
                        WHERE ur.user_id = u.id AND p.slug = 'branches.access_all'
                    )
                  )";
        $stmt = $db->prepare($sql);
        $stmt->execute(['cid' => $companyId, 'rcid' => $companyId]);
        return array_map('intval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'id'));
    }

    private function nextEntryNo(int $companyId): string
    {
        $row = (new JournalEntry())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_journal_entries WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;
        return 'JE-' . $companyId . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $stmt = Database::connection()->query("SHOW TABLES LIKE " . Database::connection()->quote($table));
            $cache[$table] = $stmt !== false && $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
            );
            $stmt->execute(['t' => $table, 'c' => $column]);
            $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }

    private function enforceInterBranchLedgerMutable(int $companyId, string $entryDate, int $branchId): void
    {
        $candidates = [];
        if (defined('RATEB_ROOT')) {
            $candidates[] = dirname((string) RATEB_ROOT) . '/app/Accounting/Support/post_accounting_integrity.php';
        }
        $candidates[] = dirname(__DIR__, 3) . '/app/Accounting/Support/post_accounting_integrity.php';

        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            require_once $path;
            if (function_exists('accounting_enforce_ledger_mutable')) {
                accounting_enforce_ledger_mutable($companyId, $entryDate, $branchId, 'create');
            }

            return;
        }
    }
}
