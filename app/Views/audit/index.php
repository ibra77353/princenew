<?php
/**
 * سجل التدقيق (للقراءة فقط).
 * @var array<int,array<string,mixed>> $logs
 * @var array<string,string> $entities
 * @var array<string,mixed> $filters
 * @var array{page:int,pages:int} $pager
 * @var int $total
 */
$actions = [
    'create' => 'إنشاء',
    'update' => 'تعديل',
    'delete' => 'حذف',
    'cancel' => 'إلغاء',
    'toggle' => 'تفعيل/تعطيل',
    'add' => 'إضافة',
    'adjust' => 'تسوية',
    'login' => 'دخول',
    'logout' => 'خروج',
    'login_failed' => 'دخول فاشل',
    'setup' => 'إعداد',
    'export' => 'تصدير',
    'restore' => 'استعادة',
];
?>
<div class="page-header">
    <div>
        <h1>سجل التدقيق</h1>
        <p>سجل للقراءة فقط يوثّق العمليات الإدارية المهمة ولا يمكن تعديله أو حذفه من الواجهة.</p>
    </div>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">عدد السجلات المعروضة</span>
        <span class="stat__value"><?= e((string) $total) ?></span>
    </div>
</div>

<form method="get" action="<?= e(url('/audit')) ?>" class="filters">
    <div class="field field--wide">
        <label for="a-q">بحث في التفاصيل</label>
        <input class="input" id="a-q" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="كلمة في التفاصيل...">
    </div>
    <div class="field">
        <label for="a-entity">القسم</label>
        <select class="select" id="a-entity" name="entity_type">
            <option value="">كل الأقسام</option>
            <?php foreach ($entities as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filters['entity_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field self-end">
        <button type="submit" class="btn btn--outline"><?= icon('search') ?> تصفية</button>
    </div>
</form>

<?php if ($logs === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('history') ?></div>
            <h2>لا توجد سجلات</h2>
            <p>ستظهر هنا كل العمليات الإدارية بمجرد تنفيذها.</p>
        </div>
    </div>
<?php else: ?>
    <div class="timeline">
        <?php foreach ($logs as $log): ?>
            <div class="timeline__item">
                <span class="timeline__dot"></span>
                <div class="grow">
                    <div class="row row--between row--wrap">
                        <p class="text-sm text-bold">
                            <?= e($actions[$log['action']] ?? $log['action']) ?>
                            — <?= e($entities[$log['entity_type']] ?? $log['entity_type']) ?>
                            <?php if (!empty($log['entity_id'])): ?>
                                <span class="text-xs text-muted">#<?= e((string) $log['entity_id']) ?></span>
                            <?php endif; ?>
                        </p>
                        <span class="text-xs text-muted nowrap"><?= e(fdatetime($log['created_at'])) ?></span>
                    </div>
                    <p class="text-xs text-muted"><?= e($log['user_name'] ?? 'النظام') ?></p>
                    <?php if (!empty($log['details'])): ?>
                        <p class="text-sm mt-1 wrap"><?= e($log['details']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php \App\Services\Core\View::partial('partials/pagination', [
        'pager' => $pager,
        'query' => array_filter($filters, static fn ($value): bool => $value !== ''),
    ]); ?>
<?php endif; ?>
