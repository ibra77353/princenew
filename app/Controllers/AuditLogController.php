<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Services\AuditService;
use App\Services\Core\Controller;

final class AuditLogController extends Controller
{
    public function index(): void
    {
        $entityType = $this->str('entity_type');
        $term = $this->str('q');

        $model = new AuditLog();
        $total = $model->countFiltered($entityType !== '' ? $entityType : null, $term !== '' ? $term : null);
        $pager = $this->pagination($total, 30);

        $this->view('audit/index', [
            'title' => 'سجل التدقيق',
            'logs' => $model->listing(
                $pager['perPage'],
                $pager['offset'],
                $entityType !== '' ? $entityType : null,
                $term !== '' ? $term : null
            ),
            'entities' => AuditService::ENTITIES,
            'filters' => ['entity_type' => $entityType, 'q' => $term],
            'pager' => $pager,
            'total' => $total,
        ]);
    }
}
