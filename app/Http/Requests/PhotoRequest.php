<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Shared definition of "a valid photo" for the cover and gallery uploads
abstract class PhotoRequest extends FormRequest
{
    // The admin has no login by design, so everyone who reaches the form may submit it
    public function authorize(): bool
    {
        return true;
    }

    /**
     * image: the content must really be a picture (SVG is excluded because it can carry scripts)
     * mimes: only jpg, png or webp
     * max: in kilobytes, 3072 = 3 MB
     *
     * @return array<int, string>
     */
    protected function photoRules(): array
    {
        return ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'];
    }

    /** @return array<string, string> */
    protected function photoMessages(string $field): array
    {
        return [
            "{$field}.uploaded" => 'The photo could not be uploaded. Make sure it is 3 MB or smaller.',
            "{$field}.image" => 'The file must be a picture.',
            "{$field}.mimes" => 'Photos must be JPG, PNG or WebP files.',
            "{$field}.max" => 'Each photo may be at most 3 MB.',
        ];
    }
}
