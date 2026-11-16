<?php

namespace App\Http\Resources;

use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Production
 */
class ProductionResource extends JsonResource
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
            'production_date' => $this->production_date->toDateString(),
            'quantity_produced' => $this->quantity_produced,
            'remarks' => $this->remarks,
            'recorded_by' => $this->whenLoaded('recorder', fn (): array => [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
