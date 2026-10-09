<?php
/**
 * حقول نموذج الباقة (مشتركة بين الإضافة والتعديل).
 * @var array<string,mixed>|null $package
 */
?>
<div class="grid grid--form">
    <div class="field grid--full">
        <label for="pkg-name">اسم الباقة أو رمزها</label>
        <input class="input" id="pkg-name" name="name" type="text" required
               value="<?= e($package['name'] ?? old('name')) ?>" placeholder="مثال: 100">
    </div>

    <div class="field">
        <label for="pkg-price">سعر الشدة</label>
        <input class="input" id="pkg-price" name="price" type="number" min="0" step="1" required
               value="<?= e($package['price'] ?? old('price', '0')) ?>">
    </div>

    <div class="field">
        <label for="pkg-color">اللون</label>
        <input class="input" id="pkg-color" name="color" type="color"
               value="<?= e($package['color'] ?? old('color', '#2563eb')) ?>">
    </div>

    <div class="field">
        <label for="pkg-data">حجم البيانات (ميغابايت)</label>
        <input class="input" id="pkg-data" name="data_mb" type="number" min="0" step="1"
               value="<?= e($package['data_mb'] ?? old('data_mb', '0')) ?>">
    </div>

    <div class="field">
        <label for="pkg-hours">عدد الساعات</label>
        <input class="input" id="pkg-hours" name="hours" type="number" min="0" step="1"
               value="<?= e($package['hours'] ?? old('hours', '0')) ?>">
    </div>

    <div class="field">
        <label for="pkg-status">الحالة</label>
        <select class="select" id="pkg-status" name="status">
            <option value="active">مفعّلة</option>
            <option value="inactive" <?= ($package['status'] ?? old('status')) === 'inactive' ? 'selected' : '' ?>>معطّلة</option>
        </select>
    </div>

    <div class="field">
        <label for="pkg-description">الوصف (اختياري)</label>
        <input class="input" id="pkg-description" name="description" type="text"
               value="<?= e($package['description'] ?? old('description')) ?>">
    </div>
</div>
