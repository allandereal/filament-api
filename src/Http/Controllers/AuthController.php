<?php

namespace Allandereal\FilamentApi\Http\Controllers;

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Http\Resources\ApiResource;
use Allandereal\FilamentApi\Support\TokenAbilities;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Lets each user of an API client sign in with their own credentials, when the plugin uses `->login()`.
 */
class AuthController extends Controller
{
    public const MAX_ATTEMPTS_PER_MINUTE = 5;

    public function login(Request $request): JsonResponse
    {
        $panel = Filament::getPanel($request->route('filamentApiPanel'));

        Filament::setCurrentPanel($panel);

        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $throttleKey = 'filament-api-login:' . $panel->getId() . ':' . Str::transliterate(Str::lower($data['email'])) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, static::MAX_ATTEMPTS_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            abort(429, __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]), ['Retry-After' => $seconds]);
        }

        // Use the user provider of the panel's guard, so that the API logs in the same users as the panel.
        $provider = Auth::createUserProvider(config("auth.guards.{$panel->getAuthGuard()}.provider"));
        $user = $provider?->retrieveByCredentials(['email' => $data['email']]);

        // The same error for an unknown email and a wrong password, so that the API doesn't reveal who has an account.
        if ((! $user) || (! $provider->validateCredentials($user, ['password' => $data['password']]))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);

        abort_if(
            ($user instanceof FilamentUser) && (! $user->canAccessPanel($panel)),
            403,
            "You don't have access to this panel.",
        );

        abort_if(
            $panel->isEmailVerificationRequired() && ($user instanceof MustVerifyEmail) && (! $user->hasVerifiedEmail()),
            403,
            'Your email address is not verified.',
        );

        if (! method_exists($user, 'createToken')) {
            throw new LogicException('To log in through the API, the [' . $user::class . "] model must use Sanctum's [HasApiTokens] trait.");
        }

        $days = FilamentApi::getPlugin($panel)->getLoginTokenLifetime();
        $expiresAt = $days ? now()->addDays($days) : null;

        $device = $data['device_name'] ?? null;
        $device = filled($device) ? $device : (Str::limit((string) $request->userAgent(), 100, '') ?: 'API client');

        // The token can do everything the user can do, in this panel only.
        $token = $user->createToken("Login: {$device}", [
            TokenAbilities::readEverything($panel->getId()),
            TokenAbilities::writeEverything($panel->getId()),
        ], $expiresAt);

        // Record the user in the request logs.
        $request->setUserResolver(fn () => $user);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => (new ApiResource($user))->resolve($request),
        ]);
    }

    public function user(Request $request): ApiResource
    {
        return new ApiResource($request->user());
    }

    /**
     * Revoke the token of the request. The user's other tokens keep working.
     */
    public function logout(Request $request): Response
    {
        $user = $request->user();

        $token = ($user && method_exists($user, 'currentAccessToken')) ? $user->currentAccessToken() : null;

        if ($token instanceof Model) {
            $token->delete();
        }

        return response()->noContent();
    }
}
