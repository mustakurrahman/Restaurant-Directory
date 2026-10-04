<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;

class AmenityRequest extends NameSlugRequest
{
    protected function table(): string
    {
        return 'amenities';
    }

    protected function routeParameter(): string
    {
        return 'amenity';
    }

    // An empty icon box is saved as "no icon" (null), not as an empty string
    protected function prepareForValidation(): void
    {
        $this->merge(['icon' => Str::of((string) $this->input('icon'))->trim()->lower()->toString() ?: null]);
    }

    // The shared name + slug rules, plus the optional icon name
    public function rules(): array
    {
        return parent::rules() + [
            // lowercase words joined by hyphens, like "wifi" or "outdoor-seating"
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'icon.regex' => 'The icon name may only contain lowercase letters, numbers and single hyphens (for example wifi or outdoor-seating).',
        ];
    }
}
