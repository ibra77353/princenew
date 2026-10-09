<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CashTransaction;
use App\Services\CashService;
use App\Services\Core\Controller;
use App\Services\Core\Database;

final class CashController extends Controller
{
    public function index(): void
    {
        $source = $this->str('source');
        $from = $this->str('from');
        $to = $this->str('to');

        $model = new CashTransaction();
        $total = (int) Database::scalar('SELECT COUNT(*) FROM cash_transactions');
        $pager = $this->pagination($total, 30);

        $transactions = $model->listing(
            $pager['perPage'],
            $pager['offset'],
            $source !== '' ? $source : null,
            $from,
            $to
        );

        $bySource = Database::all(
            "SELECT source,
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS cash_out
             FROM cash_transactions GROUP BY source ORDER BY source ASC"
        );

        $periodTotals = CashService::totals($from !== '' ? $from : null, $to !== '' ? $to : null);

        $this->view('cash/index', [
            'title' => 'الصندوق',
            'transactions' => $transactions,
            'sources' => CashTransaction::SOURCES,
            'bySource' => $bySource,
            'filters' => ['source' => $source, 'from' => $from, 'to' => $to],
            'pager' => $pager,
            'openingBalance' => CashService::openingBalance(),
            'periodTotals' => $periodTotals,
            'balance' => CashService::balance(),
            'allTotals' => CashService::totals(),
        ]);
    }
}
