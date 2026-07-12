<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOStatement;

/**
 * Phase C — PDOStatement that captures mutations into the hybrid outbox.
 */
class HybridSyncPdoStatement extends PDOStatement
{
    private string $originalSql = '';
    private ?SqliteCompatPdo $owner = null;

    protected function __construct()
    {
    }

    public function bindOwner(SqliteCompatPdo $owner, string $originalSql): void
    {
        $this->owner = $owner;
        $this->originalSql = $originalSql;
    }

    public function execute(?array $params = null): bool
    {
        $ok = parent::execute($params);
        if ($ok && $this->owner instanceof SqliteCompatPdo && $this->originalSql !== '') {
            HybridSyncOutboxCapture::afterMutate($this->owner, $this->originalSql, $params);
        }

        return $ok;
    }
}
