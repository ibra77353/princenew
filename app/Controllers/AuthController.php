<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\Core\Auth;
use App\Services\Core\Controller;
use App\Services\Core\Flash;
use App\Services\Core\Validator;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::needsSetup()) {
            $this->redirect('/setup');

            return;
        }

        if (Auth::check()) {
            $this->redirect('/');

            return;
        }

        $this->view('auth/login', ['title' => 'تسجيل الدخول'], 'layouts/auth');
    }

    public function login(): void
    {
        $this->verifyCsrf();

        if (Auth::needsSetup()) {
            $this->redirect('/setup');

            return;
        }

        $identifier = $this->str('identifier');
        $password = (string) ($_POST['password'] ?? '');

        $validator = (new Validator(['identifier' => $identifier, 'password' => $password]))
            ->required('identifier', 'البريد أو اسم المستخدم')
            ->required('password', 'كلمة المرور');

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            Flash::withOld(['identifier' => $identifier]);
            $this->redirect('/login');
        }

        if (!Auth::attempt($identifier, $password)) {
            AuditService::log('login_failed', 'auth', null, 'محاولة دخول فاشلة: ' . $identifier);
            Flash::error('بيانات الدخول غير صحيحة.');
            Flash::withOld(['identifier' => $identifier]);
            $this->redirect('/login');
        }

        AuditService::log('login', 'auth', Auth::id(), 'تسجيل دخول');
        Flash::success('مرحبًا ' . Auth::name());
        $this->redirect('/');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        AuditService::log('logout', 'auth', Auth::id(), 'تسجيل خروج');
        Auth::logout();
        $this->redirect('/login');
    }

    public function setupForm(): void
    {
        if (!Auth::needsSetup()) {
            $this->redirect('/login');

            return;
        }

        $this->view('auth/setup', ['title' => 'إعداد حساب المدير'], 'layouts/auth');
    }

    public function setup(): void
    {
        $this->verifyCsrf();

        if (!Auth::needsSetup()) {
            $this->redirect('/login');

            return;
        }

        $data = [
            'name' => $this->str('name'),
            'email' => $this->str('email'),
            'username' => $this->str('username'),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
        ];

        $validator = (new Validator($data))
            ->required('name', 'الاسم')
            ->required('email', 'البريد الإلكتروني')
            ->email('email', 'البريد الإلكتروني')
            ->required('password', 'كلمة المرور')
            ->min('password', 'كلمة المرور', 8);

        if ($validator->fails()) {
            Flash::error($validator->firstError());
            Flash::withOld($data);
            $this->redirect('/setup');
        }

        if ($data['password'] !== $data['password_confirmation']) {
            Flash::error('كلمتا المرور غير متطابقتين.');
            Flash::withOld($data);
            $this->redirect('/setup');
        }

        try {
            $userId = Auth::createFirstAdmin(
                $data['name'],
                $data['email'],
                $data['password'],
                $data['username'] !== '' ? $data['username'] : null
            );
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            $this->redirect('/setup');
        }

        AuditService::log('setup', 'auth', $userId, 'إنشاء حساب المدير الأول');
        Auth::attempt($data['email'], $data['password']);
        Flash::success('تم إنشاء حساب المدير بنجاح.');
        $this->redirect('/');
    }
}
