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
        $from = $this->str('from');
        $to = $this->str('to');

        $model = new OwnerWithdrawal();
        $total = (int) Database::scalar('SELECT COUNT(*) FROM owner_withdrawals');
        $pager = $this->pagination($total, 25);

        $this->view('owner_withdrawals/index', [
            'title' => 'سحوبات المالك',
            'withdrawals' => $model->listing($pager['perPage'], $pager['offset'], $from, $to),
            'filters' => ['from' => $from, 'to' => $to],
            'pager' => $pager,
            'total' => $model->total($from !== '' ? $from : null, $to !== '' ? $to : null),
            'cashBalance' => CashService::balance(),
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
                $this->str('notes')
            );
        }, '/owner-withdrawals', 'تم تسجيل السحب وخصمه من الصندوق.');
    }
}
