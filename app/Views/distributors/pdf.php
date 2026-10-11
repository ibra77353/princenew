<?php
/**
 * مستند PDF لكشف حساب الموزع.
 * يُبنى عبر `PdfService` (mPDF) فلا يستخدم تخطيط الموقع ولا أوراق أنماطه — أنماط مستقلة تناسب الورق.
 * @var array<string,mixed> $distributor
 * @var array<int,array<string,mixed>> $sales
 * @var array<int,array<string,mixed>> $collections
 * @var float $activeSalesTotal
 * @var float $paidAtSale
 * @var float $validCollections
 * @var float $balance
 * @var array{from:string,to:string} $filters
 * @var array<string,mixed> $settings
 */
$periodText = ($filters['from'] !== '' ? fdate($filters['from']) : 'من البداية')
    . ' — ' . ($filters['to'] !== '' ? fdate($filters['to']) : 'حتى اليوم');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>كشف حساب <?= e($distributor['name']) ?></title>
<style>
    body { font-size: 9.5pt; color: #111111; direction: rtl; }
    h1 { font-size: 14pt; margin: 0 0 4pt; }
    h2 { font-size: 11pt; margin: 10pt 0 4pt; border-bottom: 1pt solid #bbbbbb; padding-bottom: 2pt; }
    .muted { color: #555555; font-size: 8.5pt; margin: 0 0 4pt; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 0.5pt solid #999999; padding: 3pt 4pt; text-align: right; }
    th { background-color: #eeeeee; }
    .totals td { border: 0; padding: 2pt 4pt 2pt 0; }
    .totals .label { color: #555555; font-size: 8.5pt; }
    .num { white-space: nowrap; }
</style>
</head>
<body>
    <h1>كشف حساب موزع — <?= e($settings['network_name'] ?? 'شبكة البرنس') ?></h1>
    <p class="muted">
        الموزع: <?= e($distributor['name']) ?><?= !empty($distributor['phone']) ? ' — ' . e($distributor['phone']) : '' ?>
        <?= !empty($distributor['address']) ? ' — ' . e($distributor['address']) : '' ?>
        <br>الفترة: <?= e($periodText) ?>
        <br>تاريخ التصدير: <?= e(fdatetime(date('Y-m-d H:i:s'))) ?>
    </p>

    <table class="totals">
        <tr>
            <td class="label">إجمالي المبيعات</td>
            <td class="label">إجمالي التحصيلات</td>
            <td class="label">المدفوع عند البيع</td>
            <td class="label">الرصيد المستحق (كل الفترات)</td>
        </tr>
        <tr>
            <td class="num"><b><?= e(money($activeSalesTotal)) ?></b></td>
            <td class="num"><b><?= e(money($validCollections)) ?></b></td>
            <td class="num"><b><?= e(money($paidAtSale)) ?></b></td>
            <td class="num"><b><?= e(money($balance)) ?></b></td>
        </tr>
    </table>

    <h2>سجل المبيعات (<?= e((string) count($sales)) ?>)</h2>
    <?php if ($sales === []): ?>
        <p class="muted">لا توجد مبيعات في هذه الفترة.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>التاريخ</th>
                    <th>النوع</th>
                    <th>الإجمالي</th>
                    <th>المدفوع</th>
                    <th>المتبقي</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?= e($sale['invoice_number']) ?></td>
                        <td class="num"><?= e(fdatetime($sale['sale_date'])) ?></td>
                        <td><?= $sale['payment_type'] === 'cash' ? 'نقدي' : 'آجل' ?></td>
                        <td class="num"><?= e(money($sale['total_amount'])) ?></td>
                        <td class="num"><?= e(money($sale['paid_amount'])) ?></td>
                        <td class="num"><?= e(money($sale['remaining'])) ?></td>
                        <td><?= $sale['status'] === 'active' ? 'نشطة' : 'ملغاة' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>سجل التحصيلات (<?= e((string) count($collections)) ?>)</h2>
    <?php if ($collections === []): ?>
        <p class="muted">لا توجد تحصيلات في هذه الفترة.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>الفاتورة</th>
                    <th>المبلغ</th>
                    <th>ملاحظات</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($collections as $collection): ?>
                    <tr>
                        <td class="num"><?= e(fdatetime($collection['collection_date'])) ?></td>
                        <td><?= e($collection['invoice_number'] ?? 'عام') ?></td>
                        <td class="num"><?= e(money($collection['amount'])) ?></td>
                        <td><?= e($collection['notes'] ?? '') ?></td>
                        <td><?= $collection['status'] === 'active' ? 'صالحة' : 'معكوسة' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="muted">
        إجماليات الكشف للفواتير النشطة فقط (الملغاة تظهر بحالتها ومتبقيها صفر)، والتحصيلات المعكوسة تبقى
        معروضة بحالتها ولا تدخل في إجمالي التحصيلات. أما «الرصيد المستحق» فهو رصيد الموزع الحالي على كل
        الفترات لأن الدين لا يتوقّف عند حدّ الفترة.
    </p>
</body>
</html>
