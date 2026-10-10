<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Distributor;
use App\Models\Package;
use App\Models\Sale;
use App\Services\Core\Controller;
use App\Services\Core\Response;
use App\Services\SaleService;

final class SalesController extends Controller
{
    public function index(): void
    {
        $filters = [
            'term' => $this->str('q'),
            'from' => $this->str('from'),
            'to' => $this->str('to'),
            'status' => $this->str('status'),
        ];

        $model = new Sale();
        $total = $model->countFiltered($filters);
        $pager = $this->pagination($total, 25);
        $sales = $model->listing($filters, $pager['perPage'], $pager['offset']);

        $sums = \App\Services\Core\Database::one(
            "SELECT COALESCE(SUM(total_amount), 0) AS total, COALESCE(SUM(paid_amount), 0) AS paid
             FROM sales WHERE status = 'active'"
        ) ?? [];

        $this->view('sales/index', [
            'title' => 'المبيعات',
            'sales' => $sales,
            'filters' => $filters,
            'pager' => $pager,
            'total' => $total,
            'totals' => [
                'all' => round((float) ($sums['total'] ?? 0), 2),
                'paid' => round((float) ($sums['paid'] ?? 0), 2),
            ],
        ]);
    }

    public function create(): void
    {
        $this->view('sales/create', [
            'title' => 'عملية بيع جديدة',
            'distributors' => (new Distributor())->active(),
            'packages' => (new Package())->active(),
            'stock' => \App\Services\InventoryService::summary(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $packageIds = $this->array('item_package_id');
        $quantities = $this->array('item_quantity');

        $items = [];
        foreach ($packageIds as $index => $packageId) {
            $items[] = [
                'package_id' => $packageId,
                'quantity' => $quantities[$index] ?? null,
            ];
        }

        $this->action(function () use ($items): string {
            $sale = SaleService::create([
                'distributor_id' => $this->int('distributor_id'),
                'items' => $items,
                'paid_amount' => $this->moneyInput('paid_amount'),
                'payment_type' => $this->str('payment_type', 'cash'),
                'sale_date' => $this->str('sale_date'),
                'notes' => $this->str('notes'),
            ]);

            return '/sales/' . $sale['id'];
        }, '/sales/create');
    }

    public function show(array $params): void
    {
        $id = (int) $params['id'];
        $sale = (new Sale())->withItems($id);

        if ($sale === null) {
            Response::abort(404, 'الفاتورة غير موجودة');

            return;
        }

        $this->view('sales/show', [
            'title' => 'الفاتورة ' . $sale['invoice_number'],
            'sale' => $sale,
            'remaining' => (new Sale())->remaining($id),
            'settings' => settings(),
        ]);
    }

    public function cancel(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            SaleService::cancel($id, $this->str('cancel_reason'));
        }, '/sales', 'تم إلغاء الفاتورة وتسجيل الحركات العكسية.');
    }
}
