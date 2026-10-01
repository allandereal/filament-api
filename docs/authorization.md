# Authorization

Every request goes through these checks, in this order.

## 1. Authentication

The request must have an authenticated user (`$request->user()`). The plugin's middleware authenticates it, `auth:sanctum` by default. Guests get `401 Unauthenticated`.

```php
FilamentApiPlugin::make()
    ->middleware(['api', 'auth:sanctum', 'throttle:60,1'])
```

If you replace the middleware, keep an authentication middleware in the list. Without one, every request is refused with `401`.

## 2. Panel access

If the user model implements `FilamentUser`, the user must be allowed into the panel, otherwise the API responds with `403 Forbidden`:

```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->is_admin;
}
```

Then the API logs the user into the panel's auth guard for the duration of the request, so that Filament, your policies and code that calls `Filament::auth()->user()` see the same user as in the panel.

On panels with tenancy, the user must also have access to the tenant in the URL (`HasTenants::canAccessTenant()`), otherwise the API responds with `404`.

## 3. Resource authorization

Each action is authorized with the resource, using the same methods as the panel. The methods use your model's policy:

| Request | Resource method | Policy method |
|---|---|---|
| `GET /api/posts` | `canViewAny()` | `viewAny` |
| `POST /api/posts` | `canCreate()` | `create` |
| `GET /api/posts/{record}` | `canView($record)` | `view` |
| `PUT` / `PATCH /api/posts/{record}` | `canEdit($record)` | `update` |
| `DELETE /api/posts/{record}` | `canDelete($record)` | `delete` |
| `GET /api/posts/{record}/comments` | `canView($record)` on the resource, then `canViewForRecord()` on the relation manager | `view`, then `viewAny` on the related model |

A denied request gets `403 Forbidden`.

As in Filament:

- If the model has no policy, or the policy doesn't define the method, the action is allowed. Use `Resource::$shouldCheckPolicyExistence` or a `Gate::before()` callback to change this.
- If the resource overrides `canViewAny()`, `canCreate()`... the override is used.
- If the resource skips authorization (`$shouldSkipAuthorization`), every action is allowed.

Records are also limited to the resource's query, `Resource::getEloquentQuery()`. A record outside of it, for example in another tenant, gets `404`.

## 4. Token abilities

If the request is authenticated with an API token, the token must have access to the endpoint: read access for `GET` requests, and write access for `POST`, `PUT`, `PATCH` and `DELETE` requests. Otherwise the API responds with `403`:

```json
{"message": "This API token doesn't have write access to shop/orders."}
```

Abilities only narrow down what the user can do. They never grant something the user's policies deny. See [API tokens](tokens.md#access-levels).

## Model endpoints

[Models without a resource](models.md) are authorized with the model's policy: `viewAny` to list records and `view` to show one. If the model has no policy, any user who passes the first two checks can read them.

## Limiting what's exposed

- Expose only some resources with `->resources([...])` or `->excludeResources([...])`. See [Configuration](configuration.md#choosing-resources).
- Use `$hidden` on your models for attributes that must never be returned, such as passwords and tokens.
- Give each integration its own token, with only the access it needs. See [API tokens](tokens.md).
- Use separate panels with different plugin settings for different kinds of API clients.
