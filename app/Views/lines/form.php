<?php
/**
 * حقول نموذج الخط.
 * @var array<string,mixed>|null $line
 */
?>
<div class="grid grid--form">
    <div class="field grid--full">
        <label for="line-name">اسم الخط</label>
        <input class="input" id="line-name" name="name" type="text" required value="<?= e($line['name'] ?? old('name')) ?>">
    </div>

    <div class="field">
        <label for="line-provider">مزود الخدمة</label>
        <input class="input" id="line-provider" name="provider" type="text" value="<?= e($line['provider'] ?? old('provider')) ?>">
    </div>

    <div class="field">
        <label for="line-identifier">المعرّف</label>
        <input class="input" id="line-identifier" name="identifier" type="text" value="<?= e($line['identifier'] ?? old('identifier')) ?>">
    </div>

    <div class="field">
        <label for="line-speed">السرعة</label>
        <input class="input" id="line-speed" name="speed" type="text" value="<?= e($line['speed'] ?? old('speed')) ?>" placeholder="مثال: 20 ميجا">
    </div>

    <div class="field">
        <label for="line-cost">تكلفة الاشتراك</label>
        <input class="input" id="line-cost" name="subscription_cost" type="number" min="0" step="1"
               value="<?= e($line['subscription_cost'] ?? old('subscription_cost', '0')) ?>">
    </div>

    <div class="field">
        <label for="line-sub-date">تاريخ الاشتراك</label>
        <input class="input" id="line-sub-date" name="subscription_date" type="date"
               value="<?= e($line['subscription_date'] ?? old('subscription_date')) ?>">
    </div>

    <div class="field">
        <label for="line-status">الحالة</label>
        <select class="select" id="line-status" name="status">
            <option value="active">نشط</option>
            <option value="inactive" <?= ($line['status'] ?? old('status')) === 'inactive' ? 'selected' : '' ?>>معطّل</option>
        </select>
    </div>

    <div class="field grid--full">
        <label for="line-notes">ملاحظات</label>
        <textarea class="input" id="line-notes" name="notes" rows="2"><?= e($line['notes'] ?? old('notes')) ?></textarea>
    </div>
</div>
