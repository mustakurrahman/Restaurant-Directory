<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;

class CityRequest extends NameSlugRequest
{
    protected function table(): string
    {
        return 'cities';
    }

    protected function routeParameter(): string
    {
        return 'city';
    }

    // An empty description box is saved as "no description" (null), not as an empty string
    protected function prepareForValidation(): void
    {
        $this->merge(['description' => Str::of((string) $this->input('description'))->trim()->toString() ?: null]);
    }

    // The shared name + slug rules, plus the optional description
    public function rules(): array
    {
        return parent::rules() + [
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
