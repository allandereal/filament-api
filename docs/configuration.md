# Configuration reference

The API is configured on the plugin, separately for each panel:

```php
use Allandereal\FilamentApi\FilamentApiPlugin;

FilamentApiPlugin::make()
    ->prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'throttle:api'])
    ->excludeResources([UserResource::class])
    ->models(['tags' => Tag::class])
    ->perPage(25)
    ->maxPerPage(200)
```

The package has no config file.

## Options

| Method | Default | Description |
|---|---|---|
| `prefix(string $prefix)` | `api` for the default panel, `api/{panel-id}` for other panels | The URL prefix of the endpoints. See [Prefix](endpoints.md#prefix). |
| `middleware(array $middleware)` | `['api', 'auth:sanctum', 'throttle:60,1']` | The middleware of the routes. It must authenticate the user. See [Authentication](installation.md#authenticate-requests). |
| `resources(array $resources)` | every resource of the panel | Only expose these resources. |
| `excludeResources(array $resources)` | `[]` | Expose every resource except these. |
| `models(array $models)` | `[]` | Read-only endpoints for models without a resource. See [Models without a resource](models.md). |
| `perPage(int $perPage)` | `15` | The default page size. |
| `maxPerPage(int $maxPerPage)` | `100` | The largest `per_page` a client can request. |

The middleware you set replaces the default list, so include an authentication middleware and a rate limiter. The package always adds three middleware of its own around your list:

1. Before your list, it forces JSON responses.
2. After your list, it sets up the panel and checks that the user can access it.
3. On panels with tenancy, it then identifies the tenant.

## Choosing resources

```php
// Only these resources
FilamentApiPlugin::make()->resources([
    OrderResource::class,
    ProductResource::class,
])

// Every resource except these
FilamentApiPlugin::make()->excludeResources([
    UserResource::class,
])
```

Resources must belong to the panel. Listing a resource that isn't registered on the panel has no effect.

## Several panels

Each panel that uses the plugin gets its own endpoints, with its own resources, authorization and settings:

```php
// AdminPanelProvider: /api/...
->plugin(FilamentApiPlugin::make())

// AppPanelProvider (with tenancy): /api/app/{tenant}/...
->plugin(FilamentApiPlugin::make())
```

## Getting the plugin's settings

During an API request, the current panel is set, so you can read the plugin's settings like any Filament plugin:

```php
FilamentApiPlugin::get()->getPerPage();
```

The `FilamentApi` facade lists the panels and endpoints:

```php
use Allandereal\FilamentApi\Facades\FilamentApi;

FilamentApi::getPanels();                          // The panels that use the plugin
FilamentApi::getResourceEndpoints($panel);         // ['shop/orders' => OrderResource::class, ...]
FilamentApi::getRelationManagers(OrderResource::class); // ['payments' => PaymentsRelationManager::class]
```
