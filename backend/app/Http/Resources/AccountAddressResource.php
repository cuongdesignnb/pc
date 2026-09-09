<?php

namespace App\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Address */
class AccountAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'label' => $this->label,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'province' => $this->province,
            'province_code' => $this->province_code,
            'district' => $this->district,
            'ward' => $this->ward,
            'ward_code' => $this->ward_code,
            'street' => $this->street,
            'full_address' => $this->full_address,
            'is_default' => (bool) $this->is_default,
        ];
    }
}
