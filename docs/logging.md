# Request logging

The plugin can record every request made to the API of a panel, and adds an **API logs** page to the panel to browse them.

## Enabling logging

Logging is off by default. Turn it on per panel:

```php
FilamentApiPlugin::make()
    ->logRequests()
```

The logs are stored in the `filament_api_requests` table. The package's migration creates it, so run:

```bash
php artisan migrate
```

To change the table, for example to add columns or indexes, publish the migration first:

```bash
php artisan vendor:publish --tag=filament-api-migrations
```

## What's recorded

Each request records:

| Field | Example |
|---|---|
| Time, method and path | `GET /api/shop/orders` |
| Query string | `filter[status]=new&per_page=25` |
| Endpoint, action and record | `shop/orders`, `update`, `42` |
| Route name | `filament-api.admin.shop.orders.update` |
| Status code and duration | `422`, `18 ms` |
| Error message, for failed requests | `The title field is required.` |
| User, API token and tenant | the user's morph type and key, the token's ID and name |
| IP address and user agent | `203.0.113.7`, `MyApp/1.2` |

Request and response bodies are **not** recorded, because they can contain personal data and passwords.

The duration starts before authentication and rate limiting, so failed requests (`401`, `403`, `422`, `429`...) are recorded too. Requests to URLs that match no route, and requests with a method the route doesn't support (`404` and `405` raised while matching the route), are not recorded.

The token's name is copied into the log, so logs keep it after the token is revoked.

### Performance

The log entry is written after the response has been sent to the client (in the middleware's `terminate()` method), so it doesn't slow the response down. On servers that can't send the response early, such as `php artisan serve`, the write happens before the connection closes. Each request adds one `INSERT`.

If writing a log entry fails, the error is reported with Laravel's exception handler, and the request isn't affected.

## Retention

Logs are kept for 30 days by default. The package schedules Laravel's `model:prune` command to delete older logs every day, so make sure [the scheduler is running](https://laravel.com/docs/scheduling#running-the-scheduler).

```php
FilamentApiPlugin::make()
    ->logRequests()
    ->logRetention(days: 90)   // Keep logs for 90 days
    ->logRetention(null)       // Or keep them forever
```

Each panel prunes its own logs with its own retention. You can also prune them manually:

```bash
php artisan model:prune --model="Allandereal\FilamentApi\Models\ApiRequest"
```

## The API logs page

When logging is on, the panel gets an **API logs** page, next to **API tokens** in the **API** navigation group, with:

- **Stats for the last 24 hours**: the number of requests, the error rate and number of server errors, the average response time, and the slowest endpoint.
- **A table of requests**, newest first, refreshed every 10 seconds. You can search by path, and filter by status (successful, client errors, server errors), method, endpoint, token and time.
- **The details of each request**: click a row to see everything that was recorded.

The page only shows the requests made to the API of its own panel.

Put the page in a navigation group:

```php
FilamentApiPlugin::make()
    ->logRequests()
    ->logsNavigationGroup('Settings')
```

### Who can see the logs

Logs include the users, IP addresses and query strings of every request. By default, every user who can access the panel can see them, like a resource without a policy. To restrict the page, define a `viewApiLogs` gate:

```php
// AppServiceProvider::boot()
Gate::define('viewApiLogs', fn (User $user): bool => $user->is_admin);
```

Users who fail the gate don't see the page in the navigation, and get `403` if they open its URL.

## Querying the logs

Logs are regular Eloquent models, so you can query them in your own code, widgets or reports:

```php
use Allandereal\FilamentApi\Models\ApiRequest;

ApiRequest::query()
    ->where('panel', 'admin')
    ->where('status_code', '>=', 500)
    ->where('created_at', '>=', now()->subHour())
    ->count();
```

`ApiRequest::user()` is a morph-to relationship to the user who made the request.
