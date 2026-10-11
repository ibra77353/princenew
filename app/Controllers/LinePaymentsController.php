<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Line;
use App\Models\LinePayment;
use App\Services\CashService;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\OutflowService;

final class LinePaymentsController extends Controller
{
    public function index(): void
    {
        $lineId = $this->int('line_id');
        ['from' => $from, 'to' => $to, 'error' => $rangeError] = $this->dateRange();
        $lineFilter = $lineId > 0 ? $lineId : null;

        $model = new LinePayment();
        // العدد والإجمالي من نفس فلاتر التصفية (الخط + الفترة).
        $sums = $model->sums($lineFilter, $from, $to);
        $pager = $this->pagination($sums['count'], 25);

        $payments = $model->listing($pager['perPage'], $pager['offset'], $lineFilter, $from, $to);

        $this->view('line_payments/index', [
            'title' => 'دفعات الخطوط',
            'payments' => $payments,
            'lines' => (new Line())->listing(),
            'filters' => ['line_id' => $lineId, 'from' => $from, 'to' => $to],
            'pager' => $pager,
            'sum' => $sums['total'],
            'cashBalance' => CashService::balance(),
            'rangeError' => $rangeError,
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            OutflowService::recordLinePayment(
                $this->int('line_id'),
                $this->moneyInput('amount'),
                $this->str('payment_date'),
                $this->str('notes'),
                $this->requestId()
            );
        }, '/line-payments', 'تم تسجيل دفعة الخط وخصمها من الصندوق.');
    }
}
