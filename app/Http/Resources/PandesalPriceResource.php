<?php

namespace App\Http\Resources;

use App\Models\PandesalPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PandesalPrice
 */
class PandesalPriceResource extends JsonResource
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
            'price_per_piece' => $this->price_per_piece,
            'set_by' => $this->whenLoaded('setter', fn (): array => [
                'id' => $this->setter->id,
                'name' => $this->setter->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
