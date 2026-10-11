<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CashTransaction;
use App\Services\CashService;
use App\Services\Core\Controller;

final class CashController extends Controller
{
    public function index(): void
    {
        $source = $this->str('source');
        ['from' => $from, 'to' => $to, 'error' => $rangeError] = $this->dateRange();
        $sourceFilter = $source !== '' ? $source : null;

        $model = new CashTransaction();
        // العدد والإجماليات والتفصيل حسب المصدر من نفس فلاتر التصفية (المصدر + الفترة)،
        // والأرقام مستبعدة أثر العمليات الملغاة (كما في المبيعات والتحصيلات) بلا حذف من السجل.
        $summary = $model->summary($sourceFilter, $from, $to);
        $allSummary = $model->summary();
        $pager = $this->pagination($summary['count'], 30);

        $transactions = $model->listing($pager['perPage'], $pager['offset'], $sourceFilter, $from, $to);

        $this->view('cash/index', [
            'title' => 'الصندوق',
            'transactions' => $transactions,
            'sources' => CashTransaction::SOURCES,
            'bySource' => $summary['bySource'],
            'filters' => ['source' => $source, 'from' => $from, 'to' => $to],
            'pager' => $pager,
            'openingBalance' => CashService::openingBalance(),
            'periodTotals' => ['in' => $summary['in'], 'out' => $summary['out']],
            'balance' => CashService::balance(),
            'allTotals' => ['in' => $allSummary['in'], 'out' => $allSummary['out']],
            'rangeError' => $rangeError,
        ]);
    }
}
