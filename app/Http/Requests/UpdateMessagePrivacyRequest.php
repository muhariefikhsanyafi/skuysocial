<?php

namespace App\Http\Requests;

use App\Enums\MessagePrivacy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessagePrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message_privacy' => ['required', Rule::enum(MessagePrivacy::class)],
        ];
    }
}
