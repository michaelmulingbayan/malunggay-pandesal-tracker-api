<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMySaleRequest extends FormRequest
{
    /**
     * Only riders (users with a rider profile) record their own sales here.
     */
    public function authorize(): bool
    {
        return $this->user()?->rider !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity_sold' => ['required', 'integer', 'min:1', 'max:100000'],
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
        ];
    }
}
