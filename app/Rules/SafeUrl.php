<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::allows($value)) {
            $fail('The :attribute field contains an unsupported or unsafe URL.');
        }
    }

    public static function allows(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $value) ?? '');
        if ($normalized === '') {
            return true;
        }

        if (str_contains($normalized, '\\') || str_starts_with($normalized, '//')) {
            return false;
        }

        $scheme = parse_url($normalized, PHP_URL_SCHEME);
        if ($scheme === null) {
            return true;
        }

        $scheme = strtolower($scheme);
        if (in_array($scheme, ['http', 'https'], true)) {
            return preg_match('/^https?:\/\//i', $normalized) === 1
                && filled(parse_url($normalized, PHP_URL_HOST));
        }

        return in_array($scheme, ['mailto', 'tel'], true);
    }
}
