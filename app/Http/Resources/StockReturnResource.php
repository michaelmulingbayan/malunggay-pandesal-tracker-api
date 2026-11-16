<?php

namespace App\Http\Resources;

use App\Models\StockReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockReturn
 */
class StockReturnResource extends JsonResource
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
            'quantity_distributed' => $this->whenLoaded('stockDistribution', fn (): int => $this->stockDistribution->quantity_distributed),
            'quantity_returned' => $this->quantity_returned,
            'return_date' => $this->return_date,
            'status' => $this->status,
            'verified_by' => $this->whenLoaded('verifier', fn (): ?array => $this->verifier ? [
                'id' => $this->verifier->id,
                'name' => $this->verifier->name,
            ] : null),
        ];
    }
}
