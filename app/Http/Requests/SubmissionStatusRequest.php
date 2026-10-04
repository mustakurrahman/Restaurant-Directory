<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmissionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // /admin has no login by the owner's decision (protect it at server level)
    }

    public function rules(): array
    {
        // Only these three words can ever be saved as a submission status
        return ['status' => ['required', Rule::in(['pending', 'approved', 'rejected'])]];
    }
}
