<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => [
                'required',
                'string',
                Rule::in(array_keys(config('localization.supported', []))),
            ],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * Reject redirect targets that are not local relative paths.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $returnTo = $this->input('return_to');

            if ($returnTo === null || $returnTo === '') {
                return;
            }

            if (! self::isSafeLocalPath($returnTo)) {
                $validator->errors()->add('return_to', 'The return path must be a local path.');
            }
        }];
    }

    /**
     * Keep invalid input from redirecting through an arbitrary Referer.
     */
    protected function getRedirectUrl(): string
    {
        $returnTo = $this->input('return_to');

        return self::isSafeLocalPath($returnTo) ? $returnTo : '/';
    }

    /**
     * Determine whether a value is a bounded, same-origin relative path.
     */
    public static function isSafeLocalPath(mixed $value): bool
    {
        if (! is_string($value)
            || $value === ''
            || strlen($value) > 2048
            || ! str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || str_contains($value, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $value)
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
            return false;
        }

        return true;
    }
}
