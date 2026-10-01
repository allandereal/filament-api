# Login endpoints

By default, an API client authenticates with a token that a user created on the [API tokens](tokens.md) page. When each user of the client should sign in with their own email and password instead, for example a Filament app whose users and data all live in this API, enable the login endpoints:

```php
FilamentApiPlugin::make()
    ->login()
```

This adds three endpoints at the plugin's prefix:

| Method | URL | Action |
|---|---|---|
| `POST` | `/api/login` | Exchange an email and password for a token |
| `GET` | `/api/user` | The current user |
| `POST` | `/api/logout` | Revoke the current token |

They need Sanctum: the user model must use the `HasApiTokens` trait.

> [!WARNING]
> New Laravel apps define their own `GET /api/user` route in `routes/api.php`. It's registered after the package's routes, so it replaces the package's endpoint, which skips the panel access check and returns the user without a `data` key. Remove it when you enable `->login()`.

## Logging in

```http
POST /api/login
Accept: application/json
Content-Type: application/json

{"email": "ada@example.com", "password": "secret", "device_name": "Ada's laptop"}
```

`device_name` is optional. It names the token on the API tokens page, as `Login: Ada's laptop`. Without it, the token is named after the request's user agent.

On success, the response is `200 OK`:

```json
{
    "token": "12|AbCdEf...",
    "token_type": "Bearer",
    "expires_at": "2026-10-31T11:13:14+00:00",
    "user": {"id": 1, "name": "Ada", "email": "ada@example.com"}
}
```

Send the token in the `Authorization: Bearer` header of the next requests.

| Response | When |
|---|---|
| `422 Unprocessable Entity` | The email or password is missing, or the credentials are wrong. Unknown emails and wrong passwords get the same error, so the API doesn't reveal who has an account. |
| `403 Forbidden` | The credentials are right, but the user can't access the panel (`FilamentUser::canAccessPanel()`), or the panel requires a verified email address and the user hasn't verified theirs. No token is issued. |
| `429 Too Many Requests` | Too many failed attempts: see [Rate limiting](#rate-limiting). |

The user is looked up with the user provider of the panel's auth guard, so the API logs in the same users as the panel's login page.

## Tokens issued by the login

- **Access**: the token can do everything the user can do in this panel (`{panel}:*:read` and `{panel}:*:write`), and nothing in other panels. The user's [policies](authorization.md) still apply.
- **Expiration**: 30 days by default. Clients get `401` once the token expires, and should then ask the user to log in again.
- **One token per login**: each login creates a new token, so a user can be logged in on several devices. Users see these tokens on the API tokens page, and can revoke them there.

```php
FilamentApiPlugin::make()
    ->login()
    ->loginTokenLifetime(days: 7)   // Expire after 7 days
    ->loginTokenLifetime(null)      // Or never expire
```

## The current user

```http
GET /api/user
Authorization: Bearer 12|AbCdEf...
```

```json
{"data": {"id": 1, "name": "Ada", "email": "ada@example.com"}}
```

## Logging out

```http
POST /api/logout
Authorization: Bearer 12|AbCdEf...
```

This revokes the token of the request and responds with `204 No Content`. The user's other tokens keep working.

## Sensitive attributes

The login response, `GET /api/user` and every other endpoint never return these attributes of a user, even if the model doesn't hide them: `password`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`, `app_authentication_secret` and `app_authentication_recovery_codes`.

This only applies to the user itself. When a user is returned inside another record, for example as an eager loaded relationship, only the model's `$hidden` applies, so hide these attributes on the model too.

## Rate limiting

Failed logins are limited to 5 a minute per email address and IP address. Further attempts get `429 Too Many Requests` with a `Retry-After` header, even with the right password. A successful login resets the count.

The login endpoint is called by guests, so it doesn't use the plugin's middleware, which authenticates the user. It uses the `api` middleware group instead. Change it with `->loginMiddleware()`:

```php
FilamentApiPlugin::make()
    ->login()
    ->loginMiddleware(['api', 'throttle:20,1'])
```

The `user` and `logout` endpoints use the plugin's middleware, like the other endpoints.

## Reading users

The login endpoints don't expose other users. To let clients read users, for example to show the author of a record, expose them like any other model:

- with a `UserResource` in the panel, which also lets clients create and update users through its form, so that passwords are hashed and validated by the API, or
- as a read-only [model endpoint](models.md):

```php
FilamentApiPlugin::make()
    ->login()
    ->models([
        'users' => [
            'model' => User::class,
            'filters' => ['id', 'email'],
            'sorts' => ['id', 'name'],
        ],
    ])
```

## Logging

When [request logging](logging.md) is on, logins are recorded with the user who logged in. Request bodies are never recorded, so passwords don't end up in the logs.

## Tenancy

On panels with tenancy, the login endpoints don't have the tenant in their URL: `/api/app/login`, not `/api/app/{tenant}/login`. After logging in, clients call the tenant's endpoints with the token.
