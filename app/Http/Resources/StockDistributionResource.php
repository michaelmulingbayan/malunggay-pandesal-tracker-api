<?php

namespace App\Http\Resources;

use App\Models\StockDistribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockDistribution
 */
class StockDistributionResource extends JsonResource
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
            'production_id' => $this->production_id,
            'rider' => $this->whenLoaded('rider', fn (): array => [
                'id' => $this->rider->id,
                'name' => $this->rider->user->name,
            ]),
            'quantity_distributed' => $this->quantity_distributed,
            'distribution_date' => $this->distribution_date->toDateString(),
            'distributed_by' => $this->whenLoaded('distributor', fn (): array => [
                'id' => $this->distributor->id,
                'name' => $this->distributor->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
