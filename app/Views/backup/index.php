<?php
/**
 * النسخ الاحتياطي والاستعادة.
 * @var array<int,array<string,mixed>> $backups
 * @var string $directory
 * @var bool $writable
 */
?>
<div class="page-header">
    <div>
        <h1>النسخ الاحتياطي والاستعادة</h1>
        <p>تُحفظ النسخ بصيغة JSON داخل مجلد محمي بعيدًا عن الوصول العام.</p>
    </div>
    <form method="post" action="<?= e(url('/backup/export')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="label" value="manual">
        <button type="submit" class="btn btn--primary"><?= icon('download') ?> إنشاء نسخة احتياطية</button>
    </form>
</div>

<?php if (!$writable): ?>
    <div class="alert alert--error">
        <?= icon('alert') ?>
        <span>مجلد النسخ الاحتياطية غير قابل للكتابة (<?= e($directory) ?>). تحقق من صلاحيات المجلد.</span>
    </div>
<?php endif; ?>

<div class="alert alert--warning">
    <?= icon('alert') ?>
    <span>
        قبل أي استعادة تُنشأ نسخة أمان تلقائية للبيانات الحالية. الاستعادة تستبدل بيانات النظام الحالية
        بالكامل (بما فيها المستخدمون)؛ تأكد من صحة النسخة قبل المتابعة.
    </span>
</div>

<?php if ($backups === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('database') ?></div>
            <h2>لا توجد نسخ احتياطية</h2>
            <p>أنشئ أول نسخة احتياطية لحماية بيانات الشبكة.</p>
            <form method="post" action="<?= e(url('/backup/export')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="label" value="manual">
                <button type="submit" class="btn btn--primary"><?= icon('download') ?> إنشاء نسخة</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>الملف</th>
                    <th>الوسم</th>
                    <th>الحجم</th>
                    <th>الجداول</th>
                    <th>تاريخ الإنشاء</th>
                    <th>أنشأها</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td class="wrap"><?= e($backup['filename']) ?></td>
                        <td><?= e($backup['label'] ?? '—') ?></td>
                        <td><?= e(num((int) $backup['size'] / 1024, 1)) ?> كيلوبايت</td>
                        <td><?= e((string) $backup['tables_count']) ?></td>
                        <td><?= e(fdatetime($backup['created_at'])) ?></td>
                        <td><?= e($backup['created_by'] ?? '—') ?></td>
                        <td>
                            <div class="btn-group">
                                <a class="btn btn--outline btn--sm" href="<?= e(url('/backup/' . $backup['id'] . '/download')) ?>">
                                    <?= icon('download') ?> تنزيل
                                </a>
                                <form method="post" action="<?= e(url('/backup/restore')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="backup_id" value="<?= e((string) $backup['id']) ?>">
                                    <button type="submit" class="btn btn--soft-danger btn--sm"
                                            data-confirm="استعادة هذه النسخة؟ سيتم استبدال البيانات الحالية بالكامل.">
                                        <?= icon('refresh') ?> استعادة
                                    </button>
                                </form>
                                <form method="post" action="<?= e(url('/backup/' . $backup['id'] . '/delete')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--soft-danger btn--sm"
                                            data-confirm="حذف ملف النسخة الاحتياطية؟">
                                        <?= icon('trash') ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
