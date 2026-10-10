<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CashService;
use App\Services\Core\Controller;
use App\Services\Core\Database;
use App\Services\Core\Setting;
use App\Services\Core\Validator;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $settingsRow = Database::one('SELECT * FROM settings ORDER BY id ASC LIMIT 1') ?? [];

        $this->view('settings/index', [
            'title' => 'الإعدادات',
            'row' => $settingsRow,
            'cashBalance' => CashService::balance(),
            'users' => Database::all('SELECT id, name, email, username, role, status, last_login_at, created_at FROM users ORDER BY id ASC'),
            'counts' => [
                'sales' => (int) Database::scalar('SELECT COUNT(*) FROM sales'),
                'distributors' => (int) Database::scalar('SELECT COUNT(*) FROM distributors'),
                'packages' => (int) Database::scalar('SELECT COUNT(*) FROM packages'),
                'movements' => (int) Database::scalar('SELECT COUNT(*) FROM inventory_movements'),
            ],
        ]);
    }

    public function update(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            $data = [
                'network_name' => $this->str('network_name'),
                'currency' => $this->str('currency', 'YER'),
                'currency_symbol' => $this->str('currency_symbol', 'ر.ي'),
                'admin_email' => $this->str('admin_email'),
                'opening_balance' => $this->moneyInput('opening_balance'),
                'low_stock_threshold' => $this->int('low_stock_threshold', 10),
            ];

            $validator = (new Validator($data))
                ->required('network_name', 'اسم الشبكة')
                ->numeric('opening_balance', 'الرصيد الافتتاحي', 0)
                ->integer('low_stock_threshold', 'حد تنبيه المخزون', 0)
                ->email('admin_email', 'بريد المدير');

            if ($validator->fails()) {
                throw new \RuntimeException($validator->firstError());
            }

            $existing = Database::one('SELECT id FROM settings ORDER BY id ASC LIMIT 1');
            $payload = [
                'network_name' => $data['network_name'],
                'currency' => $data['currency'],
                'currency_symbol' => $data['currency_symbol'],
                'admin_email' => $data['admin_email'] !== '' ? $data['admin_email'] : null,
                'opening_balance' => $data['opening_balance'],
                'low_stock_threshold' => $data['low_stock_threshold'],
                'updated_at' => now(),
            ];

            if ($existing === null) {
                Database::insert(
                    'INSERT INTO settings (network_name, currency, currency_symbol, admin_email, opening_balance, low_stock_threshold, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    array_values($payload)
                );
            } else {
                Database::affect(
                    'UPDATE settings SET network_name = ?, currency = ?, currency_symbol = ?, admin_email = ?,
                        opening_balance = ?, low_stock_threshold = ?, updated_at = ? WHERE id = ?',
                    [...array_values($payload), (int) $existing['id']]
                );
            }

            Setting::forget();
            $this->audit('update', 'settings', (int) ($existing['id'] ?? 0), 'تحديث إعدادات النظام');
        }, '/settings', 'تم حفظ الإعدادات بنجاح.');
    }
}
