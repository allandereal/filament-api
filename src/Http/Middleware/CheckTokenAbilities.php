<?php

namespace Allandereal\FilamentApi\Http\Middleware;

use Allandereal\FilamentApi\Support\TokenAbilities;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class CheckTokenAbilities
{
    /**
     * Reject requests made with an API token that isn't scoped to the endpoint and action. Requests that aren't
     * authenticated with a token, such as session requests, are only authorized with the resource's policies.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if (! $token) {
            return $next($request);
        }

        $panel = Filament::getCurrentPanel()->getId();
        $endpoint = $request->route('filamentApiEndpoint');
        $action = $request->route('filamentApiAbility');

        // Routes that aren't endpoints, such as `user` and `logout`, are available to every token.
        if (! ($endpoint && $action)) {
            return $next($request);
        }

        foreach (TokenAbilities::allowing($panel, $endpoint, $action) as $ability) {
            if ($token->can($ability)) {
                return $next($request);
            }
        }

        abort(403, "This API token doesn't have {$action} access to {$endpoint}.");
    }
}
