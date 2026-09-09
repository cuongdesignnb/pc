<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\PublicAssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => PublicAssetUrl::normalize($this->avatar),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'created_at' => $this->created_at?->toISOString(),
            'default_address' => $this->whenLoaded('defaultAddress', fn () => $this->defaultAddress
                ? AccountAddressResource::make($this->defaultAddress)->resolve($request)
                : null),
        ];
    }
}
