<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Policies;

use Allandereal\FilamentApi\Tests\Fixtures\Models\Post;
use Allandereal\FilamentApi\Tests\Fixtures\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->email !== 'reader@example.com';
    }

    public function update(User $user, Post $post): bool
    {
        return $post->status !== 'locked';
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->status !== 'locked';
    }
}
