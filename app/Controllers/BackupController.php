<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Backup;
use App\Services\BackupService;
use App\Services\Core\Controller;
use App\Services\Core\Flash;
use App\Services\Core\Response;

final class BackupController extends Controller
{
    public function index(): void
    {
        $this->view('backup/index', [
            'title' => 'النسخ الاحتياطي والاستعادة',
            'backups' => (new Backup())->listing(),
            'directory' => BackupService::directory(),
            'writable' => is_writable(BackupService::directory()),
        ]);
    }

    public function export(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            BackupService::export($this->str('label'));
        }, '/backup', 'تم إنشاء نسخة احتياطية جديدة.');
    }

    public function restore(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            BackupService::restore($this->int('backup_id'));
        }, '/backup', 'تمت استعادة البيانات من النسخة الاحتياطية.');
    }

    public function download(array $params): void
    {
        $id = (int) $params['id'];

        try {
            [$path, $filename] = BackupService::filePath($id);
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            $this->redirect('/backup');

            return;
        }

        if (!is_file($path)) {
            Flash::error('ملف النسخة غير موجود.');
            $this->redirect('/backup');

            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);

        exit;
    }

    public function destroy(array $params): void
    {
        $this->verifyCsrf();

        $this->action(function () use ($params): void {
            BackupService::delete((int) $params['id']);
        }, '/backup', 'تم حذف النسخة الاحتياطية.');
    }
}
