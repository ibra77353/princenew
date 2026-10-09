<?php
/**
 * تنبيهات المخزون المنخفض والديون المستحقة.
 * @var array<string,mixed> $alerts
 * @var int $alertsCount
 */
$count = (int) ($alertsCount ?? 0);
?>
<div class="notifications">
    <button type="button" class="btn btn--outline btn--icon" data-notifications-toggle aria-label="التنبيهات">
        <?= icon('bell') ?>
        <?php if ($count > 0): ?>
            <span class="badge badge--danger" style="margin-inline-start:0.35rem"><?= e((string) $count) ?></span>
        <?php endif; ?>
    </button>

    <div class="notifications__panel" hidden data-notifications-panel>
        <p class="text-sm text-bold mb-2">التنبيهات</p>
        <?php if ($count === 0): ?>
            <p class="text-xs text-muted">لا توجد تنبيهات حاليًا.</p>
        <?php else: ?>
            <?php foreach ($alerts['lowStock'] as $item): ?>
                <div class="notifications__item">
                    <span class="text-warning"><?= icon('alert') ?></span>
                    <span>المخزون منخفض: <?= e($item['name']) ?> — المتبقي <?= e((string) $item['current']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php foreach ($alerts['indebted'] as $item): ?>
                <div class="notifications__item">
                    <span class="text-danger"><?= icon('alert') ?></span>
                    <span>دين مستحق على <?= e($item['name']) ?> — <?= e(money($item['debt'])) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
