<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Shared rules for things that only have a name and a slug (cities, cuisines, amenities)
abstract class NameSlugRequest extends FormRequest
{
    /** Database table, e.g. 'cities' */
    abstract protected function table(): string;

    /** Route parameter holding the record being edited, e.g. 'city' */
    abstract protected function routeParameter(): string;

    // The admin has no login by design, so everyone who reaches the form may submit it
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // On edit this is the record being edited, so it does not clash with itself (null when adding)
        $current = $this->route($this->routeParameter());

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique($this->table(), 'name')->ignore($current)],
            // Optional: when empty, the model builds the slug from the name
            'slug' => [
                'nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique($this->table(), 'slug')->ignore($current),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens (for example new-york).',
        ];
    }
}
