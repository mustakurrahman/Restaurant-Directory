<?php

namespace App\Http\Requests;

class UploadCoverRequest extends PhotoRequest
{
    public function rules(): array
    {
        return ['cover' => ['required', ...$this->photoRules()]];
    }

    public function messages(): array
    {
        return $this->photoMessages('cover') + ['cover.required' => 'Choose a photo first.'];
    }
}
