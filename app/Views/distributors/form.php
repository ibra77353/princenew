<?php
/**
 * حقول نموذج الموزع.
 * @var array<string,mixed>|null $distributor
 */
?>
<div class="grid grid--form">
    <div class="field grid--full">
        <label for="dist-name">اسم الموزع</label>
        <input class="input" id="dist-name" name="name" type="text" required value="<?= e($distributor['name'] ?? old('name')) ?>">
    </div>

    <div class="field">
        <label for="dist-phone">رقم الهاتف</label>
        <input class="input" id="dist-phone" name="phone" type="text" value="<?= e($distributor['phone'] ?? old('phone')) ?>">
    </div>

    <div class="field">
        <label for="dist-status">الحالة</label>
        <select class="select" id="dist-status" name="status">
            <option value="active">نشط</option>
            <option value="inactive" <?= ($distributor['status'] ?? old('status')) === 'inactive' ? 'selected' : '' ?>>معطّل</option>
        </select>
    </div>

    <div class="field grid--full">
        <label for="dist-address">العنوان</label>
        <input class="input" id="dist-address" name="address" type="text" value="<?= e($distributor['address'] ?? old('address')) ?>">
    </div>

    <div class="field grid--full">
        <label for="dist-notes">ملاحظات</label>
        <textarea class="input" id="dist-notes" name="notes" rows="2"><?= e($distributor['notes'] ?? old('notes')) ?></textarea>
    </div>
</div>
