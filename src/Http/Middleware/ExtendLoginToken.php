<?php

namespace Allandereal\FilamentApi\Http\Middleware;

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Support\TokenAbilities;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ExtendLoginToken
{
    /**
     * Only write the new expiry when it moves by more than this, so that busy clients don't write on every request.
     */
    public const MIN_EXTENSION_SECONDS = 60;

    /**
     * Push back the expiry of a login token that expires after a period of inactivity, so that it stays valid while
     * the client uses it, like the client's session. It never goes past the token's absolute lifetime.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        $token = ($user && method_exists($user, 'currentAccessToken')) ? $user->currentAccessToken() : null;

        $abilities = ($token instanceof Model) ? $token->getAttribute('abilities') : null;

        if (is_array($abilities) && ($idleSeconds = TokenAbilities::getIdleSeconds($abilities))) {
            $expiresAt = now()->addSeconds($idleSeconds);

            if ($days = FilamentApi::getPlugin(Filament::getCurrentPanel())->getLoginTokenLifetime()) {
                $expiresAt = $expiresAt->min($token->getAttribute('created_at')->copy()->addDays($days));
            }

            $currentExpiresAt = $token->getAttribute('expires_at');

            if ((! $currentExpiresAt) || ($currentExpiresAt->diffInSeconds($expiresAt, false) > static::MIN_EXTENSION_SECONDS)) {
                $token->forceFill(['expires_at' => $expiresAt])->save();
            }
        }

        return $next($request);
    }
}
