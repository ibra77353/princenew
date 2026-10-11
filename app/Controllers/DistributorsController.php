<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Collection;
use App\Models\Distributor;
use App\Models\Sale;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\Core\Validator;

final class DistributorsController extends Controller
{
    public function index(): void
    {
        $model = new Distributor();
        $term = $this->str('q');

        $rows = $term !== ''
            ? array_values(array_filter(
                $model->balances(),
                static fn (array $row): bool => mb_stripos((string) $row['name'], $term) !== false
                    || mb_stripos((string) $row['phone'], $term) !== false
            ))
            : $model->balances();

        $totalDebt = 0.0;
        foreach ($rows as $row) {
            if ((float) $row['balance'] > 0) {
                $totalDebt += (float) $row['balance'];
            }
        }

        $this->view('distributors/index', [
            'title' => 'الموزعون',
            'distributors' => $rows,
            'term' => $term,
            'totalDebt' => round($totalDebt, 2),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            $data = $this->distributorData();
            $this->validate($data);

            $id = (new Distributor())->create([
                'name' => $data['name'],
                'phone' => $data['phone'] !== '' ? $data['phone'] : null,
                'address' => $data['address'] !== '' ? $data['address'] : null,
                'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                'status' => $data['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit('create', 'distributor', $id, 'إضافة موزع ' . $data['name']);
        }, '/distributors', 'تمت إضافة الموزع بنجاح.');
    }

    public function update(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $distributor = (new Distributor())->find($id);
            if ($distributor === null) {
                throw new \RuntimeException('الموزع غير موجود.');
            }

            $data = $this->distributorData();
            $this->validate($data);

            (new Distributor())->update($id, [
                'name' => $data['name'],
                'phone' => $data['phone'] !== '' ? $data['phone'] : null,
                'address' => $data['address'] !== '' ? $data['address'] : null,
                'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                'status' => $data['status'],
                'updated_at' => now(),
            ]);

            $this->audit('update', 'distributor', $id, 'تعديل موزع ' . $data['name']);
        }, '/distributors', 'تم تحديث بيانات الموزع.');
    }

    public function destroy(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $distributor = (new Distributor())->find($id);
            if ($distributor === null) {
                throw new \RuntimeException('الموزع غير موجود.');
            }

            $sales = (int) Database::scalar('SELECT COUNT(*) FROM sales WHERE distributor_id = ?', [$id]);
            $collections = (int) Database::scalar('SELECT COUNT(*) FROM collections WHERE distributor_id = ?', [$id]);

            if ($sales > 0 || $collections > 0) {
                throw new \RuntimeException('لا يمكن حذف موزع لديه فواتير أو تحصيلات. يمكن تعطيله للحفاظ على تاريخه المالي.');
            }

            (new Distributor())->delete($id);
            $this->audit('delete', 'distributor', $id, 'حذف موزع ' . $distributor['name']);
        }, '/distributors', 'تم حذف الموزع.');
    }

    /** كشف حساب موزع (يُفتح من بطاقة الموزع في القائمة). */
    public function show(array $params): void
    {
        $this->renderStatement((int) $params['id']);
    }

    /** شاشة «كشف حساب موزع»: اختيار اسم الموزع والفترة من مكان واحد ثم عرض الكشف وطباعته. */
    public function statement(): void
    {
        $this->renderStatement($this->int('distributor_id'));
    }

    /** مستند الكشف: شاشة اختيار فقط قبل تحديد الموزع، ويكتمل بالبيانات والفترة بعده. */
    private function renderStatement(int $id): void
    {
        $model = new Distributor();
        $distributor = $id > 0 ? $model->find($id) : null;

        if ($id > 0 && $distributor === null) {
            \App\Services\Core\Response::abort(404, 'الموزع غير موجود');

            return;
        }

        // كشف الحساب: الفواتير والتحصيلات تُحصر بالفترة المختارة (المجموع والصفوف معًا)،
        // أما «الرصيد المستحق» فيبقى رصيد الموزع الحالي على كل الفترات لأن الدين لا يتوقّف عند حدّ الفترة.
        ['from' => $from, 'to' => $to, 'error' => $rangeError] = $this->dateRange();

        $sales = $distributor === null
            ? []
            : (new Sale())->listing(['distributor_id' => $id, 'from' => $from, 'to' => $to], 200);
        $collections = $distributor === null ? [] : (new Collection())->forDistributor($id, $from, $to);

        $activeSalesTotal = 0.0;
        $paidAtSale = 0.0;
        foreach ($sales as $sale) {
            if ($sale['status'] === 'active') {
                $activeSalesTotal += (float) $sale['total_amount'];
                $paidAtSale += (float) $sale['paid_amount'];
            }
        }

        $validCollections = 0.0;
        foreach ($collections as $collection) {
            if ($collection['status'] === 'active') {
                $validCollections += (float) $collection['amount'];
            }
        }

        $this->view('distributors/show', [
            'title' => $distributor === null ? 'كشف حساب موزع' : 'كشف حساب ' . $distributor['name'],
            'distributor' => $distributor,
            'distributors' => $this->statementOptions($model, $distributor),
            'sales' => $sales,
            'collections' => $collections,
            'activeSalesTotal' => round($activeSalesTotal, 2),
            'paidAtSale' => round($paidAtSale, 2),
            'validCollections' => round($validCollections, 2),
            'balance' => $distributor === null ? 0.0 : $model->balance($id),
            'filters' => ['from' => $from, 'to' => $to],
            'rangeError' => $rangeError,
        ]);
    }

    /**
     * قائمة اختيار الموزع في شاشة الكشف: الموزعون النشطون، ويُضاف الموزع الحالي إن كان معطّلًا
     * حتى لا يختفي من الاختيار عند فتح كشفه.
     * @return array<int,array<string,mixed>>
     */
    private function statementOptions(Distributor $model, ?array $current): array
    {
        $options = $model->active();

        if ($current === null) {
            return $options;
        }

        foreach ($options as $option) {
            if ((int) $option['id'] === (int) $current['id']) {
                return $options;
            }
        }

        $options[] = $current;

        return $options;
    }

    /** @return array<string,string> */
    private function distributorData(): array
    {
        return [
            'name' => $this->str('name'),
            'phone' => $this->str('phone'),
            'address' => $this->str('address'),
            'notes' => $this->str('notes'),
            'status' => $this->str('status', 'active') === 'inactive' ? 'inactive' : 'active',
        ];
    }

    /** @param array<string,string> $data */
    private function validate(array $data): void
    {
        $validator = (new Validator($data))->required('name', 'اسم الموزع');
        if ($validator->fails()) {
            throw new \RuntimeException($validator->firstError());
        }
    }
}
