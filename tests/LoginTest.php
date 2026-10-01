<?php

use Allandereal\FilamentApi\Models\ApiRequest;
use Allandereal\FilamentApi\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => Hash::make('correct-password'),
        'remember_token' => 'remember-me',
    ]);
});

/**
 * Sanctum's guard keeps the user it resolved, so forget it to authenticate the next request from scratch.
 */
function forgetAuthentication(): void
{
    app('auth')->forgetGuards();
}

describe('login', function () {
    it('issues a token for the user', function () {
        $response = postJson('api/login', [
            'email' => 'ada@example.com',
            'password' => 'correct-password',
            'device_name' => 'Ada’s laptop',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $token = PersonalAccessToken::findToken($response->json('token'));

        expect($token->tokenable->is($this->user))->toBeTrue()
            ->and($token->name)->toBe('Login: Ada’s laptop')
            ->and($token->abilities)->toBe(['admin:*:read', 'admin:*:write'])
            ->and($token->expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
            ->and($response->json('expires_at'))->toBe($token->expires_at->toIso8601String());
    });

    it('issues tokens that can read and write the panel', function () {
        $token = postJson('api/login', ['email' => 'ada@example.com', 'password' => 'correct-password'])->json('token');

        forgetAuthentication();

        $this->withToken($token)->getJson('api/posts')->assertOk();
        $this->withToken($token)->postJson('api/posts', ['title' => 'New'])->assertCreated();
    });

    it('names the token after the user agent by default', function () {
        $token = $this->withHeader('User-Agent', 'MyApp/1.2')
            ->postJson('api/login', ['email' => 'ada@example.com', 'password' => 'correct-password'])
            ->json('token');

        expect(PersonalAccessToken::findToken($token)->name)->toBe('Login: MyApp/1.2');
    });

    it('gives the same error for unknown emails and wrong passwords', function () {
        $wrongPassword = postJson('api/login', ['email' => 'ada@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->json('errors.email');

        $unknownEmail = postJson('api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->json('errors.email');

        expect($wrongPassword)->toBe($unknownEmail)
            ->and(PersonalAccessToken::count())->toBe(0);
    });

    it('validates the credentials', function () {
        postJson('api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('limits the attempts per email and IP address', function () {
        foreach (range(1, 5) as $attempt) {
            postJson('api/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }

        postJson('api/login', ['email' => 'ada@example.com', 'password' => 'correct-password'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        // Another email isn't limited.
        postJson('api/login', ['email' => 'other@example.com', 'password' => 'wrong'])->assertUnprocessable();

        expect(PersonalAccessToken::count())->toBe(0);
    });

    it('refuses users who cannot access the panel', function () {
        User::create(['name' => 'Blocked', 'email' => 'blocked@example.com', 'password' => Hash::make('correct-password')]);

        postJson('api/login', ['email' => 'blocked@example.com', 'password' => 'correct-password'])
            ->assertForbidden();

        expect(PersonalAccessToken::count())->toBe(0);
    });

    it('does not record the password in the logs', function () {
        postJson('api/login', ['email' => 'ada@example.com', 'password' => 'correct-password'])->assertOk();

        $log = ApiRequest::sole();

        expect($log->path)->toBe('/api/login')
            ->and($log->user_id)->toBe($this->user->id)
            ->and(json_encode($log->toArray()))->not->toContain('correct-password');
    });

    it('is only available on panels that enable it', function () {
        postJson('api/app/login', ['email' => 'ada@example.com', 'password' => 'correct-password'])->assertNotFound();
    });
});

describe('user', function () {
    it('returns the current user', function () {
        $token = $this->user->createToken('Test', ['admin:posts:read'])->plainTextToken;

        $this->withToken($token)->getJson('api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $this->user->id)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    });

    it('rejects guests', function () {
        getJson('api/user')->assertUnauthorized();
    });
});

describe('logout', function () {
    it('revokes the current token only', function () {
        $current = $this->user->createToken('Current')->plainTextToken;
        $other = $this->user->createToken('Other')->plainTextToken;

        $this->withToken($current)->postJson('api/logout')->assertNoContent();

        forgetAuthentication();

        $this->withToken($current)->getJson('api/user')->assertUnauthorized();

        forgetAuthentication();

        $this->withToken($other)->getJson('api/user')->assertOk();
    });
});
