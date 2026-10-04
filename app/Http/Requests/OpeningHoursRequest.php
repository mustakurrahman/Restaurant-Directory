<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OpeningHoursRequest extends FormRequest
{
    // The admin has no login by design, so everyone who reaches the form may submit it
    public function authorize(): bool
    {
        return true;
    }

    // An unticked "Closed" box is sent as the text "0" by a hidden field; turn it into a real true/false
    protected function prepareForValidation(): void
    {
        $hours = $this->input('hours');

        if (! is_array($hours)) {
            return;
        }

        foreach (range(1, 7) as $day) {
            if (isset($hours[$day]) && is_array($hours[$day])) {
                $hours[$day]['is_closed'] = filter_var($hours[$day]['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->merge(['hours' => $hours]);
    }

    public function rules(): array
    {
        $rules = ['hours' => ['required', 'array']];

        // 1 = Monday ... 7 = Sunday. Naming each day means no other day numbers can be saved.
        foreach (range(1, 7) as $day) {
            $rules["hours.{$day}.is_closed"] = ['boolean'];
            $rules["hours.{$day}.opens_at"] = ['nullable', 'date_format:H:i'];
            $rules["hours.{$day}.closes_at"] = ['nullable', 'date_format:H:i'];
        }

        return $rules;
    }

    // Checks that need to look at two fields of the same day together
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (range(1, 7) as $day) {
                $row = $this->input("hours.{$day}", []);

                // A closed day ignores its times; a day with a format error is already reported
                if (($row['is_closed'] ?? false) || $validator->errors()->hasAny(["hours.{$day}.opens_at", "hours.{$day}.closes_at"])) {
                    continue;
                }

                $opens = $row['opens_at'] ?? null;
                $closes = $row['closes_at'] ?? null;

                if (filled($opens) xor filled($closes)) {
                    $validator->errors()->add("hours.{$day}", 'Enter both the opening and closing time, or leave both empty.');
                } elseif (filled($opens) && $opens === $closes) {
                    $validator->errors()->add("hours.{$day}", 'The opening and closing time cannot be the same.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'hours.required' => 'The opening hours were not sent. Please try again.',
            'hours.*.opens_at.date_format' => 'Use the time format HH:MM, for example 09:00.',
            'hours.*.closes_at.date_format' => 'Use the time format HH:MM, for example 22:30.',
        ];
    }
}
