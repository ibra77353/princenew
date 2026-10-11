<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Expense;
use App\Services\CashService;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\OutflowService;

final class ExpensesController extends Controller
{
    public function index(): void
    {
        $category = $this->str('category');
        $from = $this->str('from');
        $to = $this->str('to');

        $model = new Expense();
        $total = (int) Database::scalar('SELECT COUNT(*) FROM expenses');
        $pager = $this->pagination($total, 25);

        $expenses = $model->listing($pager['perPage'], $pager['offset'], $category !== '' ? $category : null, $from, $to);

        $byCategory = Database::all(
            'SELECT category, COALESCE(SUM(amount), 0) AS total FROM expenses GROUP BY category ORDER BY total DESC'
        );

        $this->view('expenses/index', [
            'title' => 'المصروفات',
            'expenses' => $expenses,
            'categories' => Expense::CATEGORIES,
            'byCategory' => $byCategory,
            'filters' => ['category' => $category, 'from' => $from, 'to' => $to],
            'pager' => $pager,
            'total' => $model->total($from !== '' ? $from : null, $to !== '' ? $to : null),
            'cashBalance' => CashService::balance(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            OutflowService::recordExpense(
                $this->str('category', 'other'),
                $this->moneyInput('amount'),
                $this->str('description'),
                $this->str('expense_date'),
                $this->str('notes')
            );
        }, '/expenses', 'تم تسجيل المصروف وخصمه من الصندوق.');
    }
}
