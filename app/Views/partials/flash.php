<?php
/**
 * رسائل النجاح والفشل.
 * @var array<int,array{type:string,message:string}> $flashes
 */
foreach ($flashes ?? [] as $flash):
    $tone = match ($flash['type']) {
        'success' => 'alert--success',
        'error' => 'alert--error',
        'warning' => 'alert--warning',
        default => 'alert--info',
    };
    ?>
    <div class="alert <?= e($tone) ?>" role="status">
        <?= icon($flash['type'] === 'success' ? 'check' : 'alert') ?>
        <span><?= e($flash['message']) ?></span>
    </div>
<?php endforeach; ?>
