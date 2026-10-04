<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CityRequest extends FormRequest
{
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
        // On edit this is the city being edited, so it does not clash with itself (null when adding)
        $city = $this->route('city');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('cities', 'name')->ignore($city)],
            // Optional: when empty, the model builds the slug from the name
            'slug' => [
                'nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('cities', 'slug')->ignore($city),
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
