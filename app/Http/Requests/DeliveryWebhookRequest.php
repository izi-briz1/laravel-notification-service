<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_message_id' => ['required', 'string'],
            'status' => ['required', Rule::in(['delivered', 'failed'])],
            'info' => ['nullable', 'string'],
        ];
    }
}
