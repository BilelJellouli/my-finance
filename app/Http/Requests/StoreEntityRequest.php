<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\EntityColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntityRequest extends FormRequest
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
            'color' => ['required', Rule::enum(EntityColor::class)],
            'accounts' => ['required', 'array', 'min:1', 'max:20'],
            'accounts.*.name' => ['required', 'string', 'max:120'],
            'accounts.*.currency' => ['required', Rule::enum(Currency::class)],
            'accounts.*.amount' => ['nullable', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
            'accounts.*.is_main' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $accounts = $this->input('accounts', []);
            if (! is_array($accounts) || count($accounts) === 0) {
                return;
            }

            $mainCount = collect($accounts)
                ->filter(fn ($account) => filter_var($account['is_main'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->count();

            if ($mainCount !== 1) {
                $validator->errors()->add('accounts', __('Exactly one account must be marked as main.'));
            }
        });
    }
}
