<?php

namespace App\Http\Requests\Api\V1;

use App\Models\StockDistribution;
use App\Models\StockReturn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockReturnRequest extends FormRequest
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

        return $this->user()?->can('create', [StockReturn::class, $distribution]) ?? false;
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
            'quantity_returned' => ['required', 'integer', 'min:1', 'max:100000'],
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
            'quantity_returned.min' => 'Return at least 1 piece.',
        ];
    }
}
