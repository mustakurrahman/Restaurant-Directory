<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MessageReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // /admin has no login by the owner's decision (protect it at server level)
    }

    public function rules(): array
    {
        // "1" = mark as read, "0" = mark as unread. Nothing else of the message can be changed.
        return ['is_read' => ['required', 'boolean']];
    }
}
