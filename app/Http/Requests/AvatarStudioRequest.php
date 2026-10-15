<?php

namespace App\Http\Requests;

use App\Services\GravatarAvatar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvatarStudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canPerformSocialWrites();
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['upload', 'gravatar'])],
            'style' => ['required_if:source,gravatar', 'prohibited_unless:source,gravatar', Rule::in(GravatarAvatar::STYLES)],
            'avatar' => ['required_if:source,upload', 'prohibited_unless:source,upload', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'url' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }
}
