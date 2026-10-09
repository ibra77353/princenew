<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $data = DashboardService::data();

        $this->view('dashboard/index', array_merge($data, [
            'title' => 'لوحة التحكم',
            'counts' => [
                'sales' => (int) Database::scalar('SELECT COUNT(*) FROM sales'),
                'collections' => (int) Database::scalar('SELECT COUNT(*) FROM collections'),
                'lines' => (int) Database::scalar('SELECT COUNT(*) FROM `lines`'),
                'expenses' => (int) Database::scalar('SELECT COUNT(*) FROM expenses'),
            ],
        ]));
    }
}
