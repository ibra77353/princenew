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

\App\Services\Core\Database::configure($config['database']);
\App\Services\Core\Setting::configure($config);

\App\Services\Core\Session::start($config['session']);
\App\Services\Core\Flash::boot();

$router = new \App\Services\Core\Router();

$public = ['guest'];
$auth = ['auth'];

// ── المصادقة والإعداد الأول ───────────────────────────────────────────────
$router->get('/login', [\App\Controllers\AuthController::class, 'loginForm'], $public);
$router->post('/login', [\App\Controllers\AuthController::class, 'login'], $public);
$router->get('/setup', [\App\Controllers\AuthController::class, 'setupForm'], $public);
$router->post('/setup', [\App\Controllers\AuthController::class, 'setup'], $public);
$router->post('/logout', [\App\Controllers\AuthController::class, 'logout'], $auth);

// ── لوحة التحكم ───────────────────────────────────────────────────────────
$router->get('/', [\App\Controllers\DashboardController::class, 'index'], $auth);

// ── الباقات ───────────────────────────────────────────────────────────────
$router->get('/packages', [\App\Controllers\PackagesController::class, 'index'], $auth);
$router->post('/packages', [\App\Controllers\PackagesController::class, 'store'], $auth);
$router->post('/packages/{id}/update', [\App\Controllers\PackagesController::class, 'update'], $auth);
$router->post('/packages/{id}/toggle', [\App\Controllers\PackagesController::class, 'toggle'], $auth);
$router->post('/packages/{id}/delete', [\App\Controllers\PackagesController::class, 'destroy'], $auth);

// ── المخزون ───────────────────────────────────────────────────────────────
$router->get('/inventory', [\App\Controllers\InventoryController::class, 'index'], $auth);
$router->post('/inventory/add', [\App\Controllers\InventoryController::class, 'add'], $auth);
$router->post('/inventory/adjust', [\App\Controllers\InventoryController::class, 'adjust'], $auth);

// ── الموزعون ──────────────────────────────────────────────────────────────
$router->get('/distributors', [\App\Controllers\DistributorsController::class, 'index'], $auth);
$router->post('/distributors', [\App\Controllers\DistributorsController::class, 'store'], $auth);
$router->post('/distributors/{id}/update', [\App\Controllers\DistributorsController::class, 'update'], $auth);
$router->post('/distributors/{id}/delete', [\App\Controllers\DistributorsController::class, 'destroy'], $auth);
$router->get('/distributors/{id}', [\App\Controllers\DistributorsController::class, 'show'], $auth);

// ── المبيعات ──────────────────────────────────────────────────────────────
$router->get('/sales', [\App\Controllers\SalesController::class, 'index'], $auth);
$router->get('/sales/create', [\App\Controllers\SalesController::class, 'create'], $auth);
$router->post('/sales', [\App\Controllers\SalesController::class, 'store'], $auth);
$router->get('/sales/{id}', [\App\Controllers\SalesController::class, 'show'], $auth);
$router->post('/sales/{id}/cancel', [\App\Controllers\SalesController::class, 'cancel'], $auth);

// ── التحصيلات ─────────────────────────────────────────────────────────────
$router->get('/collections', [\App\Controllers\CollectionsController::class, 'index'], $auth);
$router->post('/collections', [\App\Controllers\CollectionsController::class, 'store'], $auth);
$router->get('/collections/outstanding', [\App\Controllers\CollectionsController::class, 'outstanding'], $auth);

// ── الخطوط ودفعاتها ───────────────────────────────────────────────────────
$router->get('/lines', [\App\Controllers\LinesController::class, 'index'], $auth);
$router->post('/lines', [\App\Controllers\LinesController::class, 'store'], $auth);
$router->post('/lines/{id}/update', [\App\Controllers\LinesController::class, 'update'], $auth);
$router->post('/lines/{id}/delete', [\App\Controllers\LinesController::class, 'destroy'], $auth);
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

// ── سجل التدقيق والإعدادات والنسخ الاحتياطي ───────────────────────────────
$router->get('/audit', [\App\Controllers\AuditLogController::class, 'index'], $auth);
$router->get('/settings', [\App\Controllers\SettingsController::class, 'index'], $auth);
$router->post('/settings', [\App\Controllers\SettingsController::class, 'update'], $auth);
$router->get('/backup', [\App\Controllers\BackupController::class, 'index'], $auth);
$router->post('/backup/export', [\App\Controllers\BackupController::class, 'export'], $auth);
$router->post('/backup/restore', [\App\Controllers\BackupController::class, 'restore'], $auth);
$router->get('/backup/{id}/download', [\App\Controllers\BackupController::class, 'download'], $auth);
$router->post('/backup/{id}/delete', [\App\Controllers\BackupController::class, 'destroy'], $auth);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
