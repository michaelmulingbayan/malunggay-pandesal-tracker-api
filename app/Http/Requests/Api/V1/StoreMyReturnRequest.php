<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMyReturnRequest extends FormRequest
{
    /**
     * Only riders (users with a rider profile) return their own stock here.
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
            // Optional: leave it out to return everything still held (the usual end-of-duty return).
            'quantity_returned' => ['nullable', 'integer', 'min:1', 'max:100000'],
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
