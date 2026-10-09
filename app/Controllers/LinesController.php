<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Line;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\Core\Response;
use App\Services\Core\Validator;

final class LinesController extends Controller
{
    public function index(): void
    {
        $lines = (new Line())->listing();

        $paidByLine = [];
        foreach (Database::all(
            'SELECT line_id, COALESCE(SUM(amount), 0) AS paid, COUNT(*) AS payments, MAX(payment_date) AS last_payment
             FROM line_payments GROUP BY line_id'
        ) as $row) {
            $paidByLine[(int) $row['line_id']] = $row;
        }

        $this->view('lines/index', [
            'title' => 'خطوط الإنترنت',
            'lines' => $lines,
            'paidByLine' => $paidByLine,
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            $data = $this->lineData();
            $this->validate($data);

            $id = (new Line())->create([
                'name' => $data['name'],
                'provider' => $data['provider'] !== '' ? $data['provider'] : null,
                'identifier' => $data['identifier'] !== '' ? $data['identifier'] : null,
                'speed' => $data['speed'] !== '' ? $data['speed'] : null,
                'subscription_cost' => $data['subscription_cost'],
                'subscription_date' => $data['subscription_date'] !== '' ? $data['subscription_date'] : null,
                'status' => $data['status'],
                'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit('create', 'line', $id, 'إضافة خط ' . $data['name']);
        }, '/lines', 'تمت إضافة الخط بنجاح.');
    }

    public function update(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $line = (new Line())->find($id);
            if ($line === null) {
                throw new \RuntimeException('الخط غير موجود.');
            }

            $data = $this->lineData();
            $this->validate($data);

            (new Line())->update($id, [
                'name' => $data['name'],
                'provider' => $data['provider'] !== '' ? $data['provider'] : null,
                'identifier' => $data['identifier'] !== '' ? $data['identifier'] : null,
                'speed' => $data['speed'] !== '' ? $data['speed'] : null,
                'subscription_cost' => $data['subscription_cost'],
                'subscription_date' => $data['subscription_date'] !== '' ? $data['subscription_date'] : null,
                'status' => $data['status'],
                'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                'updated_at' => now(),
            ]);

            $this->audit('update', 'line', $id, 'تعديل خط ' . $data['name']);
        }, '/lines', 'تم تحديث بيانات الخط.');
    }

    public function destroy(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $line = (new Line())->find($id);
            if ($line === null) {
                throw new \RuntimeException('الخط غير موجود.');
            }

            $payments = (int) Database::scalar('SELECT COUNT(*) FROM line_payments WHERE line_id = ?', [$id]);
            if ($payments > 0) {
                throw new \RuntimeException('لا يمكن حذف خط له دفعات مسجلة. عطّله بدلًا من ذلك.');
            }

            (new Line())->delete($id);
            $this->audit('delete', 'line', $id, 'حذف خط ' . $line['name']);
        }, '/lines', 'تم حذف الخط.');
    }

    public function show(array $params): void
    {
        $id = (int) $params['id'];
        $line = (new Line())->withPayments($id);

        if ($line === null) {
            Response::abort(404, 'الخط غير موجود');

            return;
        }

        $this->view('lines/show', [
            'title' => 'تفاصيل الخط',
            'line' => $line,
        ]);
    }

    /** @return array<string,mixed> */
    private function lineData(): array
    {
        return [
            'name' => $this->str('name'),
            'provider' => $this->str('provider'),
            'identifier' => $this->str('identifier'),
            'speed' => $this->str('speed'),
            'subscription_cost' => $this->moneyInput('subscription_cost'),
            'subscription_date' => $this->str('subscription_date'),
            'status' => $this->str('status', 'active') === 'inactive' ? 'inactive' : 'active',
            'notes' => $this->str('notes'),
        ];
    }

    /** @param array<string,mixed> $data */
    private function validate(array $data): void
    {
        $validator = (new Validator($data))
            ->required('name', 'اسم الخط')
            ->numeric('subscription_cost', 'تكلفة الاشتراك', 0)
            ->date('subscription_date', 'تاريخ الاشتراك');

        if ($validator->fails()) {
            throw new \RuntimeException($validator->firstError());
        }
    }
}
