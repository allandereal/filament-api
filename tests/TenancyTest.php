<?php

use Allandereal\FilamentApi\Models\ApiRequest;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Comment;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Post;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Team;
use Allandereal\FilamentApi\Tests\Fixtures\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->ours = Team::create(['name' => 'Ours']);
    $this->theirs = Team::create(['name' => 'Theirs']);

    $this->user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $this->user->teams()->attach($this->ours);

    $this->ourPost = $this->ours->posts()->create(['title' => 'Ours']);
    $this->ours->posts()->create(['title' => 'Also ours']);
    $this->theirPost = $this->theirs->posts()->create(['title' => 'Theirs']);

    actingAs($this->user);
});

it('puts the tenant in the URL', function () {
    getJson('api/app/posts')->assertNotFound();
});

it('lists only the records of the tenant', function () {
    getJson("api/app/{$this->ours->id}/posts")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.*.team_id', [$this->ours->id, $this->ours->id]);
});

it('rejects tenants the user does not belong to', function () {
    getJson("api/app/{$this->theirs->id}/posts")->assertNotFound();
    getJson("api/app/{$this->theirs->id}/posts/{$this->theirPost->id}")->assertNotFound();
    getJson('api/app/999/posts')->assertNotFound();
});

it('hides the records of other tenants', function () {
    getJson("api/app/{$this->ours->id}/posts/{$this->ourPost->id}")->assertOk();

    getJson("api/app/{$this->ours->id}/posts/{$this->theirPost->id}")->assertNotFound();
    patchJson("api/app/{$this->ours->id}/posts/{$this->theirPost->id}", ['title' => 'Hacked'])->assertNotFound();
    deleteJson("api/app/{$this->ours->id}/posts/{$this->theirPost->id}")->assertNotFound();
    getJson("api/app/{$this->ours->id}/posts/{$this->theirPost->id}/comments")->assertNotFound();

    expect($this->theirPost->refresh()->title)->toBe('Theirs');
});

it('does not let key filters reach other tenants', function () {
    getJson("api/app/{$this->ours->id}/posts?filter[id]={$this->ourPost->id},{$this->theirPost->id}")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$this->ourPost->id]);
});

it('creates records in the tenant', function () {
    $id = postJson("api/app/{$this->ours->id}/posts", ['title' => 'New', 'team_id' => $this->theirs->id])
        ->assertCreated()
        ->json('data.id');

    expect(Post::find($id)->team_id)->toBe($this->ours->id);
});

it('lists related records of the tenant', function () {
    Comment::create(['post_id' => $this->ourPost->id, 'body' => 'Nice']);

    getJson("api/app/{$this->ours->id}/posts/{$this->ourPost->id}/comments")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('scopes token abilities to the panel', function () {
    Sanctum::actingAs($this->user, ['admin:*:read']);

    getJson("api/app/{$this->ours->id}/posts")->assertForbidden();

    Sanctum::actingAs($this->user, ['app:*:read']);

    getJson("api/app/{$this->ours->id}/posts")->assertOk();
});

it('records the tenant in the logs', function () {
    getJson("api/app/{$this->ours->id}/posts")->assertOk();

    expect(ApiRequest::sole())
        ->panel->toBe('app')
        ->tenant_key->toBe((string) $this->ours->id);
});
