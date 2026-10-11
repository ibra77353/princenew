<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\OwnerWithdrawal;
use App\Services\CashService;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\OutflowService;

final class OwnerWithdrawalsController extends Controller
{
    public function index(): void
    {
        ['from' => $from, 'to' => $to, 'error' => $rangeError] = $this->dateRange();

        $model = new OwnerWithdrawal();
        $pager = $this->pagination($model->countFiltered($from, $to), 25);

        $this->view('owner_withdrawals/index', [
            'title' => 'سحوبات المالك',
            'withdrawals' => $model->listing($pager['perPage'], $pager['offset'], $from, $to),
            'filters' => ['from' => $from, 'to' => $to],
            'pager' => $pager,
            'total' => $model->total($from, $to),
            'cashBalance' => CashService::balance(),
            'rangeError' => $rangeError,
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            OutflowService::recordWithdrawal(
                $this->moneyInput('amount'),
                $this->str('reason'),
                $this->str('withdrawal_date'),
                $this->str('notes'),
                $this->requestId()
            );
        }, '/owner-withdrawals', 'تم تسجيل السحب وخصمه من الصندوق.');
    }
}
