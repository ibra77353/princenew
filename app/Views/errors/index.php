<?php
/**
 * صفحة خطأ داخل التخطيط العام.
 * @var string $title
 * @var string $message
 */
?>
<div class="empty-state">
    <div class="empty-state__icon">!</div>
    <h2><?= e($title ?? 'خطأ') ?></h2>
    <p><?= e($message ?? '') ?></p>
    <a class="btn btn--primary" href="<?= e(url('/')) ?>">العودة إلى لوحة التحكم</a>
</div>
