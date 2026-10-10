<?php
/**
 * الترويسة العلوية: زر القائمة، اسم الشبكة، التنبيهات، الوضع الداكن.
 * @var array<string,mixed> $settings
 * @var array<string,mixed> $alerts
 * @var int $alertsCount
 * @var array<string,mixed>|null $currentUser
 */
?>
<header class="topbar">
    <div class="topbar__start">
        <button type="button" class="btn btn--outline btn--icon menu-toggle" data-menu-toggle aria-label="القائمة">
            <?= icon('menu') ?>
        </button>
        <div class="brand-inline">
            <span class="text-primary"><?= icon('network') ?></span>
            <span><?= e($settings['network_name'] ?? 'شبكة البرنس') ?></span>
        </div>
    </div>

    <div class="topbar__end">
        <?php \App\Services\Core\View::partial('partials/notifications', ['alerts' => $alerts, 'alertsCount' => $alertsCount]); ?>
        <button type="button" class="btn btn--outline btn--icon" data-theme-toggle aria-label="تبديل المظهر">
            <span data-theme-icon>☾</span>
        </button>
    </div>
</header>
