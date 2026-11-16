<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distribution_id' => $this->distribution_id,
            'rider' => $this->whenLoaded('stockDistribution', fn (): array => [
                'id' => $this->stockDistribution->rider->id,
                'name' => $this->stockDistribution->rider->user->name,
            ]),
            'quantity_sold' => $this->quantity_sold,
            'price_per_piece' => $this->price_per_piece,
            'total_amount' => $this->total_amount,
            'sale_date' => $this->sale_date,
        ];
    }
}
