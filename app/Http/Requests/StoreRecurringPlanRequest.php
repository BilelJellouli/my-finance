<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\RecurringFrequency;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringPlanRequest extends FormRequest
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
            'owner_entity_id' => ['required', 'integer', 'exists:entities,id'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'counterparty_mode' => ['required', 'in:internal,external'],
            'internal_entity_id' => ['nullable', 'integer', 'exists:entities,id', 'different:owner_entity_id'],
            'counterparty_id' => ['nullable', 'integer', 'exists:counterparties,id'],
            'external_name' => ['nullable', 'string', 'max:120'],
            'direction' => ['required', Rule::enum(PlannedTransactionDirection::class)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'label' => ['required', 'string', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:120'],
            'is_mandatory' => ['required', 'boolean'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'frequency' => ['required', Rule::enum(RecurringFrequency::class)],
            'interval_step' => ['nullable', 'integer', 'min:1', 'max:52'],
            'anchor_day' => ['nullable', 'integer', 'min:0', 'max:31'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = $this->input('counterparty_mode');

            if ($mode === 'internal' && ! $this->filled('internal_entity_id')) {
                $validator->errors()->add('internal_entity_id', __('Pick an internal entity.'));
            }

            if ($mode === 'external' && ! $this->filled('counterparty_id') && ! $this->filled('external_name')) {
                $validator->errors()->add('external_name', __('Pick or add an external counterparty.'));
            }
        });
    }
}
