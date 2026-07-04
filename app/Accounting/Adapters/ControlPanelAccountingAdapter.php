<?php
declare(strict_types=1);

namespace App\Accounting\Adapters;

use App\Accounting\Contracts\AccountingAdapterInterface;
use App\Accounting\Core\AccountingResult;

/**
 * Writes to control_chart_accounts / control_journal_entries / control_journal_entry_lines.
 * Gateway-first path mirrors control-panel/api/control/accounting.php journal_entry_create flow.
 */
final class ControlPanelAccountingAdapter implements AccountingAdapterInterface
{
    public function supports(string $sourceSystem): bool
    {
        return $sourceSystem === 'control-panel';
    }

    /**
     * @param array<string, mixed> $event
     */
    public function post(array $event): AccountingResult
    {
        if (!empty($event['metadata']['legacy_write'])) {
            return AccountingResult::ok([
                'mode' => 'acknowledged',
                'source_system' => 'control-panel',
                'journal_entry_id' => $event['metadata']['journal_entry_id'] ?? null,
                'reference' => $event['metadata']['reference'] ?? null,
                'reference_type' => $event['reference_type'],
                'reference_id' => $event['reference_id'],
            ], 'Legacy write acknowledged — no duplicate control-panel post');
        }

        $ctrl = $event['metadata']['mysqli'] ?? null;
        if (!$ctrl instanceof \mysqli) {
            return AccountingResult::fail('metadata.mysqli (control connection) required for gateway-first control-panel write');
        }

        $amount = round((float) $event['amount'], 2);
        if ($amount <= 0) {
            return AccountingResult::fail('amount must be greater than zero');
        }

        $entryDate = (string) ($event['metadata']['entry_date'] ?? date('Y-m-d'));
        $description = (string) ($event['metadata']['description'] ?? 'Gateway journal');
        $countryId = (int) ($event['metadata']['country_id'] ?? 0);
        $debitCode = (string) $event['debit_account'];
        $creditCode = (string) $event['credit_account'];

        $debitLine = $this->resolveChartAccount($ctrl, $debitCode);
        $creditLine = $this->resolveChartAccount($ctrl, $creditCode);

        if ($debitLine === null || $creditLine === null) {
            return AccountingResult::fail('Could not resolve control_chart_accounts for debit/credit codes');
        }

        $reference = (string) ($event['metadata']['reference'] ?? ('GL-GW-' . date('YmdHis')));

        $useTx = @$ctrl->begin_transaction();
        try {
            $stInsert = $ctrl->prepare(
                'INSERT INTO control_journal_entries (agency_id, country_id, reference, entry_date, description, total_debit, total_credit, status) VALUES (0,?,?,?,?,?,?,?)'
            );
            if ($stInsert === false) {
                throw new \RuntimeException('prepare journal header failed');
            }
            $status = 'draft';
            $stInsert->bind_param('isssdds', $countryId, $reference, $entryDate, $description, $amount, $amount, $status);
            if (!$stInsert->execute()) {
                throw new \RuntimeException('insert journal header failed');
            }
            $jid = (int) $ctrl->insert_id;
            $stInsert->close();

            $this->insertLine($ctrl, $jid, $debitLine, $amount, 0.0, $description);
            $this->insertLine($ctrl, $jid, $creditLine, 0.0, $amount, $description);

            if ($useTx) {
                $ctrl->commit();
            }

            return AccountingResult::ok([
                'mode' => 'gateway_write',
                'source_system' => 'control-panel',
                'journal_entry_id' => $jid,
                'reference' => $reference,
            ], 'Posted to control_journal_entries');
        } catch (\Throwable $e) {
            if ($useTx) {
                $ctrl->rollback();
            }

            return AccountingResult::fail('Control panel journal insert failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array{account_id:?int, account_code:string, account_name:string}|null
     */
    private function resolveChartAccount(\mysqli $ctrl, string $codeOrName): ?array
    {
        $codeOrName = trim($codeOrName);
        if ($codeOrName === '') {
            return null;
        }

        $st = $ctrl->prepare(
            'SELECT id, account_code, account_name FROM control_chart_accounts WHERE account_code = ? OR account_name = ? LIMIT 1'
        );
        if ($st === false) {
            return ['account_id' => null, 'account_code' => $codeOrName, 'account_name' => $codeOrName];
        }
        $st->bind_param('ss', $codeOrName, $codeOrName);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();

        if (!$row) {
            return ['account_id' => null, 'account_code' => $codeOrName, 'account_name' => $codeOrName];
        }

        return [
            'account_id' => (int) $row['id'],
            'account_code' => (string) $row['account_code'],
            'account_name' => (string) $row['account_name'],
        ];
    }

    /**
     * @param array{account_id:?int, account_code:string, account_name:string} $account
     */
    private function insertLine(\mysqli $ctrl, int $journalId, array $account, float $debit, float $credit, string $lineDesc): void
    {
        if ($account['account_id'] !== null) {
            $st = $ctrl->prepare(
                'INSERT INTO control_journal_entry_lines (journal_entry_id, account_id, account_code, account_name, debit, credit, description) VALUES (?,?,?,?,?,?,?)'
            );
            $accId = $account['account_id'];
            $st->bind_param('iissdds', $journalId, $accId, $account['account_code'], $account['account_name'], $debit, $credit, $lineDesc);
        } else {
            $st = $ctrl->prepare(
                'INSERT INTO control_journal_entry_lines (journal_entry_id, account_id, account_code, account_name, debit, credit, description) VALUES (?,NULL,?,?,?,?,?)'
            );
            $st->bind_param('issdds', $journalId, $account['account_code'], $account['account_name'], $debit, $credit, $lineDesc);
        }
        if ($st === false || !$st->execute()) {
            throw new \RuntimeException('insert journal line failed');
        }
        $st->close();
    }
}
