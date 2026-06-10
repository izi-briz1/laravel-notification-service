<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateNotificationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Валидация только структурная: идентификаторы получателей принимаются
     * как есть, без проверки формата phone/email (см. ТЗ).
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(Channel::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'text' => ['required', 'string'],
            'recipient_ids' => ['required', 'array', 'min:1'],
            'recipient_ids.*' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ключ идемпотентности передаётся стандартным заголовком Idempotency-Key
        if ($this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }

        // Числовые идентификаторы приводим к строкам — храним как есть
        if (is_array($this->input('recipient_ids'))) {
            $this->merge([
                'recipient_ids' => array_map(
                    static fn ($id) => is_scalar($id) ? (string) $id : $id,
                    $this->input('recipient_ids'),
                ),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'The Idempotency-Key header is required.',
        ];
    }
}
