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
| `tokens(bool $condition = true)` | `true` | Add the [API tokens](tokens.md) page to the panel. |
| `tokensNavigationGroup(?string $group)` | `null` | The navigation group of the API tokens page. |
| `logRequests(bool $condition = true)` | `false` | Record every API request, and add the [API logs](logging.md) page to the panel. |
| `logRetention(?int $days)` | `30` | How many days to keep the logs. `null` keeps them forever. |
| `logsNavigationGroup(?string $group)` | `null` | The navigation group of the API logs page. |
| `operatorFilters(bool $condition = true)` | `false` | Allow [`where[{column}][{operator}]`](aggregates.md#operator-filters) filters on list endpoints, and [sorting by any visible column](aggregates.md#sorting-by-any-column). |
| `aggregates(bool $condition = true)` | `false` | Allow [`aggregate` and `group`](aggregates.md#aggregates) on list endpoints. |
| `login(bool $condition = true)` | `false` | Add the [login, user and logout endpoints](login.md). |
| `loginTokenLifetime(?int $days)` | `30` | How many days login tokens are valid, at most. `null` issues tokens that don't expire, unless the login sends [`expires_in`](login.md#expiring-with-the-clients-session). |
| `loginMiddleware(array $middleware)` | `['api']` | The middleware of the login endpoint. It must not require authentication. |

The middleware you set replaces the default list, so include an authentication middleware and a rate limiter. The package always adds middleware of its own around your list:

1. Before your list, it starts [logging](logging.md) the request (if logging is on) and forces JSON responses.
2. After your list, it sets up the panel and checks that the user can access it.
3. On panels with tenancy, it then identifies the tenant.
4. Last, it checks the [abilities of the API token](tokens.md#access-levels), if the request uses one.

## Rate limiting

The default middleware allows 60 requests per minute per user (`throttle:60,1`). That's enough for most integrations, but clients that load records one by one, such as an Eloquent driver backed by the API, can need many requests to render a single page. Raise the limit for them:

```php
FilamentApiPlugin::make()
    ->middleware(['api', 'auth:sanctum', 'throttle:600,1'])
```

Or use a [named rate limiter](https://laravel.com/docs/routing#rate-limiting) to give different users different limits:

```php
// AppServiceProvider::boot()
RateLimiter::for('filament-api', fn (Request $request) => $request->user()?->is_integration
    ? Limit::perMinute(1000)->by($request->user()->id)
    : Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

// The panel provider
FilamentApiPlugin::make()
    ->middleware(['api', 'auth:sanctum', 'throttle:filament-api'])
```

> [!IMPORTANT]
> Every rate limiter in the middleware list applies, so the lowest limit wins. In apps created with Laravel 10 or older, and in Laravel 11+ apps that call `->throttleApi()`, the `api` middleware group includes `throttle:api`, which allows 60 requests a minute by default. To raise the limit, either raise the `api` limiter in your app, or replace `api` with the middleware you need from it:
>
> ```php
> FilamentApiPlugin::make()
>     ->middleware([SubstituteBindings::class, 'auth:sanctum', 'throttle:600,1'])
> ```
>
> Run `php artisan route:list --path=api -v` to see the middleware of each route.

Rejected requests get `429 Too Many Requests` with a `Retry-After` header. Clients can also batch their reads with [`filter[id]=1,2,3`](listing.md#filtering-by-key) to need fewer requests.

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
