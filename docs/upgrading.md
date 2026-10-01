# Upgrading from 1.0

Version 1.0 exposed models through a single catch-all route with no authentication. This version is rebuilt around Filament resources, and almost every part of it changed.

## 1. Enable the plugin

The `config/filament-api.php` file is no longer used, and nothing is exposed until you add the plugin to a panel:

```php
->plugin(FilamentApiPlugin::make())
```

Delete `config/filament-api.php` from your application.

## 2. Authenticate your clients

Requests now require an authenticated user who can access the panel. Set up [Sanctum](installation.md#authenticate-requests), or [replace the middleware](configuration.md#options). Then send a token with every request.

## 3. Update the URLs

Endpoints now use the resource's panel path:

| 1.0 | Now |
|---|---|
| `/api/orders` | `/api/shop/orders` (the resource's slug) |
| `/api/blog-categories` | `/api/blog/categories` |
| `/api/brands` | `/api/shop/products/brands` (in a cluster) |
| `/api/payments?filter[order_id]=1` | `/api/shop/orders/1/payments` (a relation manager) |

Run `php artisan route:list --name=filament-api` to see the new URLs.

## 4. Move the config models to the plugin

Models that were mapped in the config file and don't have a resource move to `->models()`. Each model now declares its own filters and sorts, instead of sharing one global list:

```php
// Before: config/filament-api.php
'models' => [
    'tags' => \Spatie\Tags\Tag::class,
],

// After: the panel provider
FilamentApiPlugin::make()
    ->models([
        'tags' => [
            'model' => \Spatie\Tags\Tag::class,
            'filters' => ['type'],
            'sorts' => ['name'],
        ],
    ])
```

Model endpoints are read-only. See [Models without a resource](models.md).

## 5. Update the query parameters

| 1.0 | Now |
|---|---|
| `?perPage=50` | `?per_page=50`, capped by `->maxPerPage()` |
| `?filter[status]=new` on any column in the global list | `?filter[status]=new`, using the resource table's filters |
| `?sort=name` on any column in the global list | `?sort=name`, using the table's sortable columns |
| `?fields[...]` | Removed. Responses return the model's attributes. Use `$hidden` to remove attributes. |

There's also a new `?search=` parameter, and a new `?tab=` parameter.

## 6. Check the writes

In 1.0, `POST`, `PUT` and `PATCH` didn't save any field. Now they save the resource form's fields, validated with the form's rules. Read [Creating, updating and deleting records](writing.md) before you send writes from existing clients.

## Removed

- Endpoints discovered from model relationships, such as `/api/comments` because `Post` has a `comments()` relationship. Use relation manager endpoints, or expose the model with `->models()`.
- The global filter and sort lists.
- The `filament-api` Artisan command, the migration and the config file.
