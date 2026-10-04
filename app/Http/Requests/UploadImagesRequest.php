<?php

namespace App\Http\Requests;

class UploadImagesRequest extends PhotoRequest
{
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'max:10'],
            'images.*' => $this->photoRules(),
        ];
    }

    public function messages(): array
    {
        return $this->photoMessages('images.*') + [
            'images.required' => 'Choose at least one photo first.',
            'images.max' => 'You can upload at most 10 photos at a time.',
        ];
    }
}
