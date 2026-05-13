<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount' => ['nullable', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
            'is_main' => ['required', 'boolean'],
        ];
    }
}
