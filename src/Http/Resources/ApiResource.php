<?php

namespace Allandereal\FilamentApi\Http\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ApiResource extends JsonResource
{
    /**
     * Attributes that are never returned for users, even if the model doesn't hide them.
     *
     * @var array<string>
     */
    public const SENSITIVE_USER_ATTRIBUTES = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($this->resource instanceof Authenticatable) {
            $data = Arr::except($data, static::SENSITIVE_USER_ATTRIBUTES);
        }

        return $data;
    }
}
