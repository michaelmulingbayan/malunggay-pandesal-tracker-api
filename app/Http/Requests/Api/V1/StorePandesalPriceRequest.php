<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PandesalPrice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePandesalPriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', PandesalPrice::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price_per_piece' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000'],
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
            'price_per_piece.decimal' => 'Use a price like 3 or 3.50.',
            'price_per_piece.min' => 'The price must be more than ₱0.',
        ];
    }
}
