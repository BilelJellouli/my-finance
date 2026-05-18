<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('occurred_on')) {
            $this->merge(['occurred_on' => now()->toDateString()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
