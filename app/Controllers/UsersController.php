<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\Core\Auth;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\Core\Validator;

/**
 * إدارة المستخدمين والأدوار — للمدير فقط (وسيط «admin» في الموجّه، فرض على الخادم).
 *
 * دورَان لا ثالث لهما:
 *   - admin    : صلاحية كاملة (إعدادات، نسخ احتياطي، مستخدمون، حذف السجلات الحساسة).
 *   - employee : تشغيل محدود (مبيعات نقدية وآجلة، تحصيلات، مخزون، خطوط).
 *
 * قواعد الحماية الذاتية: لا يغيّر المدير دوره، ولا يعطّل حسابه، ولا يحذفه،
 * ولا يمكن إزالة دور المدير عن آخر حساب مدير نشط.
 */
final class UsersController extends Controller
{
    /** @var array<int,string> */
    public const ROLES = [Auth::ROLE_ADMIN, Auth::ROLE_EMPLOYEE];

    /** @var array<int,string> */
    private const STATUSES = ['active', 'disabled'];

    public function index(): void
    {
        $users = Database::all(
            'SELECT id, name, email, username, role, status, last_login_at, created_at
             FROM users ORDER BY id ASC'
        );

        $this->view('users/index', [
            'title' => 'المستخدمون',
            'users' => $users,
            'counts' => [
                'total' => count($users),
                'admins' => count(array_filter($users, static fn (array $u): bool => $u['role'] === Auth::ROLE_ADMIN)),
                'employees' => count(array_filter($users, static fn (array $u): bool => $u['role'] !== Auth::ROLE_ADMIN)),
                'disabled' => count(array_filter($users, static fn (array $u): bool => $u['status'] !== 'active')),
            ],
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            $data = $this->userData();
            $this->validate($data, null);

            if ($data['password'] === '') {
                throw new \RuntimeException('كلمة المرور مطلوبة للمستخدم الجديد.');
            }

            try {
                $id = (new User())->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'username' => $data['username'] !== '' ? $data['username'] : null,
                    'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'role' => $data['role'],
                    'status' => $data['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\PDOException $e) {
                throw $this->uniquenessError($e);
            }

            $this->audit('create', 'user', $id, sprintf(
                'إضافة مستخدم %s بدور «%s»',
                $data['name'],
                role_label($data['role'])
            ));
        }, '/users', 'تمت إضافة المستخدم بنجاح.');
    }

    public function update(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $user = (new User())->find($id);
            if ($user === null) {
                throw new \RuntimeException('المستخدم غير موجود.');
            }

            $data = $this->userData();
            $this->validate($data, $id);
            $this->assertSafeChange($user, $data);

            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'] !== '' ? $data['username'] : null,
                'role' => $data['role'],
                'status' => $data['status'],
                'updated_at' => now(),
            ];

            // كلمة المرور تُغيَّر فقط عندما تُكتب؛ تركها فارغة يُبقيها كما هي.
            if ($data['password'] !== '') {
                $payload['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            try {
                (new User())->update($id, $payload);
            } catch (\PDOException $e) {
                throw $this->uniquenessError($e);
            }

            $this->audit('update', 'user', $id, sprintf(
                'تعديل المستخدم %s — الدور: %s، الحالة: %s%s',
                $data['name'],
                role_label($data['role']),
                $data['status'] === 'active' ? 'نشط' : 'معطّل',
                $data['password'] !== '' ? '، مع تغيير كلمة المرور' : ''
            ));
        }, '/users', 'تم تحديث بيانات المستخدم.');
    }

    public function destroy(array $params): void
    {
        $this->verifyCsrf();
        $id = (int) $params['id'];

        $this->action(function () use ($id): void {
            $user = (new User())->find($id);
            if ($user === null) {
                throw new \RuntimeException('المستخدم غير موجود.');
            }

            if ($id === (int) Auth::id()) {
                throw new \RuntimeException('لا يمكنك حذف حسابك الحالي.');
            }

            if ($user['role'] === Auth::ROLE_ADMIN && $user['status'] === 'active') {
                $this->assertAnotherActiveAdminExists($id);
            }

            (new User())->delete($id);
            $this->audit('delete', 'user', $id, 'حذف المستخدم ' . $user['name']);
        }, '/users', 'تم حذف المستخدم.');
    }

    /** @return array<string,string> */
    private function userData(): array
    {
        return [
            'name' => $this->str('name'),
            'email' => $this->str('email'),
            'username' => $this->str('username'),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
            'role' => $this->str('role', Auth::ROLE_EMPLOYEE),
            'status' => $this->str('status', 'active'),
        ];
    }

    /** @param array<string,string> $data */
    private function validate(array $data, ?int $exceptId): void
    {
        $validator = (new Validator($data))
            ->required('name', 'الاسم')
            ->required('email', 'البريد الإلكتروني')
            ->email('email', 'البريد الإلكتروني')
            ->min('password', 'كلمة المرور', 8)
            ->in('role', 'الدور', self::ROLES)
            ->in('status', 'الحالة', self::STATUSES);

        if ($validator->fails()) {
            throw new \RuntimeException($validator->firstError());
        }

        if ($data['password'] !== $data['password_confirmation']) {
            throw new \RuntimeException('كلمتا المرور غير متطابقتين.');
        }

        $this->assertUnique('email', 'البريد الإلكتروني', $data['email'], $exceptId);
        if ($data['username'] !== '') {
            $this->assertUnique('username', 'اسم المستخدم', $data['username'], $exceptId);
        }
    }

    /** حماية المدير من إسقاط صلاحيته أو تعطيل حسابه أو إزالة آخر مدير نشط. */
    private function assertSafeChange(array $user, array $data): void
    {
        $id = (int) $user['id'];
        $isSelf = $id === (int) Auth::id();

        if ($isSelf && $data['role'] !== $user['role']) {
            throw new \RuntimeException('لا يمكنك تغيير دور حسابك الحالي.');
        }

        if ($isSelf && $data['status'] !== 'active') {
            throw new \RuntimeException('لا يمكنك تعطيل حسابك الحالي.');
        }

        $wasActiveAdmin = $user['role'] === Auth::ROLE_ADMIN && $user['status'] === 'active';
        $staysActiveAdmin = $data['role'] === Auth::ROLE_ADMIN && $data['status'] === 'active';

        if ($wasActiveAdmin && !$staysActiveAdmin) {
            $this->assertAnotherActiveAdminExists($id);
        }
    }

    /** يجب أن يبقى حساب مدير نشط واحد على الأقل حتى لا يُقفل النظام. */
    private function assertAnotherActiveAdminExists(int $exceptId): void
    {
        $others = Database::all('SELECT id, role, status FROM users WHERE id <> ?', [$exceptId]);
        foreach ($others as $other) {
            if ($other['role'] === Auth::ROLE_ADMIN && $other['status'] === 'active') {
                return;
            }
        }

        throw new \RuntimeException('يجب أن يبقى حساب مدير نشط واحد على الأقل.');
    }

    private function assertUnique(string $column, string $label, string $value, ?int $exceptId): void
    {
        $sql = 'SELECT id FROM users WHERE ' . $column . ' = ?';
        $params = [$value];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        if (Database::scalar($sql . ' LIMIT 1', $params) !== null) {
            throw new \RuntimeException('يوجد مستخدم مسجل بـ' . $label . ' «' . $value . '» مسبقًا.');
        }
    }

    private function uniquenessError(\PDOException $e): \RuntimeException
    {
        if ($e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return new \RuntimeException('يوجد مستخدم مسجل بنفس البريد أو اسم المستخدم.');
        }

        return new \RuntimeException('تعذر حفظ بيانات المستخدم. تحقق من البيانات وحاول مرة أخرى.');
    }
}
