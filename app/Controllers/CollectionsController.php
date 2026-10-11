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
        ['from' => $from, 'to' => $to, 'error' => $rangeError] = $this->dateRange();
        $distributorFilter = $distributorId > 0 ? $distributorId : null;

        $model = new Collection();
        // الإجماليات وعدد السجلات يُحسبان من نفس فلاتر التصفية، فلا يختلف المعروض عن الفعلي.
        $totals = $model->totals($distributorFilter, $from, $to);
        $pager = $this->pagination($totals['count'], 25);

        $collections = $model->listing($pager['perPage'], $pager['offset'], $distributorFilter, $from, $to);

        $this->view('collections/index', [
            'title' => 'التحصيلات',
            'collections' => $collections,
            'distributors' => (new Distributor())->active(),
            'outstanding' => $this->outstandingSales(),
            'filters' => ['distributor_id' => $distributorId, 'from' => $from, 'to' => $to],
            'pager' => $pager,
            'totals' => ['valid' => $totals['valid'], 'reversed' => $totals['reversed']],
            'rangeError' => $rangeError,
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
                'request_id' => $this->requestId(),
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
