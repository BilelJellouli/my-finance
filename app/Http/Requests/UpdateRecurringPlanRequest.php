<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'purpose' => ['nullable', 'string', 'max:120'],
            'is_mandatory' => ['required', 'boolean'],
            'ends_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
