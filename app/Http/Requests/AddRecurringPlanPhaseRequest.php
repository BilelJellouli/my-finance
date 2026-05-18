<?php

namespace App\Http\Requests;

use App\Enums\RecurringFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddRecurringPlanPhaseRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'frequency' => ['required', Rule::enum(RecurringFrequency::class)],
            'interval_step' => ['nullable', 'integer', 'min:1', 'max:52'],
            'anchor_day' => ['nullable', 'integer', 'min:0', 'max:31'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
