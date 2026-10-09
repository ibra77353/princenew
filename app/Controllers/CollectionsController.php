<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Collection;
use App\Models\Distributor;
use App\Models\Sale;
use App\Services\CollectionService;
use App\Services\Core\Controller;

final class CollectionsController extends Controller
{
    public function index(): void
    {
        $distributorId = $this->int('distributor_id');
        $from = $this->str('from');
        $to = $this->str('to');

        $model = new Collection();
        $total = (int) \App\Services\Core\Database::scalar('SELECT COUNT(*) FROM collections');
        $pager = $this->pagination($total, 25);

        $collections = $model->listing($pager['perPage'], $pager['offset'], $distributorId > 0 ? $distributorId : null, $from, $to);

        $validTotal = (float) \App\Services\Core\Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM collections WHERE status = 'active'"
        );
        $reversedTotal = (float) \App\Services\Core\Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM collections WHERE status = 'reversed'"
        );

        $this->view('collections/index', [
            'title' => 'التحصيلات',
            'collections' => $collections,
            'distributors' => (new Distributor())->active(),
            'outstanding' => $this->outstandingSales(),
            'filters' => ['distributor_id' => $distributorId, 'from' => $from, 'to' => $to],
            'pager' => $pager,
            'totals' => ['valid' => round($validTotal, 2), 'reversed' => round($reversedTotal, 2)],
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            CollectionService::create([
                'distributor_id' => $this->int('distributor_id'),
                'sale_id' => $this->int('sale_id'),
                'amount' => $this->moneyInput('amount'),
                'collection_date' => $this->str('collection_date'),
                'notes' => $this->str('notes'),
            ]);
        }, '/collections', 'تم تسجيل التحصيل بنجاح.');
    }

    /** فواتير نشطة عليها مبلغ متبقٍ لكل موزع (تُستخدم في القائمة المنسدلة). */
    public function outstanding(): void
    {
        $distributorId = $this->int('distributor_id');

        if ($distributorId <= 0) {
            $this->json(['sales' => [], 'balance' => 0]);
        }

        $this->json([
            'sales' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'invoice_number' => $row['invoice_number'],
                'remaining' => round((float) $row['remaining'], 2),
                'sale_date' => $row['sale_date'],
            ], (new Sale())->outstandingForDistributor($distributorId)),
            'balance' => (new Distributor())->balance($distributorId),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function outstandingSales(): array
    {
        return \App\Services\Core\Database::all(
            "SELECT s.id, s.invoice_number, s.distributor_id, s.sale_date,
                (s.total_amount - s.paid_amount
                 - COALESCE((SELECT SUM(c.amount) FROM collections c WHERE c.sale_id = s.id AND c.status = 'active'), 0)) AS remaining
             FROM sales s
             WHERE s.status = 'active'
             HAVING remaining > 0
             ORDER BY s.sale_date ASC, s.id ASC LIMIT 500"
        );
    }
}
