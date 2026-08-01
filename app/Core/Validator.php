<?php
/**
 * Validator — declarative input validation.
 *
 * Central place to validate all user input before it touches the DB or an
 * external API. Returns an associative array of field => [error messages];
 * an empty array means the input is valid.
 *
 * Usage:
 *   $errors = Validator::make($_POST, [
 *       'email' => ['required', 'email', 'max:254'],
 *       'code'  => ['required', 'numeric', 'len:6'],
 *       'role'  => ['in:admin,staff,sales'],
 *   ]);
 *   if ($errors) { ... }
 */

declare(strict_types=1);

namespace PPC\Core;

final class Validator
{
    /**
     * Validate $input against $rules.
     *
     * @param array<string,mixed>  $input The data to validate.
     * @param array<string,array>  $rules field => list of rule strings.
     * @return array<string,array> field => error messages (empty if valid).
     */
    public static function make(array $input, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $input[$field] ?? null;
            foreach ($fieldRules as $rule) {
                [$name, $param] = self::parseRule($rule);
                $msg = self::apply($name, $param, $field, $value);
                if ($msg !== null) {
                    $errors[$field][] = $msg;
                    break; // one error per field is enough for UX
                }
            }
        }
        return $errors;
    }

    /** Split "max:254" into ["max", "254"]. */
    private static function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            return explode(':', $rule, 2);
        }
        return [$rule, null];
    }

    /** Apply a single rule; returns an error message or null if it passes. */
    private static function apply(string $rule, ?string $param, string $field, mixed $value): ?string
    {
        $label = ucwords(str_replace('_', ' ', $field));

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || $value === []) {
                    return "$label is required.";
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "$label must be a valid email address.";
                }
                break;

            case 'phone':
                // Accept digits, spaces, dashes, parens, leading +; require 7-15 digits.
                if ($value !== null && $value !== '') {
                    $digits = preg_replace('/\D+/', '', (string) $value);
                    if (strlen($digits) < 7 || strlen($digits) > 15) {
                        return "$label must be a valid phone number.";
                    }
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    return "$label must be numeric.";
                }
                break;

            case 'len':
                if ($value !== null && mb_strlen((string) $value) !== (int) $param) {
                    return "$label must be exactly $param characters.";
                }
                break;

            case 'min':
                if ($value !== null && $value !== '' && mb_strlen((string) $value) < (int) $param) {
                    return "$label must be at least $param characters.";
                }
                break;

            case 'max':
                if ($value !== null && mb_strlen((string) $value) > (int) $param) {
                    return "$label must be at most $param characters.";
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $param);
                if ($value !== null && $value !== '' && !in_array((string) $value, $allowed, true)) {
                    return "$label is not a valid choice.";
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return "$label must be a valid URL.";
                }
                break;
        }
        return null;
    }

    /**
     * Sanitize a string for safe storage/display (strip tags + trim).
     * Use for free-text fields; rich CMS content is sanitized differently.
     */
    public static function clean(?string $value): string
    {
        return trim(strip_tags((string) $value));
    }
}
