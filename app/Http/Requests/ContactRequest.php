<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // a public form: anyone may write to us
    }

    // Tidy the input first: no stray spaces, one spelling per email address, empty subject becomes null
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->toString(),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'subject' => Str::of((string) $this->input('subject'))->squish()->toString() ?: null,
            'message' => trim((string) $this->input('message')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return ['message.min' => 'Please write at least 10 characters.'];
    }

    // After a mistake, return to the form itself, not the top of the page
    protected function getRedirectUrl(): string
    {
        return route('contact.create').'#contact-form';
    }
}
