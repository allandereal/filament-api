# Installation

## Install the package

```bash
composer require allandereal/filament-api
```

## Enable the API on a panel

The API is enabled per panel. Add the plugin to each panel you want to expose:

```php
use Allandereal\FilamentApi\FilamentApiPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        // ...
        ->plugin(FilamentApiPlugin::make());
}
```

Nothing is exposed until you do this. Every resource of the panel gets an endpoint, and you can [limit which ones](configuration.md#choosing-resources).

Check the routes:

```bash
php artisan route:list --name=filament-api
```

## Authenticate requests

The API refuses guests. By default, the routes run through the `api`, `auth:sanctum` and `throttle:60,1` middleware, so you need [Laravel Sanctum](https://laravel.com/docs/sanctum):

```bash
php artisan install:api   # Laravel 11+, or: composer require laravel/sanctum
```

Your user model needs the `HasApiTokens` trait:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
}
```

Issue a token, for example from a page in your panel or from Tinker:

```php
$token = $user->createToken('my-integration')->plainTextToken;
```

Then send it with each request, along with `Accept: application/json`:

```bash
curl https://example.com/api/shop/orders \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

> [!NOTE]
> Sanctum 4 stores an expiry date on each token. If your `personal_access_tokens` table was created by an older version of Sanctum, add a nullable `expires_at` timestamp column, or `createToken()` will fail.

To use another authentication method, replace the middleware. The only requirement is that the request has an authenticated user (`$request->user()`):

```php
FilamentApiPlugin::make()
    ->middleware(['api', 'auth:api'])
```

## Next steps

- [Endpoints](endpoints.md) lists the URLs the API exposes.
- [Authorization](authorization.md) explains which user can do what.
