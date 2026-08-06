<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Repositories;

use Rateb\App\Core\Model;
use Rateb\App\Logistics\Models\LogisticsExpense;

final class LogisticsExpenseRepository extends AbstractLogisticsRepository
{
    protected function newModel(): Model
    {
        return new LogisticsExpense();
    }
}
