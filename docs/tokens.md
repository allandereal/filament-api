# API tokens

The plugin adds an **API tokens** page to the panel. Users create, scope and revoke their own [Sanctum](https://laravel.com/docs/sanctum) tokens there, so you don't have to build token management yourself.

## Requirements

The page is shown to users whose model uses Sanctum's `HasApiTokens` trait. It needs Sanctum 3.3 or higher, for token expiration:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
}
```

Users who don't have the trait don't see the page in the navigation, and get `403` if they open its URL.

## Creating a token

Click **Create token** and fill in:

- **Name**: a name to recognize the token, such as the application that uses it.
- **Expiration**: 7, 30 (the default), 60 or 90 days, 1 year, or no expiration.
- **Access**: what the token can do. See [Access levels](#access-levels).

The token is then shown once, with a button to copy it. Sanctum only stores a hash of the token, so it can't be shown again. If it's lost, revoke it and create a new one.

Send the token in the `Authorization` header:

```bash
curl https://example.com/api/shop/orders \
  -H "Authorization: Bearer 1|AbCdEf..." \
  -H "Accept: application/json"
```

## Access levels

| Access | The token can | Sanctum abilities |
|---|---|---|
| **Read-only** (the default) | List and show the records of every endpoint of the panel | `admin:*:read` |
| **Custom** | Read and write the endpoints you choose | `admin:shop/orders:read`, `admin:blog/posts:write`... |
| **Full access** | Do everything the user can do, in every panel, and in other routes of your app that check Sanctum abilities | `*` |

The abilities start with the panel's ID (`admin` above), so a token created in one panel only works in that panel, unless it has full access.

- **Read** allows `GET` requests: listing records, showing a record, and listing the records of a relation manager.
- **Write** allows `POST`, `PUT`, `PATCH` and `DELETE` requests: creating, updating and deleting records. It doesn't include read access, so choose both for a token that does both.
- [Model endpoints](models.md) are read-only, so they can only be chosen for read access.

A token's access is checked on top of the user's own permissions: a token never allows what the user's [policies](authorization.md#3-resource-authorization) deny.

If you add, rename or remove a resource, existing tokens keep their abilities. A token with access to a renamed endpoint has to be recreated.

## Revoking tokens

The page lists the user's own tokens, with their access, when they were last used and when they expire. Expired tokens are shown in red, and Sanctum rejects them with `401`.

Click **Revoke** on a token, or select several tokens and use **Revoke selected**. Applications using a revoked token get `401` on their next request.

Users can only see and revoke their own tokens.

## Configuration

```php
FilamentApiPlugin::make()
    ->tokensNavigationGroup('Settings') // Put the page in a navigation group
    ->tokens(false)                     // Or remove the page
```

When the page is removed, you can still issue tokens in your own code. Use the same abilities to scope them:

```php
use Allandereal\FilamentApi\Support\TokenAbilities;

$user->createToken('Reporting', [
    TokenAbilities::readEverything('admin'),                                 // admin:*:read
    TokenAbilities::make('admin', 'shop/orders', TokenAbilities::WRITE),     // admin:shop/orders:write
], now()->addMonth());
```

## Requests without a token

Token abilities only apply to requests authenticated with a token. Requests authenticated another way, such as Sanctum's cookie-based SPA authentication, are only authorized with the user's permissions.
