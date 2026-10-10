<?php
/**
 * ترقيم الصفحات.
 * @var array{page:int,pages:int} $pager
 * @var array<string,mixed> $query
 */
$page = (int) $pager['page'];
$pages = (int) $pager['pages'];
$query = $query ?? [];

$link = static function (int $target) use ($query): string {
    $query['page'] = $target;

    return url(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') . '?' . http_build_query($query);
};
?>
<?php if ($pages > 1): ?>
    <nav class="pagination no-print" aria-label="ترقيم الصفحات">
        <?php if ($page > 1): ?>
            <a href="<?= e($link($page - 1)) ?>">السابق</a>
        <?php else: ?>
            <span class="is-disabled">السابق</span>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($pages, $start + 4);
        $start = max(1, $end - 4);
        for ($i = $start; $i <= $end; $i++):
            ?>
            <?php if ($i === $page): ?>
                <span class="is-current"><?= e((string) $i) ?></span>
            <?php else: ?>
                <a href="<?= e($link($i)) ?>"><?= e((string) $i) ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $pages): ?>
            <a href="<?= e($link($page + 1)) ?>">التالي</a>
        <?php else: ?>
            <span class="is-disabled">التالي</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>
