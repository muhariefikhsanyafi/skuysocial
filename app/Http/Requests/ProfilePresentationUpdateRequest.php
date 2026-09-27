<?php

namespace App\Http\Requests;

use App\Enums\ProfileVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfilePresentationUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $links = $this->input('profile_links');

        if (! is_array($links)) {
            return;
        }

        foreach ($links as $index => $link) {
            if (! is_array($link)) {
                continue;
            }

            foreach (['label', 'url'] as $field) {
                if (isset($link[$field]) && is_string($link[$field])) {
                    $links[$index][$field] = trim($link[$field]);
                }
            }
        }

        $this->merge(['profile_links' => $links]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'profile_visibility' => ['sometimes', Rule::enum(ProfileVisibility::class)],
            'profile_links_present' => ['sometimes', 'accepted'],
            'profile_links' => ['sometimes', 'array', 'max:5'],
            'profile_links.*' => ['required', 'array:label,url'],
            'profile_links.*.label' => ['required', 'string', 'max:50'],
            'profile_links.*.url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ];
    }
}
