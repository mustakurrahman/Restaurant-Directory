<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Anyone may leave a review (moderation happens afterwards), but not for a draft: that page does not exist publicly
        abort_unless($this->route('restaurant')->status === 'published', 404);

        return true;
    }

    // Tidy the input first: no stray spaces, and one spelling per email address
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->toString(),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'comment' => trim((string) $this->input('comment')),
        ]);
    }

    public function rules(): array
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email:rfc', 'max:255',
                // One review per person per restaurant (rejected ones do not block a new attempt)
                function (string $attribute, mixed $value, \Closure $fail) use ($restaurant) {
                    if (Review::where('restaurant_id', $restaurant->id)->where('email', $value)->where('status', '!=', 'rejected')->exists()) {
                        $fail('This email address has already been used to review this restaurant.');
                    }
                },
            ],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Please choose a star rating.',
            'rating.between' => 'Please choose a star rating from 1 to 5.',
            'comment.min' => 'Please write at least 10 characters.',
        ];
    }

    // After a mistake, send the visitor back to the form itself, not the top of the page
    protected function getRedirectUrl(): string
    {
        return route('restaurants.show', $this->route('restaurant')).'#review-form';
    }
}
