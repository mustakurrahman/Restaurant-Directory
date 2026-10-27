<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// Add / edit an admin account. Only people who are already signed in can reach this (the admin routes need a login).
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // One spelling per email address, no stray spaces
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->toString(),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        // On edit this is the account being edited, so its own email is not a "duplicate" (null when adding)
        $current = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($current)],
            // Required when adding. When editing, empty means "keep the current password".
            'password' => [$current ? 'nullable' : 'required', 'string', 'min:12', 'max:255', 'confirmed'],
        ];
    }
}
