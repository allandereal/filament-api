<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;

    protected $guarded = [];

    protected $hidden = ['password'];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->email !== 'blocked@example.com';
    }
}
