<?php

declare(strict_types=1);

/**
 * شبكة البرنس — نقطة الدخول الرئيسية
 * Native PHP MVC bootstrap + routing table.
 */

define('BASE_PATH', __DIR__);

$config = require BASE_PATH . '/config/database.php';

/** @var array<string,string> $config */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/app/' . $relative . '.php';
    // Controllers/Models/Services live directly under app/, but "Core" classes
    // are grouped inside app/Services/Core/.
    if (!is_file($file) && str_starts_with($relative, 'Services/Core/')) {
        $file = BASE_PATH . '/app/' . $relative . '.php';
    }
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Services/Core/helpers.php';

// معالجة الأخطاء أولًا: لا تُعرض تفاصيل داخلية للمستخدم مهما حدث بعد ذلك (M5)
\App\Services\Core\ErrorHandler::register($config['app']);

\App\Services\Core\Database::configure($config['database']);
\App\Services\Core\Setting::configure($config);

\App\Services\Core\Session::start($config['session']);
\App\Services\Core\Flash::boot();

$router = new \App\Services\Core\Router();

$public = ['guest'];
$auth = ['auth'];
// وسيط «admin»: المصادقة ثم التحقق من دور المدير على الخادم (403 لبقية الأدوار) — M6
$admin = ['auth', 'admin'];

// ── المصادقة والإعداد الأول ───────────────────────────────────────────────
$router->get('/login', [\App\Controllers\AuthController::class, 'loginForm'], $public);
$router->post('/login', [\App\Controllers\AuthController::class, 'login'], $public);
$router->get('/setup', [\App\Controllers\AuthController::class, 'setupForm'], $public);
$router->post('/setup', [\App\Controllers\AuthController::class, 'setup'], $public);
$router->post('/logout', [\App\Controllers\AuthController::class, 'logout'], $auth);

// ── لوحة التحكم ───────────────────────────────────────────────────────────
$router->get('/', [\App\Controllers\DashboardController::class, 'index'], $auth);

// ── الباقات ───────────────────────────────────────────────────────────────
// العرض متاح لكل الأدوار، وكل إضافة/تعديل/تعطيل/حذف للمدير فقط (مفروض على الخادم) — M6
$router->get('/packages', [\App\Controllers\PackagesController::class, 'index'], $auth);
$router->post('/packages', [\App\Controllers\PackagesController::class, 'store'], $admin);
$router->post('/packages/{id}/update', [\App\Controllers\PackagesController::class, 'update'], $admin);
$router->post('/packages/{id}/toggle', [\App\Controllers\PackagesController::class, 'toggle'], $admin);
$router->post('/packages/{id}/delete', [\App\Controllers\PackagesController::class, 'destroy'], $admin);

// ── المخزون ───────────────────────────────────────────────────────────────
// الموظف يعرض الأرصدة فقط؛ الإضافة والتسوية للمدير (وحركات البيع/الإرجاع تلقائية في النظام)
$router->get('/inventory', [\App\Controllers\InventoryController::class, 'index'], $auth);
$router->post('/inventory/add', [\App\Controllers\InventoryController::class, 'add'], $admin);
$router->post('/inventory/adjust', [\App\Controllers\InventoryController::class, 'adjust'], $admin);

// ── الموزعون ──────────────────────────────────────────────────────────────
// الموظف يعرض الموزعين ويختارهم في المبيعات/التحصيلات؛ الإضافة/التعديل/الحذف للمدير فقط
$router->get('/distributors', [\App\Controllers\DistributorsController::class, 'index'], $auth);
$router->post('/distributors', [\App\Controllers\DistributorsController::class, 'store'], $admin);
$router->post('/distributors/{id}/update', [\App\Controllers\DistributorsController::class, 'update'], $admin);
$router->post('/distributors/{id}/delete', [\App\Controllers\DistributorsController::class, 'destroy'], $admin);
$router->get('/distributors/{id}', [\App\Controllers\DistributorsController::class, 'show'], $auth);

// ── المبيعات ──────────────────────────────────────────────────────────────
$router->get('/sales', [\App\Controllers\SalesController::class, 'index'], $auth);
$router->get('/sales/create', [\App\Controllers\SalesController::class, 'create'], $auth);
$router->post('/sales', [\App\Controllers\SalesController::class, 'store'], $auth);
$router->get('/sales/{id}', [\App\Controllers\SalesController::class, 'show'], $auth);
// إلغاء الفاتورة عملية مالية حسّاسة (تعكس النقد والمخزون): للمدير فقط
$router->post('/sales/{id}/cancel', [\App\Controllers\SalesController::class, 'cancel'], $admin);

// ── التحصيلات ─────────────────────────────────────────────────────────────
$router->get('/collections', [\App\Controllers\CollectionsController::class, 'index'], $auth);
$router->post('/collections', [\App\Controllers\CollectionsController::class, 'store'], $auth);
$router->get('/collections/outstanding', [\App\Controllers\CollectionsController::class, 'outstanding'], $auth);

// ── الخطوط ودفعاتها ───────────────────────────────────────────────────────
$router->get('/lines', [\App\Controllers\LinesController::class, 'index'], $auth);
$router->post('/lines', [\App\Controllers\LinesController::class, 'store'], $auth);
$router->post('/lines/{id}/update', [\App\Controllers\LinesController::class, 'update'], $auth);
$router->post('/lines/{id}/delete', [\App\Controllers\LinesController::class, 'destroy'], $admin);
$router->get('/lines/{id}', [\App\Controllers\LinesController::class, 'show'], $auth);
$router->get('/line-payments', [\App\Controllers\LinePaymentsController::class, 'index'], $auth);
$router->post('/line-payments', [\App\Controllers\LinePaymentsController::class, 'store'], $auth);

// ── المصروفات وسحوبات المالك ──────────────────────────────────────────────
$router->get('/expenses', [\App\Controllers\ExpensesController::class, 'index'], $auth);
$router->post('/expenses', [\App\Controllers\ExpensesController::class, 'store'], $auth);
$router->get('/owner-withdrawals', [\App\Controllers\OwnerWithdrawalsController::class, 'index'], $auth);
$router->post('/owner-withdrawals', [\App\Controllers\OwnerWithdrawalsController::class, 'store'], $auth);

// ── الصندوق والتقارير والبحث ──────────────────────────────────────────────
$router->get('/cash', [\App\Controllers\CashController::class, 'index'], $auth);
$router->get('/reports', [\App\Controllers\ReportsController::class, 'index'], $auth);
$router->get('/search', [\App\Controllers\SearchController::class, 'index'], $auth);

// ── سجل التدقيق ───────────────────────────────────────────────────────────
$router->get('/audit', [\App\Controllers\AuditLogController::class, 'index'], $auth);

// ── المستخدمون والإعدادات والنسخ الاحتياطي (للمدير فقط) ─────────────────
$router->get('/users', [\App\Controllers\UsersController::class, 'index'], $admin);
$router->post('/users', [\App\Controllers\UsersController::class, 'store'], $admin);
$router->post('/users/{id}/update', [\App\Controllers\UsersController::class, 'update'], $admin);
$router->post('/users/{id}/delete', [\App\Controllers\UsersController::class, 'destroy'], $admin);

$router->get('/settings', [\App\Controllers\SettingsController::class, 'index'], $admin);
$router->post('/settings', [\App\Controllers\SettingsController::class, 'update'], $admin);
$router->get('/backup', [\App\Controllers\BackupController::class, 'index'], $admin);
$router->post('/backup/export', [\App\Controllers\BackupController::class, 'export'], $admin);
$router->post('/backup/restore', [\App\Controllers\BackupController::class, 'restore'], $admin);
$router->get('/backup/{id}/download', [\App\Controllers\BackupController::class, 'download'], $admin);
$router->post('/backup/{id}/delete', [\App\Controllers\BackupController::class, 'destroy'], $admin);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
