<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protected by the secret token from the email, checked in the controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            // Same minimum as the admin:create command; "confirmed" needs a matching password_confirmation box
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ];
    }
}
