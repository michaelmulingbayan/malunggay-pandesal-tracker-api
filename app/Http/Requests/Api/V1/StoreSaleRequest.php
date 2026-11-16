<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Sale;
use App\Models\StockDistribution;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * A missing distribution is left to validation (422) rather than authorization.
     */
    public function authorize(): bool
    {
        $distribution = StockDistribution::with('rider')->find($this->integer('distribution_id'));

        if ($distribution === null) {
            return $this->user() !== null;
        }

        return $this->user()?->can('create', [Sale::class, $distribution]) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'distribution_id' => ['required', 'integer', Rule::exists(StockDistribution::class, 'id')],
            'quantity_sold' => ['required', 'integer', 'min:1', 'max:100000'],
            // Normally omitted: the current set price is used. Only admins may override it (e.g. a discount).
            'price_per_piece' => [
                Rule::prohibitedIf(fn (): bool => ! $this->user()?->isAdmin()),
                'nullable',
                'decimal:0,2',
                'min:0.01',
                'max:10000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity_sold.min' => 'Sell at least 1 piece.',
            'price_per_piece.decimal' => 'Use a price like 3 or 3.50.',
            'price_per_piece.prohibited' => 'The price is set by the admin and cannot be changed when selling.',
        ];
    }
}
