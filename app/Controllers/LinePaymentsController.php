<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Line;
use App\Models\LinePayment;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\OutflowService;

final class LinePaymentsController extends Controller
{
    public function index(): void
    {
        $lineId = $this->int('line_id');
        $from = $this->str('from');
        $to = $this->str('to');

        $total = (int) Database::scalar('SELECT COUNT(*) FROM line_payments');
        $pager = $this->pagination($total, 25);

        $payments = (new LinePayment())->listing(
            $pager['perPage'],
            $pager['offset'],
            $lineId > 0 ? $lineId : null,
            $from,
            $to
        );

        $sum = (float) Database::scalar('SELECT COALESCE(SUM(amount), 0) FROM line_payments');

        $this->view('line_payments/index', [
            'title' => 'دفعات الخطوط',
            'payments' => $payments,
            'lines' => (new Line())->listing(),
            'filters' => ['line_id' => $lineId, 'from' => $from, 'to' => $to],
            'pager' => $pager,
            'sum' => round($sum, 2),
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
                $this->str('notes')
            );
        }, '/line-payments', 'تم تسجيل دفعة الخط وخصمها من الصندوق.');
    }
}
