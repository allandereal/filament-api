<?php

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Http\Controllers\ModelController;
use Allandereal\FilamentApi\Http\Controllers\ResourceController;
use Allandereal\FilamentApi\Http\Middleware\ForceJsonResponse;
use Allandereal\FilamentApi\Http\Middleware\ServeFilamentApi;
use Filament\Http\Middleware\IdentifyTenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

foreach (FilamentApi::getPanels() as $panel) {
    $plugin = FilamentApi::getPlugin($panel);

    $resources = FilamentApi::getResourceEndpoints($panel);
    $models = $plugin->getModels();

    // Tenant-aware panels get the tenant in the URL, like the panel itself: `api/{tenant}/shop/orders`.
    $prefix = $plugin->getPrefix($panel) . ($panel->hasTenancy() ? '/{tenant}' : '');

    Route::prefix($prefix)
        ->name("filament-api.{$panel->getId()}.")
        ->middleware([
            ForceJsonResponse::class,
            ...$plugin->getMiddleware(),
            ServeFilamentApi::class . ":{$panel->getId()}",
            IdentifyTenant::class,
        ])
        ->group(function () use ($models, $resources): void {
            // Collection routes are registered before record routes, so that `shop/products` is never
            // mistaken for the record `products` of a `shop` endpoint.
            foreach ($resources as $slug => $resource) {
                $name = str_replace('/', '.', $slug);

                Route::get($slug, [ResourceController::class, 'index'])->name("{$name}.index")->defaults('filamentApiResource', $resource);
                Route::post($slug, [ResourceController::class, 'store'])->name("{$name}.store")->defaults('filamentApiResource', $resource);
            }

            foreach (array_keys($models) as $slug) {
                Route::get($slug, [ModelController::class, 'index'])->name(str_replace('/', '.', $slug) . '.index')->defaults('filamentApiModel', $slug);
            }

            foreach ($resources as $slug => $resource) {
                $name = str_replace('/', '.', $slug);

                Route::get("{$slug}/{record}", [ResourceController::class, 'show'])->name("{$name}.show")->defaults('filamentApiResource', $resource);
                Route::match(['put', 'patch'], "{$slug}/{record}", [ResourceController::class, 'update'])->name("{$name}.update")->defaults('filamentApiResource', $resource);
                Route::delete("{$slug}/{record}", [ResourceController::class, 'destroy'])->name("{$name}.destroy")->defaults('filamentApiResource', $resource);

                foreach (FilamentApi::getRelationManagers($resource) as $relationship => $relationManager) {
                    $relationshipSlug = Str::kebab($relationship);

                    Route::get("{$slug}/{record}/{$relationshipSlug}", [ResourceController::class, 'relationIndex'])
                        ->name("{$name}.{$relationshipSlug}.index")
                        ->defaults('filamentApiResource', $resource)
                        ->defaults('filamentApiRelationManager', $relationManager);
                }
            }

            foreach (array_keys($models) as $slug) {
                Route::get("{$slug}/{record}", [ModelController::class, 'show'])->name(str_replace('/', '.', $slug) . '.show')->defaults('filamentApiModel', $slug);
            }
        });
}
