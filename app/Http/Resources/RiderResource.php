<?php

namespace App\Http\Resources;

use App\Models\Rider;
use App\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rider
 */
class RiderResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone_number' => $this->phone_number,
            'address' => $this->address,
            'availability_status' => $this->availability_status,
            'is_active' => $this->user->status === UserStatus::Active,
            'created_at' => $this->created_at,
        ];
    }
}
