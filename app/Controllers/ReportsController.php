<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Distributor;
use App\Models\Package;
use App\Services\Core\Controller;
use App\Services\ReportService;

final class ReportsController extends Controller
{
    public function index(): void
    {
        $period = $this->str('period', 'month');
        [$from, $to] = $this->resolvePeriod($period);

        $rangeError = '';
        if ($period === 'custom') {
            // التحقق من تواريخ الفترة المخصصة: قيمة غير صالحة لا تُنتج إجماليات مضلّلة (M7).
            ['from' => $from, 'to' => $to, 'error' => $rangeError] = $this->dateRange();
        }

        $filters = [
            'from' => $from,
            'to' => $to,
            'distributor_id' => $this->int('distributor_id'),
            'package_id' => $this->int('package_id'),
            'status' => $this->str('status', 'active'),
        ];

        $this->view('reports/index', [
            'title' => 'التقارير',
            'report' => ReportService::build($filters),
            'filters' => $filters,
            'rangeError' => $rangeError,
            'period' => $period,
            'distributors' => (new Distributor())->active(),
            'packages' => (new Package())->ordered(),
        ]);
    }

    /** @return array{0:string,1:string} */
    private function resolvePeriod(string $period): array
    {
        return match ($period) {
            'today' => [date('Y-m-d'), date('Y-m-d')],
            'week' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
            'year' => [date('Y-01-01'), date('Y-m-d')],
            'month' => [date('Y-m-01'), date('Y-m-d')],
            default => [$this->str('from'), $this->str('to')],
        };
    }
}
