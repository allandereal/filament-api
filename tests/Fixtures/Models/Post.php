<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = ['title', 'body', 'status'];

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
