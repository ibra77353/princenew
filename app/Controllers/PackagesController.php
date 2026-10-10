<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Package;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\Core\Validator;
use App\Services\InventoryService;

final class PackagesController extends Controller
{
    public function index(): void
    {
        $packages = (new Package())->ordered();
        $stock = [];
        foreach (InventoryService::summary() as $row) {
            $stock[(int) $row['package_id']] = $row;
        }

        $this->view('packages/index', [
            'title' => 'الباقات',
            'packages' => $packages,
            'stock' => $stock,
            'threshold' => (int) settings()['low_stock_threshold'],
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            $data = $this->packageData();
            $this->validate($data);

            $id = $this->createPackage([
                'name' => $data['name'],
                'price' => $data['price'],
                'data_mb' => $data['data_mb'],
                'hours' => $data['hours'],
                'color' => $data['color'],
                'status' => $data['status'],
                'description' => $data['description'] !== '' ? $data['description'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit('create', 'package', $id, 'إضافة باقة ' . $data['name']);
        }, '/packages', 'تمت إضافة الباقة بنجاح.');
    }

    public function update(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $package = (new Package())->find($id);
            if ($package === null) {
                throw new \RuntimeException('الباقة غير موجودة.');
            }

            $data = $this->packageData();
            $this->validate($data, $id);

            $this->updatePackage($id, [
                'name' => $data['name'],
                'price' => $data['price'],
                'data_mb' => $data['data_mb'],
                'hours' => $data['hours'],
                'color' => $data['color'],
                'status' => $data['status'],
                'description' => $data['description'] !== '' ? $data['description'] : null,
                'updated_at' => now(),
            ]);

            // تغيير السعر لا يؤثر على الفواتير القديمة لأن الأسعار محفوظة في عناصر الفاتورة
            $this->audit('update', 'package', $id, 'تعديل باقة ' . $data['name']);
        }, '/packages', 'تم تحديث الباقة بنجاح.');
    }

    public function toggle(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $package = (new Package())->find($id);
            if ($package === null) {
                throw new \RuntimeException('الباقة غير موجودة.');
            }

            $newStatus = $package['status'] === 'active' ? 'inactive' : 'active';
            (new Package())->update($id, ['status' => $newStatus, 'updated_at' => now()]);

            $this->audit('toggle', 'package', $id, sprintf(
                '%s الباقة %s',
                $newStatus === 'active' ? 'تفعيل' : 'تعطيل',
                $package['name']
            ));
        }, '/packages', 'تم تحديث حالة الباقة.');
    }

    public function destroy(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $package = (new Package())->find($id);
            if ($package === null) {
                throw new \RuntimeException('الباقة غير موجودة.');
            }

            $references = (int) Database::scalar('SELECT COUNT(*) FROM sale_items WHERE package_id = ?', [$id])
                + (int) Database::scalar('SELECT COUNT(*) FROM inventory_movements WHERE package_id = ?', [$id]);

            if ($references > 0) {
                throw new \RuntimeException('لا يمكن حذف باقة لها مبيعات أو حركات مخزون. عطّلها بدلًا من ذلك.');
            }

            (new Package())->delete($id);
            $this->audit('delete', 'package', $id, 'حذف باقة ' . $package['name']);
        }, '/packages', 'تم حذف الباقة.');
    }

    /** @return array<string,mixed> */
    private function packageData(): array
    {
        return [
            'name' => $this->str('name'),
            'price' => $this->moneyInput('price'),
            'data_mb' => $this->int('data_mb'),
            'hours' => $this->int('hours'),
            'color' => $this->str('color', '#2563eb'),
            'status' => $this->str('status', 'active') === 'inactive' ? 'inactive' : 'active',
            'description' => $this->str('description'),
        ];
    }

    /** @param array<string,mixed> $data */
    private function validate(array $data, ?int $exceptId = null): void
    {
        $validator = (new Validator($data))
            ->required('name', 'اسم الباقة')
            ->numeric('price', 'سعر الباقة', 0)
            ->integer('data_mb', 'حجم البيانات', 0)
            ->integer('hours', 'عدد الساعات', 0);

        if ($validator->fails()) {
            throw new \RuntimeException($validator->firstError());
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $data['color'])) {
            throw new \RuntimeException('اللون يجب أن يكون بصيغة #RRGGBB.');
        }

        $this->assertNameIsUnique((string) $data['name'], $exceptId);
    }

    /**
     * منع تكرار اسم الباقة على مستوى الخادم (المسافات الطرفية تُشذَّب قبل المقارنة).
     * المقارنة تتم بترميز utf8mb4_unicode_ci فلا تفرّق بين حالة الأحرف.
     */
    private function assertNameIsUnique(string $name, ?int $exceptId = null): void
    {
        $name = trim($name);
        $sql = 'SELECT id FROM packages WHERE name = ?';
        $params = [$name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        if (Database::scalar($sql . ' LIMIT 1', $params) !== null) {
            throw new \RuntimeException('توجد باقة بالاسم «' . $name . '» مسبقًا. اختر اسمًا آخر.');
        }
    }

    /** @param array<string,mixed> $data */
    private function createPackage(array $data): int
    {
        try {
            return (new Package())->create($data);
        } catch (\PDOException $e) {
            throw $this->uniquenessError($e);
        }
    }

    /** @param array<string,mixed> $data */
    private function updatePackage(int $id, array $data): void
    {
        try {
            (new Package())->update($id, $data);
        } catch (\PDOException $e) {
            throw $this->uniquenessError($e);
        }
    }

    /** رسالة واضحة عند تصادم قيد عدم التكرار في قاعدة البيانات (حماية من الطلبات المتزامنة). */
    private function uniquenessError(\PDOException $e): \RuntimeException
    {
        if ($e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return new \RuntimeException('توجد باقة بالاسم نفسه مسبقًا. اختر اسمًا آخر.');
        }

        return new \RuntimeException('تعذر حفظ الباقة. تحقق من البيانات وحاول مرة أخرى.');
    }
}
