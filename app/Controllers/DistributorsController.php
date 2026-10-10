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

    public function show(array $params): void
    {
        $id = (int) $params['id'];
        $distributor = (new Distributor())->find($id);

        if ($distributor === null) {
            \App\Services\Core\Response::abort(404, 'الموزع غير موجود');

            return;
        }

        $sales = (new Sale())->listing(['distributor_id' => $id], 200);
        $collections = (new Collection())->forDistributor($id);

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
            'title' => 'تفاصيل الموزع',
            'distributor' => $distributor,
            'sales' => $sales,
            'collections' => $collections,
            'activeSalesTotal' => round($activeSalesTotal, 2),
            'paidAtSale' => round($paidAtSale, 2),
            'validCollections' => round($validCollections, 2),
            'balance' => (new Distributor())->balance($id),
        ]);
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
