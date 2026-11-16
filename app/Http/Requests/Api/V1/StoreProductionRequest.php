<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Production;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Production::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'quantity_produced' => ['required', 'integer', 'min:1', 'max:100000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
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
            'production_date.before_or_equal' => 'Production cannot be recorded for a future date.',
            'quantity_produced.min' => 'Enter at least 1 piece.',
        ];
    }
}
