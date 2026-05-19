<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\TransactionKind;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
            'from_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'to_account_id' => ['nullable', 'integer', 'exists:accounts,id', 'different:from_account_id'],
            'counterparty_id' => ['nullable', 'integer', 'exists:counterparties,id'],
            'kind' => ['required', Rule::enum(TransactionKind::class)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('from_account_id') && ! $this->filled('to_account_id')) {
                $validator->errors()->add('from_account_id', __('Pick at least one account.'));
            }
        });
    }
}
