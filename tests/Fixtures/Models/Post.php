<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = ['title', 'body', 'status', 'views', 'secret_note'];

    protected $hidden = ['secret_note'];

    /**
     * The tenant of the post in the `app` panel.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
