<?php

use Allandereal\FilamentApi\Pages\ApiTokens;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Post;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Secret;
use Allandereal\FilamentApi\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function tokenUser(string $email = 'admin@example.com'): User
{
    return User::create(['name' => 'Test', 'email' => $email, 'password' => 'secret']);
}

describe('token abilities', function () {
    beforeEach(function () {
        $this->post = Post::create(['title' => 'Hello']);

        Secret::create(['name' => 'a']);
    });

    it('allows everything with full access', function () {
        Sanctum::actingAs(tokenUser(), ['*']);

        getJson('api/posts')->assertOk();
        postJson('api/posts', ['title' => 'New'])->assertCreated();
        deleteJson("api/posts/{$this->post->id}")->assertNoContent();
    });

    it('allows reading every endpoint with read-only access', function () {
        Sanctum::actingAs(tokenUser(), ['admin:*:read']);

        getJson('api/posts')->assertOk();
        getJson("api/posts/{$this->post->id}")->assertOk();
        getJson("api/posts/{$this->post->id}/comments")->assertOk();
        getJson('api/categories')->assertOk();
        getJson('api/secrets')->assertOk();

        postJson('api/posts', ['title' => 'New'])
            ->assertForbidden()
            ->assertJson(['message' => "This API token doesn't have write access to posts."]);
        patchJson("api/posts/{$this->post->id}", ['title' => 'Changed'])->assertForbidden();
        deleteJson("api/posts/{$this->post->id}")->assertForbidden();

        expect(Post::count())->toBe(1)
            ->and($this->post->refresh()->title)->toBe('Hello');
    });

    it('limits custom tokens to their endpoints', function () {
        Sanctum::actingAs(tokenUser(), ['admin:posts:read', 'admin:categories:write']);

        getJson('api/posts')->assertOk();
        postJson('api/posts', ['title' => 'New'])->assertForbidden();

        getJson('api/categories')->assertForbidden();
        postJson('api/categories', ['name' => 'News'])->assertCreated();

        getJson('api/secrets')->assertForbidden();
    });

    it('ignores the abilities of other panels', function () {
        Sanctum::actingAs(tokenUser(), ['other:*:read', 'other:posts:read']);

        getJson('api/posts')->assertForbidden();
    });

    it('checks real tokens', function () {
        $token = tokenUser()->createToken('Integration', ['admin:posts:read'])->plainTextToken;

        $this->withToken($token)->getJson('api/posts')->assertOk();
        $this->withToken($token)->postJson('api/posts', ['title' => 'New'])->assertForbidden();
    });

    it('rejects expired tokens', function () {
        $token = tokenUser()->createToken('Old', ['*'], now()->subDay())->plainTextToken;

        $this->withToken($token)->getJson('api/posts')->assertUnauthorized();
    });

    it('does not limit requests without a token', function () {
        actingAs(tokenUser());

        postJson('api/posts', ['title' => 'New'])->assertCreated();
    });
});

describe('tokens page', function () {
    beforeEach(function () {
        actingAs($this->user = tokenUser());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    });

    it('renders', function () {
        $this->get(ApiTokens::getUrl())
            ->assertOk()
            ->assertSee('API tokens');
    });

    it('creates a read-only token by default', function () {
        $component = Livewire::test(ApiTokens::class)
            ->callAction('create', ['name' => 'Reporting'])
            ->assertHasNoActionErrors()
            ->assertNotified('API token created')
            ->assertSee('Copy your new API token');

        $token = $this->user->tokens()->sole();

        expect($token->name)->toBe('Reporting')
            ->and($token->abilities)->toBe(['admin:*:read'])
            ->and($token->expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
            ->and($component->get('plainTextToken'))->toStartWith("{$token->id}|");

        $this->withToken($component->get('plainTextToken'))->getJson('api/posts')->assertOk();
    });

    it('creates a custom token', function () {
        Livewire::test(ApiTokens::class)
            ->callAction('create', [
                'name' => 'Sync',
                'expires_in' => 'never',
                'access' => 'custom',
                'read' => ['posts', 'secrets'],
                'write' => ['categories'],
            ])
            ->assertHasNoActionErrors();

        $token = $this->user->tokens()->sole();

        expect($token->abilities)->toBe(['admin:posts:read', 'admin:secrets:read', 'admin:categories:write'])
            ->and($token->expires_at)->toBeNull();
    });

    it('ignores endpoints that do not exist', function () {
        Livewire::test(ApiTokens::class)
            ->callAction('create', [
                'name' => 'Sync',
                'access' => 'custom',
                'read' => ['posts'],
                'write' => ['secrets', 'nope'],
            ]);

        // `secrets` is a read-only model endpoint, and `nope` doesn't exist.
        expect($this->user->tokens()->sole()->abilities)->toBe(['admin:posts:read']);
    });

    it('requires an endpoint for custom tokens', function () {
        Livewire::test(ApiTokens::class)
            ->callAction('create', ['name' => 'Sync', 'access' => 'custom'])
            ->assertHasActionErrors(['read' => 'required_without']);

        expect($this->user->tokens()->count())->toBe(0);
    });

    it('creates a full access token', function () {
        Livewire::test(ApiTokens::class)
            ->callAction('create', ['name' => 'Admin', 'access' => 'full'])
            ->assertHasNoActionErrors();

        expect($this->user->tokens()->sole()->abilities)->toBe(['*']);
    });

    it('hides the token once dismissed', function () {
        Livewire::test(ApiTokens::class)
            ->callAction('create', ['name' => 'Reporting'])
            ->call('dismissToken')
            ->assertSet('plainTextToken', null)
            ->assertDontSee('Copy your new API token');
    });

    it('only lists and revokes your own tokens', function () {
        $this->user->createToken('Mine');
        tokenUser('other@example.com')->createToken('Theirs');

        $mine = $this->user->tokens()->sole();

        Livewire::test(ApiTokens::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCountTableRecords(1)
            ->callTableAction(DeleteAction::class, $mine);

        expect($this->user->tokens()->count())->toBe(0)
            ->and(User::firstWhere('email', 'other@example.com')->tokens()->count())->toBe(1);
    });
});
