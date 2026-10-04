<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Alt text: the description read aloud by screen readers and used by Google Images
        return ['alt_text' => ['nullable', 'string', 'max:255']];
    }
}
