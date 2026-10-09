<?php
/**
 * شريط التنقّل السفلي للجوال.
 */
$items = [
    ['/', 'الرئيسية', 'dashboard'],
    ['/sales', 'المبيعات', 'cart'],
    ['/inventory', 'المخزون', 'boxes'],
    ['/collections', 'التحصيلات', 'coins'],
    ['/search', 'البحث', 'search'],
];
?>
<div class="mobilebar">
    <div class="mobilebar__inner">
        <?php foreach ($items as [$path, $label, $iconName]): ?>
            <a class="mobilebar__item<?= is_active_path($path) ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>">
                <?= icon($iconName) ?>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
