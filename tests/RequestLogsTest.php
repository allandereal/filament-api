<?php

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Models\ApiRequest;
use Allandereal\FilamentApi\Pages\ApiLogs;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Post;
use Allandereal\FilamentApi\Tests\Fixtures\Models\User;
use Allandereal\FilamentApi\Widgets\ApiRequestsOverview;
use Filament\Facades\Filament;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

function logUser(string $email = 'admin@example.com'): User
{
    return User::create(['name' => 'Ada', 'email' => $email, 'password' => 'secret']);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function apiLog(array $attributes = []): ApiRequest
{
    return ApiRequest::create([
        'panel' => 'admin',
        'method' => 'GET',
        'path' => '/api/posts',
        'endpoint' => 'posts',
        'action' => 'index',
        'status_code' => 200,
        'duration_ms' => 12,
        ...$attributes,
    ]);
}

describe('logging', function () {
    it('records requests', function () {
        actingAs($user = logUser());

        getJson('api/posts?filter[status]=draft&per_page=2')->assertOk();

        $log = ApiRequest::sole();

        expect($log)
            ->panel->toBe('admin')
            ->method->toBe('GET')
            ->path->toBe('/api/posts')
            ->query->toBe(['filter' => ['status' => 'draft'], 'per_page' => '2'])
            ->route_name->toBe('filament-api.admin.posts.index')
            ->endpoint->toBe('posts')
            ->action->toBe('index')
            ->record_key->toBeNull()
            ->status_code->toBe(200)
            ->duration_ms->toBeGreaterThanOrEqual(0)
            ->user_type->toBe($user->getMorphClass())
            ->user_id->toBe($user->id)
            ->token_id->toBeNull()
            ->ip->toBe('127.0.0.1')
            ->error->toBeNull();
    });

    it('records the record and the error of failed requests', function () {
        actingAs(logUser());

        $post = Post::create(['title' => 'Hello']);

        patchJson("api/posts/{$post->id}", ['title' => ''])->assertUnprocessable();

        expect(ApiRequest::sole())
            ->method->toBe('PATCH')
            ->action->toBe('update')
            ->record_key->toBe((string) $post->id)
            ->status_code->toBe(422)
            ->error->toBe('The title field is required.');
    });

    it('records guests', function () {
        getJson('api/posts')->assertUnauthorized();

        expect(ApiRequest::sole())
            ->status_code->toBe(401)
            ->user_id->toBeNull()
            ->error->toBe('Unauthenticated.');
    });

    it('records the token', function () {
        $user = logUser();
        $token = $user->createToken('Reporting', ['admin:*:read']);

        $this->withToken($token->plainTextToken)->getJson('api/posts')->assertOk();
        $this->withToken($token->plainTextToken)->postJson('api/posts', ['title' => 'New'])->assertForbidden();

        [$read, $write] = ApiRequest::orderBy('id')->get();

        expect($read->token_id)->toBe($token->accessToken->id)
            ->and($read->token_name)->toBe('Reporting')
            ->and($write->status_code)->toBe(403)
            ->and($write->error)->toBe("This API token doesn't have write access to posts.");
    });

    it('does not record requests when logging is off', function () {
        FilamentApi::getPlugin(Filament::getPanel('admin'))->logRequests(false);

        actingAs(logUser());

        getJson('api/posts')->assertOk();

        expect(ApiRequest::count())->toBe(0);
    });
});

describe('pruning', function () {
    it('prunes the logs older than the retention', function () {
        $old = apiLog(['created_at' => now()->subDays(31)]);
        $recent = apiLog(['created_at' => now()->subDays(29)]);
        $otherPanel = apiLog(['panel' => 'other', 'created_at' => now()->subDays(100)]);

        $this->artisan('model:prune', ['--model' => [ApiRequest::class]])->assertSuccessful();

        expect(ApiRequest::pluck('id')->all())->toEqualCanonicalizing([$recent->id, $otherPanel->id]);
    });

    it('keeps the logs forever without a retention', function () {
        FilamentApi::getPlugin(Filament::getPanel('admin'))->logRetention(null);

        apiLog(['created_at' => now()->subYears(2)]);

        $this->artisan('model:prune', ['--model' => [ApiRequest::class]])->assertSuccessful();

        expect(ApiRequest::count())->toBe(1);
    });

    it('schedules the pruning daily', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => $event->description === 'filament-api:prune-request-logs');

        expect($event)->not->toBeNull()
            ->and($event->command)->toContain('model:prune')
            ->and($event->expression)->toBe('0 0 * * *');
    });
});

describe('logs page', function () {
    beforeEach(function () {
        actingAs(logUser());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    });

    it('renders', function () {
        $this->get(ApiLogs::getUrl())
            ->assertOk()
            ->assertSee('API logs')
            ->assertSee('Logs are kept for 30 days.');
    });

    it('lists the logs of the panel', function () {
        $log = apiLog();
        $otherPanel = apiLog(['panel' => 'other']);

        Livewire::test(ApiLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertCanNotSeeTableRecords([$otherPanel]);
    });

    it('filters by status', function () {
        $successful = apiLog();
        $clientError = apiLog(['status_code' => 404]);
        $serverError = apiLog(['status_code' => 500]);

        Livewire::test(ApiLogs::class)
            ->filterTable('status', 'server_error')
            ->assertCanSeeTableRecords([$serverError])
            ->assertCanNotSeeTableRecords([$successful, $clientError])
            ->filterTable('status', 'client_error')
            ->assertCanSeeTableRecords([$clientError])
            ->assertCanNotSeeTableRecords([$successful, $serverError]);
    });

    it('shows the details of a request', function () {
        $log = apiLog([
            'query' => ['filter' => ['status' => 'new']],
            'status_code' => 422,
            'error' => 'The filter [nope] is not allowed.',
            'token_name' => 'Reporting',
        ]);

        Livewire::test(ApiLogs::class)
            ->mountTableAction(ViewAction::class, $log)
            ->assertSee('filter[status]=new')
            ->assertSee('The filter [nope] is not allowed.')
            ->assertSee('Reporting');
    });

    it('summarizes the last 24 hours', function () {
        apiLog(['duration_ms' => 10]);
        apiLog(['duration_ms' => 30, 'endpoint' => 'categories']);
        apiLog(['status_code' => 500, 'duration_ms' => 20]);
        apiLog(['status_code' => 404, 'duration_ms' => 20]);
        apiLog(['created_at' => now()->subDays(2), 'duration_ms' => 999]);

        Livewire::test(ApiRequestsOverview::class)
            ->assertSee('Requests')
            ->assertSee('50%')
            ->assertSee('1 server error (5xx)')
            ->assertSee('20 ms')
            ->assertSee('30 ms')
            ->assertSee('categories (average)');
    });

    it('can be restricted with a gate', function () {
        Gate::define('viewApiLogs', fn (User $user): bool => $user->email === 'ops@example.com');

        $this->get(ApiLogs::getUrl())->assertForbidden();

        actingAs(logUser('ops@example.com'));

        $this->get(ApiLogs::getUrl())->assertOk();
    });
});
