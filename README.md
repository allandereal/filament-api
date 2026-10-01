# Filament API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/allandereal/filament-api.svg?style=flat-square)](https://packagist.org/packages/allandereal/filament-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/allandereal/filament-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/allandereal/filament-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/allandereal/filament-api.svg?style=flat-square)](https://packagist.org/packages/allandereal/filament-api)

Turn the resources of a Filament panel into a REST API. The API doesn't re-implement your app: every request goes through the resource, exactly like the panel does.

| The panel has | The API uses it for |
|---|---|
| Policies (`canViewAny`, `canCreate`, `canEdit`, `canDelete`...) | Authorizing every request |
| `getEloquentQuery()` and tenancy | The records you can list, show, update and delete |
| The table's filters, searchable and sortable columns, and list page tabs | `?filter[...]`, `?search=`, `?sort=` and `?tab=` |
| The form, its validation rules and its relationships | Validating and saving `POST` / `PUT` / `PATCH` requests |
| The create and edit pages and their hooks (`mutateFormDataBeforeCreate()`...) | Creating and updating records |
| Relation managers | Nested endpoints, e.g. `GET /api/shop/orders/1/payments` |

Read the full [documentation](docs/README.md), or start with [installation](docs/installation.md). If you used version 1.0, read [Upgrading from 1.0](docs/upgrading.md).

## Installation

```bash
composer require allandereal/filament-api
```

Add the plugin to each panel you want to expose:

```php
use Allandereal\FilamentApi\FilamentApiPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentApiPlugin::make());
}
```

Nothing is exposed until the plugin is added to a panel.

## Authentication

The API refuses guests (`401`) and users that can't access the panel (`403`, see `FilamentUser::canAccessPanel()`).

By default, the routes use the `api`, `auth:sanctum` and `throttle:60,1` middleware, so you need [Laravel Sanctum](https://laravel.com/docs/sanctum) or another middleware that authenticates the user:

```php
FilamentApiPlugin::make()
    ->middleware(['api', 'auth:sanctum', 'throttle:api'])
```

Send `Accept: application/json` with every request. Errors are always JSON, but a `405 Method Not Allowed` response is only JSON when the client asks for it.

## Endpoints

Each resource gets an endpoint at the same path as in the panel, under the `api` prefix. If a resource is in a cluster, the endpoint includes the cluster's slug. For example, `OrderResource` with the slug `shop/orders` gets these routes:

| Method | URL | Action |
|---|---|---|
| `GET` | `/api/shop/orders` | List records |
| `POST` | `/api/shop/orders` | Create a record (`201`) |
| `GET` | `/api/shop/orders/{record}` | Show a record |
| `PUT` / `PATCH` | `/api/shop/orders/{record}` | Update a record |
| `DELETE` | `/api/shop/orders/{record}` | Delete a record (`204`) |
| `GET` | `/api/shop/orders/{record}/payments` | List the records of a relation manager |

Run `php artisan route:list --name=filament-api` to see every endpoint.

The default panel uses the `api` prefix, and other panels use `api/{panel-id}`. When a panel has tenancy, the tenant goes in the URL like in the panel: `/api/{tenant}/shop/orders`.

### Listing records

```http
GET /api/shop/orders?filter[status]=new&search=OR85&sort=-total_price&tab=processing&per_page=25&page=2
```

- `filter[name]=value` applies one of the table's filters. A select filter takes its value (`filter[status]=new`). A multiple select filter takes a comma-separated list (`filter[status]=new,processing`). A toggle filter takes a boolean (`filter[featured]=1`). For a filter with a custom form, pass the form fields: `filter[created_at][created_from]=2024-01-01`.
- `search` searches the table's searchable columns, like the table's search field.
- `sort` sorts by one of the table's sortable columns. Prefix it with `-` to sort in descending order.
- `tab` activates one of the list page's tabs. When you leave it out, the default tab applies, like in the panel.
- `per_page` defaults to 15 and is capped at 100. `page` picks the page.

Unknown filters, sorts and tabs are rejected with a `422` response that lists the allowed values.

### Creating and updating records

Send the fields of the resource's form as JSON:

```http
POST /api/blog/posts
Content-Type: application/json

{"title": "Hello", "slug": "hello", "blog_author_id": 1, "tags": ["news"]}
```

- The request runs through the resource's create or edit page, so the form's validation rules, default values, dehydration and relationships (selects, repeaters, tags...) and the page's hooks behave like in the panel. A resource without these pages, such as a simple (modal) resource, uses a default create or edit page.
- Only the form's fields are accepted. Any other field, such as `id`, is ignored.
- `PUT` and `PATCH` both do partial updates: fields you leave out keep their current value. A repeater you send replaces all of its items.
- Validation errors use the field names: `{"errors": {"title": ["The title field is required."]}}`.

The API only enforces the rules declared on the form. For example, Filament's `Select` doesn't check that the value is one of its options, so add `->in(...)` if you need that check.

## Configuration

```php
FilamentApiPlugin::make()
    ->prefix('api/v1')
    ->middleware(['api', 'auth:sanctum'])
    ->resources([OrderResource::class, ProductResource::class]) // Only expose these resources
    ->excludeResources([UserResource::class])                   // Or expose every resource except these
    ->perPage(25)
    ->maxPerPage(200)
```

### Models without a resource

You can also expose models that don't have a resource as read-only endpoints (`index` and `show`). If the model has a policy, its `viewAny` and `view` methods authorize the requests.

```php
FilamentApiPlugin::make()
    ->models([
        'tags' => Tag::class,
        'comments' => [
            'model' => Comment::class,
            'filters' => ['commentable_id', 'commentable_type'], // ?filter[commentable_id]=1 (exact match)
            'sorts' => ['id', 'created_at'],                      // ?sort=-created_at
        ],
        'taggables' => [
            'model' => Taggable::class,
            'default_sort' => 'tag_id', // Defaults to the primary key
        ],
    ])
```

These endpoints return the model's attributes, so set `$hidden` on the model for anything that must stay private.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Allan](https://github.com/allandereal)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
