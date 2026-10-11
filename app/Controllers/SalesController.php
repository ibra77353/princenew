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
        ['from' => $from, 'to' => $to, 'error' => $rangeError] = $this->dateRange();

        $filters = [
            'term' => $this->str('q'),
            'from' => $from,
            'to' => $to,
            // الحالة الافتراضية «نشطة»، و«الكل» قيمة صالحة (تُرسل فارغة) — كانت القائمة
            // تجمع الفواتير الملغاة داخل بطاقات معنونة «النشطة» فتظهر إجماليات عُكست لاحقًا.
            // لا تُستخدم str() هنا لأنها تُرجع القيمة الافتراضية عند الفراغ فلا يمكن تمثيل «الكل».
            'status' => self::normalizeStatus($_GET['status'] ?? null),
        ];

        $model = new Sale();
        // إجماليات مطابقة لنفس فلاتر التصفية (لا تجمع كل المبيعات النشطة بغضّ النظر عن الفلاتر).
        $totals = $model->totalsFiltered($filters);
        $pager = $this->pagination($totals['count'], 25);
        $sales = $model->listing($filters, $pager['perPage'], $pager['offset']);

        $this->view('sales/index', [
            'title' => 'المبيعات',
            'sales' => $sales,
            'filters' => $filters,
            'pager' => $pager,
            'total' => $totals['count'],
            'totals' => [
                'all' => $totals['total'],
                'paid' => $totals['paid'],
            ],
            'rangeError' => $rangeError,
        ]);
    }

    /**
     * تطبيع تصفية الحالة: الافتراضي «نشطة» عند غياب المعامل، و«الكل» تُرسل فارغة،
     * وأي قيمة غير معروفة تُعامل كـ«الكل» بلا تصفية.
     */
    private static function normalizeStatus(mixed $value): string
    {
        if ($value === null) {
            return 'active';
        }

        $status = trim((string) $value);

        return in_array($status, ['active', 'cancelled'], true) ? $status : '';
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
                'request_id' => $this->requestId(),
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
