<?php

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Http\Controllers\AuthController;
use Allandereal\FilamentApi\Http\Controllers\ModelController;
use Allandereal\FilamentApi\Http\Controllers\ResourceController;
use Allandereal\FilamentApi\Http\Middleware\CheckTokenAbilities;
use Allandereal\FilamentApi\Http\Middleware\ExtendLoginToken;
use Allandereal\FilamentApi\Http\Middleware\ForceJsonResponse;
use Allandereal\FilamentApi\Http\Middleware\LogApiRequest;
use Allandereal\FilamentApi\Http\Middleware\ServeFilamentApi;
use Allandereal\FilamentApi\Support\TokenAbilities;
use Filament\Http\Middleware\IdentifyTenant;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Every route knows its endpoint and whether it reads or writes, to check the abilities of API tokens.
 */
$endpoint = fn (RouteInstance $route, string $slug, string $ability): RouteInstance => $route
    ->defaults('filamentApiEndpoint', $slug)
    ->defaults('filamentApiAbility', $ability);

foreach (FilamentApi::getPanels() as $panel) {
    $plugin = FilamentApi::getPlugin($panel);

    $resources = FilamentApi::getResourceEndpoints($panel);
    $models = $plugin->getModels();

    if ($plugin->hasLogin()) {
        foreach (['login', 'logout', 'user'] as $reserved) {
            if (array_key_exists($reserved, $resources) || array_key_exists($reserved, $models)) {
                throw new LogicException("The API endpoint [{$reserved}] of the [{$panel->getId()}] panel is reserved by ->login().");
            }
        }

        // Guests call the login endpoint, so it doesn't use the plugin's authentication middleware. The user and
        // logout endpoints aren't tenant-specific, so they don't have the tenant in their URL.
        Route::prefix($plugin->getPrefix($panel))
            ->name("filament-api.{$panel->getId()}.")
            ->middleware([
                LogApiRequest::class . ":{$panel->getId()}",
                ForceJsonResponse::class,
                ...$plugin->getLoginMiddleware(),
            ])
            ->group(function () use ($panel): void {
                Route::post('login', [AuthController::class, 'login'])->name('login')->defaults('filamentApiPanel', $panel->getId());
            });

        Route::prefix($plugin->getPrefix($panel))
            ->name("filament-api.{$panel->getId()}.")
            ->middleware([
                LogApiRequest::class . ":{$panel->getId()}",
                ForceJsonResponse::class,
                ...$plugin->getMiddleware(),
                ServeFilamentApi::class . ":{$panel->getId()}",
                ExtendLoginToken::class,
            ])
            ->group(function (): void {
                Route::get('user', [AuthController::class, 'user'])->name('user');
                Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            });
    }

    // Tenant-aware panels get the tenant in the URL, like the panel itself: `api/{tenant}/shop/orders`.
    $prefix = $plugin->getPrefix($panel) . ($panel->hasTenancy() ? '/{tenant}' : '');

    Route::prefix($prefix)
        ->name("filament-api.{$panel->getId()}.")
        ->middleware([
            LogApiRequest::class . ":{$panel->getId()}",
            ForceJsonResponse::class,
            ...$plugin->getMiddleware(),
            ServeFilamentApi::class . ":{$panel->getId()}",
            IdentifyTenant::class,
            CheckTokenAbilities::class,
            ExtendLoginToken::class,
        ])
        ->group(function () use ($endpoint, $models, $resources): void {
            $read = TokenAbilities::READ;
            $write = TokenAbilities::WRITE;

            // Collection routes are registered before record routes, so that `shop/products` is never
            // mistaken for the record `products` of a `shop` endpoint.
            foreach ($resources as $slug => $resource) {
                $name = str_replace('/', '.', $slug);

                $endpoint(Route::get($slug, [ResourceController::class, 'index']), $slug, $read)->name("{$name}.index")->defaults('filamentApiResource', $resource);
                $endpoint(Route::post($slug, [ResourceController::class, 'store']), $slug, $write)->name("{$name}.store")->defaults('filamentApiResource', $resource);
            }

            foreach (array_keys($models) as $slug) {
                $endpoint(Route::get($slug, [ModelController::class, 'index']), $slug, $read)->name(str_replace('/', '.', $slug) . '.index')->defaults('filamentApiModel', $slug);
            }

            foreach ($resources as $slug => $resource) {
                $name = str_replace('/', '.', $slug);

                $endpoint(Route::get("{$slug}/{record}", [ResourceController::class, 'show']), $slug, $read)->name("{$name}.show")->defaults('filamentApiResource', $resource);
                $endpoint(Route::match(['put', 'patch'], "{$slug}/{record}", [ResourceController::class, 'update']), $slug, $write)->name("{$name}.update")->defaults('filamentApiResource', $resource);
                $endpoint(Route::delete("{$slug}/{record}", [ResourceController::class, 'destroy']), $slug, $write)->name("{$name}.destroy")->defaults('filamentApiResource', $resource);

                // Listing related records needs read access to the parent endpoint.
                foreach (FilamentApi::getRelationManagers($resource) as $relationship => $relationManager) {
                    $relationshipSlug = Str::kebab($relationship);

                    $endpoint(Route::get("{$slug}/{record}/{$relationshipSlug}", [ResourceController::class, 'relationIndex']), $slug, $read)
                        ->name("{$name}.{$relationshipSlug}.index")
                        ->defaults('filamentApiResource', $resource)
                        ->defaults('filamentApiRelationManager', $relationManager);
                }
            }

            foreach (array_keys($models) as $slug) {
                $endpoint(Route::get("{$slug}/{record}", [ModelController::class, 'show']), $slug, $read)->name(str_replace('/', '.', $slug) . '.show')->defaults('filamentApiModel', $slug);
            }
        });
}
