# Endpoints

## Resource endpoints

Each resource gets an endpoint at the same path as in the panel, under the API prefix. The path comes from the resource's slug (`Resource::getSlug()`), with the cluster's slug in front if the resource is in a cluster.

| Resource | Panel URL | API endpoint |
|---|---|---|
| `PostResource` | `/admin/posts` | `/api/posts` |
| `OrderResource` with `$slug = 'shop/orders'` | `/admin/shop/orders` | `/api/shop/orders` |
| `BrandResource` in the `shop/products` cluster | `/admin/shop/products/brands` | `/api/shop/products/brands` |

Each resource endpoint has these routes:

| Method | URL | Action | Success |
|---|---|---|---|
| `GET` | `/api/shop/orders` | [List records](listing.md) | `200` |
| `POST` | `/api/shop/orders` | [Create a record](writing.md#creating-records) | `201` |
| `GET` | `/api/shop/orders/{record}` | Show a record | `200` |
| `PUT` / `PATCH` | `/api/shop/orders/{record}` | [Update a record](writing.md#updating-records) | `200` |
| `DELETE` | `/api/shop/orders/{record}` | [Delete a record](writing.md#deleting-records) | `204` |

`{record}` is the record's route key, the same key the panel uses in its URLs (`Resource::getRecordRouteKeyName()`, the primary key by default).

## Relation manager endpoints

Each relation manager of a resource gets a read-only endpoint under the record. The last segment is the relationship's name in kebab case:

| Relation manager | API endpoint |
|---|---|
| `PaymentsRelationManager` (`$relationship = 'payments'`) | `GET /api/shop/orders/{record}/payments` |
| `OrderItemsRelationManager` (`$relationship = 'orderItems'`) | `GET /api/shop/orders/{record}/order-items` |

These endpoints list records like the resource endpoints do, using the relation manager's table: see [Listing records](listing.md). Relation managers inside a `RelationGroup` are included too.

To create or update related records, use the related resource's own endpoint if it has one. Relationships that are part of the parent's form, such as a repeater, are saved with the parent: see [Relationships](writing.md#relationships).

## Model endpoints

Models without a resource can be exposed as read-only `index` and `show` endpoints. See [Models without a resource](models.md).

## Prefix

The default panel uses the `api` prefix, and other panels use `api/{panel-id}`. Change it with `->prefix()`:

```php
FilamentApiPlugin::make()->prefix('api/v1')
```

If two panels use the plugin, give them different prefixes.

## Tenancy

When a panel has [tenancy](https://filamentphp.com/docs/3.x/panels/tenancy), the tenant goes in the URL right after the prefix, like in the panel:

```
GET /api/{tenant}/shop/orders
```

The tenant is resolved with Filament's own `IdentifyTenant` middleware: the user must have access to it (`HasTenants::canAccessTenant()`), otherwise the API responds with `404`. Queries are then scoped to the tenant, and new records are associated with it, exactly like in the panel.

## Route names

Routes are named `filament-api.{panel}.{endpoint}.{action}`, with the slashes of the endpoint replaced by dots:

```php
route('filament-api.admin.shop.orders.index');
route('filament-api.admin.shop.orders.show', ['record' => 1]);
route('filament-api.admin.shop.orders.payments.index', ['record' => 1]);
```

The actions are `index`, `store`, `show`, `update` and `destroy`.

## Route caching

Routes are generated from the panels when the application boots, and work with `php artisan route:cache`. Clear the route cache after you add, remove or rename a resource or a relation manager.
