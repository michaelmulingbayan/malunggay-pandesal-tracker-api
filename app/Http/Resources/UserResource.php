<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'rider' => $this->whenLoaded('rider', fn (): ?array => $this->rider ? [
                'id' => $this->rider->id,
                'phone_number' => $this->rider->phone_number,
                'address' => $this->rider->address,
                'availability_status' => $this->rider->availability_status,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
