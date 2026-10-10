<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * تحقق مبسّط من المدخلات مع رسائل خطأ عربية.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private array $data)
    {
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
            $this->errors[$field] = $label . ' مطلوب';
        }

        return $this;
    }

    public function numeric(string $field, string $label, float $min = 0.0): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this;
        }
        if (!is_numeric($value)) {
            $this->errors[$field] = $label . ' يجب أن يكون رقمًا';

            return $this;
        }
        if ((float) $value < $min) {
            $this->errors[$field] = $label . ' يجب ألا يقل عن ' . $min;
        }

        return $this;
    }

    public function integer(string $field, string $label, int $min = 1): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this;
        }
        if (!is_numeric($value) || (float) $value != (int) $value) {
            $this->errors[$field] = $label . ' يجب أن يكون عددًا صحيحًا';

            return $this;
        }
        if ((int) $value < $min) {
            $this->errors[$field] = $label . ' يجب ألا يقل عن ' . $min;
        }

        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $label . ' غير صالح';
        }

        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this;
        }
        $parts = explode('-', (string) $value);
        if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            $this->errors[$field] = $label . ' غير صالح';
        }

        return $this;
    }

    public function min(string $field, string $label, int $length): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && mb_strlen($value) < $length) {
            $this->errors[$field] = $label . ' يجب أن يكون ' . $length . ' أحرف على الأقل';
        }

        return $this;
    }

    public function in(string $field, string $label, array $allowed): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = $label . ' غير صالح';
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return (string) (reset($this->errors) ?: 'بيانات غير صالحة');
    }
}
