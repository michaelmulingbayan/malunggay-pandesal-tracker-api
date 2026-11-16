<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Rider;
use App\Models\StockDistribution;
use App\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockDistributionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockDistribution::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rider_id' => ['required', 'integer', Rule::exists(Rider::class, 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('rider_id')) {
                    return;
                }

                $rider = Rider::with('user')->find($this->integer('rider_id'));

                if ($rider?->user->status !== UserStatus::Active) {
                    $validator->errors()->add('rider_id', 'This rider is inactive and cannot be assigned stock.');
                }
            },
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
            'rider_id.required' => 'Choose a rider.',
            'rider_id.exists' => 'The selected rider does not exist.',
            'quantity.min' => 'Assign at least 1 piece.',
        ];
    }
}
