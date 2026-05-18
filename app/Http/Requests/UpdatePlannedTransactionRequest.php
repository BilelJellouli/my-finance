<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\PlannedTransactionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlannedTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'due_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::enum(PlannedTransactionStatus::class)],
            'is_mandatory' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
