<?php

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Category;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Comment;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Post;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Secret;
use Allandereal\FilamentApi\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

function user(string $email = 'admin@example.com'): User
{
    return User::create(['name' => 'Test', 'email' => $email, 'password' => 'secret']);
}

describe('access', function () {
    it('rejects guests', function () {
        getJson('api/posts')->assertUnauthorized();
        postJson('api/posts', ['title' => 'Hi'])->assertUnauthorized();
        deleteJson('api/posts/1')->assertUnauthorized();
    });

    it('returns json to guests that do not ask for it', function () {
        $this->get('api/posts')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    });

    it('rejects users that cannot access the panel', function () {
        actingAs(user('blocked@example.com'));

        getJson('api/posts')->assertForbidden();
    });

    it('only exposes the resources of panels that use the plugin', function () {
        actingAs(user());

        getJson('api/other/posts')->assertNotFound();
    });

    it('does not expose arbitrary models or methods', function () {
        actingAs(user());

        getJson('api/users')->assertNotFound();
        getJson('api/save')->assertNotFound();
        getJson('api/comments')->assertNotFound();
    });
});

describe('index', function () {
    beforeEach(function () {
        actingAs(user());

        Post::create(['title' => 'Banana', 'status' => 'draft']);
        Post::create(['title' => 'Apple', 'status' => 'published', 'body' => 'Text']);
        Post::create(['title' => 'Cherry', 'status' => 'published']);
    });

    it('paginates with the plugin defaults', function () {
        getJson('api/posts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);

        getJson('api/posts?per_page=5&page=1')->assertJsonCount(3, 'data');
    });

    it('caps the page size', function () {
        getJson('api/posts?per_page=6')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        getJson('api/posts?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    });

    it('applies the table filters', function () {
        getJson('api/posts?filter[status]=published')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        getJson('api/posts?filter[has_body]=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Apple');
    });

    it('applies the default state of deferred filters', function () {
        // The table defers its filters, and has a query builder filter that needs its default state.
        getJson('api/posts?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    });

    it('filters and sorts by the record key', function () {
        $ids = Post::orderBy('id')->pluck('id');

        getJson("api/posts?filter[id]={$ids[0]},{$ids[2]}&sort=-id")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$ids[2], $ids[0]]);

        getJson("api/posts?filter[id][]={$ids[1]}")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$ids[1]]);

        getJson('api/posts?sort=id&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.*.id', $ids->all());
    });

    it('rejects unknown filters', function () {
        getJson('api/posts?filter[title]=Apple')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.title');
    });

    it('searches the searchable columns', function () {
        getJson('api/posts?search=err')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Cherry');
    });

    it('sorts by sortable columns only', function () {
        // Without operator filters, which allow sorting by any visible column.
        FilamentApi::getPlugin(Filament::getPanel('admin'))->operatorFilters(false);

        getJson('api/posts?sort=-title&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.*.title', ['Cherry', 'Banana', 'Apple']);

        getJson('api/posts?sort=status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    });

    it('applies the list page tabs', function () {
        getJson('api/posts?tab=drafts')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        getJson('api/posts?tab=nope')->assertUnprocessable()->assertJsonValidationErrors('tab');
    });
});

describe('records', function () {
    beforeEach(fn () => actingAs(user()));

    it('shows a record', function () {
        $post = Post::create(['title' => 'Hello']);

        getJson("api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Hello');

        getJson('api/posts/999')->assertNotFound();
    });

    it('validates with the resource form', function () {
        postJson('api/posts', ['title' => str_repeat('a', 21), 'status' => 'unknown'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'status']);

        postJson('api/posts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    });

    it('creates a record through the create page', function () {
        postJson('api/posts', ['title' => 'Hello', 'id' => 999, 'created_at' => '2000-01-01'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Hello')
            // The form's default value.
            ->assertJsonPath('data.status', 'draft')
            // Set by the page's `mutateFormDataBeforeCreate()` hook.
            ->assertJsonPath('data.body', 'Written by the create page.');

        $post = Post::sole();

        expect($post->id)->not->toBe(999)
            ->and($post->created_at->year)->not->toBe(2000);
    });

    it('authorizes creation with the policy', function () {
        actingAs(user('reader@example.com'));

        postJson('api/posts', ['title' => 'Hello'])->assertForbidden();

        expect(Post::count())->toBe(0);
    });

    it('partially updates a record', function () {
        $post = Post::create(['title' => 'Hello', 'body' => 'Body', 'status' => 'draft']);

        patchJson("api/posts/{$post->id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Hello')
            ->assertJsonPath('data.body', 'Body')
            ->assertJsonPath('data.status', 'published');

        putJson("api/posts/{$post->id}", ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        expect($post->refresh()->title)->toBe('Hello');
    });

    it('authorizes updates and deletes with the policy', function () {
        $post = Post::create(['title' => 'Hello', 'status' => 'locked']);

        patchJson("api/posts/{$post->id}", ['title' => 'Changed'])->assertForbidden();
        deleteJson("api/posts/{$post->id}")->assertForbidden();

        expect($post->refresh()->title)->toBe('Hello');
    });

    it('deletes a record', function () {
        $post = Post::create(['title' => 'Hello']);

        deleteJson("api/posts/{$post->id}")->assertNoContent();

        expect(Post::count())->toBe(0);

        deleteJson("api/posts/{$post->id}")->assertNotFound();
    });

    it('rejects unsupported methods', function () {
        $post = Post::create(['title' => 'Hello']);

        postJson("api/posts/{$post->id}")->assertMethodNotAllowed();
    });

    it('creates and updates records of simple resources', function () {
        postJson('api/categories', [])->assertUnprocessable()->assertJsonValidationErrors('name');

        $id = postJson('api/categories', ['name' => 'News'])
            ->assertCreated()
            ->json('data.id');

        patchJson("api/categories/{$id}", ['name' => 'Updates'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updates');

        expect(Category::find($id)->name)->toBe('Updates');
    });
});

describe('relation managers', function () {
    it('lists the related records', function () {
        actingAs(user());

        $post = Post::create(['title' => 'Hello']);
        $other = Post::create(['title' => 'Other']);

        Comment::create(['post_id' => $post->id, 'body' => 'First']);
        Comment::create(['post_id' => $post->id, 'body' => 'Second']);
        Comment::create(['post_id' => $other->id, 'body' => 'Elsewhere']);

        getJson("api/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        getJson("api/posts/{$post->id}/comments?search=Sec")
            ->assertOk()
            ->assertJsonPath('data.*.body', ['Second']);

        getJson('api/posts/999/comments')->assertNotFound();
    });
});

describe('models', function () {
    beforeEach(function () {
        actingAs(user());

        Secret::create(['name' => 'b']);
        Secret::create(['name' => 'a']);
    });

    it('lists and shows models', function () {
        getJson('api/secrets?sort=name')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['a', 'b']);

        getJson('api/secrets?filter[name]=a')->assertOk()->assertJsonPath('meta.total', 1);

        getJson('api/secrets/1')->assertOk()->assertJsonPath('data.name', 'b');
        getJson('api/secrets/999')->assertNotFound();
    });

    it('rejects filters and sorts that are not allowed', function () {
        // Without operator filters, which allow sorting by any visible column.
        FilamentApi::getPlugin(Filament::getPanel('admin'))->operatorFilters(false);

        getJson('api/secrets?filter[id]=1')->assertUnprocessable()->assertJsonValidationErrors('filter');
        getJson('api/secrets?sort=created_at')->assertUnprocessable()->assertJsonValidationErrors('sort');
    });

    it('is read-only', function () {
        postJson('api/secrets', ['name' => 'c'])->assertMethodNotAllowed();
        deleteJson('api/secrets/1')->assertMethodNotAllowed();
    });
});
