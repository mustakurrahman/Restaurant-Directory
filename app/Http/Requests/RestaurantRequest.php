<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestaurantRequest extends FormRequest
{
    // The admin has no login by design, so everyone who reaches the form may submit it
    public function authorize(): bool
    {
        return true;
    }

    // An unticked checkbox sends nothing, so turn "missing" into false
    protected function prepareForValidation(): void
    {
        $this->merge(['is_featured' => $this->boolean('is_featured')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // On edit this is the restaurant being edited, so its own slug is not a "duplicate" (null when adding)
        $restaurant = $this->route('restaurant');

        return [
            'name' => ['required', 'string', 'max:255'],
            // Optional: when empty, the model builds the slug from the name
            'slug' => [
                'nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('restaurants', 'slug')->ignore($restaurant),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],

            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-.\s]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],

            'price_range' => ['required', 'integer', 'between:1,4'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_featured' => ['boolean'],

            // Both or neither: half a map pin is useless
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],

            // Ticked checkboxes arrive as lists of ids
            'cuisines' => ['nullable', 'array'],
            'cuisines.*' => ['integer', 'exists:cuisines,id'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens (for example trattoria-bella-luna).',
            'phone.regex' => 'The phone number may only contain digits, spaces and the characters + ( ) - .',
            'website.url' => 'The website must be a full address starting with http:// or https://.',
            'latitude.required_with' => 'Enter both latitude and longitude, or leave both empty.',
            'longitude.required_with' => 'Enter both latitude and longitude, or leave both empty.',
        ];
    }
}
