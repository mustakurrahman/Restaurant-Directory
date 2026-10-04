<?php

namespace App\Http\Requests;

use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // a public form: anyone may suggest a restaurant
    }

    // Tidy the input first: no stray spaces, one spelling per email address, empty optional fields become null
    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => Str::of((string) $this->input($key))->squish()->toString() ?: null;

        $this->merge([
            'restaurant_name' => $clean('restaurant_name'),
            'address' => $clean('address'),
            'city' => $clean('city'),
            'cuisine' => $clean('cuisine'),
            'phone' => $clean('phone'),
            'website' => $clean('website'),
            'submitter_name' => $clean('submitter_name'),
            'submitter_email' => Str::lower(trim((string) $this->input('submitter_email'))),
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'restaurant_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'cuisine' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-.\s]+$/'],
            'website' => ['nullable', 'url:http,https', 'max:255'], // only http(s): never javascript: and the like
            'description' => ['nullable', 'string', 'max:2000'],
            'submitter_name' => ['required', 'string', 'max:100'],
            'submitter_email' => [
                'required', 'email:rfc', 'max:255',
                // Stops the same person sending the same restaurant again while the first is still waiting
                function (string $attribute, mixed $value, \Closure $fail) {
                    $exists = Submission::where('status', 'pending')
                        ->where('submitter_email', $value)
                        ->whereRaw('LOWER(restaurant_name) = ?', [Str::lower((string) $this->input('restaurant_name'))])
                        ->exists();

                    if ($exists) {
                        $fail('You have already suggested this restaurant. We will review it soon.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'restaurant_name' => 'restaurant name',
            'submitter_name' => 'name',
            'submitter_email' => 'email',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number may only contain digits, spaces and the characters + ( ) - .',
            'website.url' => 'The website must be a full address starting with http:// or https://.',
        ];
    }

    // After a mistake, return to the form itself, not the top of the page
    protected function getRedirectUrl(): string
    {
        return route('submit.create').'#submit-form';
    }
}
