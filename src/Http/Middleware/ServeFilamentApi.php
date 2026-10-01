<?php

namespace Allandereal\FilamentApi\Http\Middleware;

use Closure;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class ServeFilamentApi
{
    /**
     * Boot the panel the same way Filament does for its own pages, so that resources, policies and
     * tenancy behave exactly like they do in the panel.
     */
    public function handle(Request $request, Closure $next, string $panel): mixed
    {
        $panel = Filament::getPanel($panel);

        Filament::setCurrentPanel($panel);

        Filament::bootCurrentPanel();

        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        abort_if(
            ($user instanceof FilamentUser) && (! $user->canAccessPanel($panel)),
            403,
        );

        // Filament authorizes resources against the panel's guard, which is usually the session guard.
        Filament::auth()->setUser($user);

        Filament::setServingStatus();

        ServingFilament::dispatch();

        return $next($request);
    }
}
