# How it works

This page explains the internals, for contributors and for debugging. You don't need it to use the package.

The package doesn't reimplement Filament. Each API request drives the same Livewire components that render the panel: the resource's list page, create page, edit page and relation managers. These components are created in memory and never rendered.

## Booting

`FilamentApiServiceProvider` loads `routes/api.php`, which:

1. lists the panels that use `FilamentApiPlugin` (`FilamentApi::getPanels()`)
2. builds the endpoints of each panel from its resources (`FilamentApi::getResourceEndpoints()`), relation managers (`FilamentApi::getRelationManagers()`) and the plugin's `models()`
3. registers the routes. Collection routes are registered before record routes, so that `shop/products` (a collection) is never matched as the record `products` of a `shop` endpoint.

Each route stores the resource, relation manager or model in its route defaults (`filamentApiResource`, `filamentApiRelationManager`, `filamentApiModel`), along with its endpoint and whether it reads or writes (`filamentApiEndpoint`, `filamentApiAbility`). These defaults are strings, so the routes can be cached.

## Middleware

Each route runs through:

1. `LogApiRequest:{panel}`, which starts the timer if the panel logs requests. Its `terminate()` method writes the `ApiRequest` row after the response is sent.
2. `ForceJsonResponse`, which sets `Accept: application/json` so that errors render as JSON.
3. The plugin's middleware, `api`, `auth:sanctum` and `throttle:60,1` by default.
4. `ServeFilamentApi:{panel}`, which does what Filament's own panel middleware does:
   - sets the current panel and boots it (`Filament::setCurrentPanel()`, `Filament::bootCurrentPanel()`)
   - refuses guests (`401`) and users who fail `canAccessPanel()` (`403`)
   - sets the user on the panel's auth guard, because Filament authorizes with `Filament::auth()->user()`, which is usually the session guard and not the API guard
   - dispatches `ServingFilament`
5. Filament's `IdentifyTenant`, which resolves the `{tenant}` URL segment on panels with tenancy.
6. `CheckTokenAbilities`, which checks the abilities of the request's API token against the route's `filamentApiEndpoint` and `filamentApiAbility` defaults (`read` or `write`). It uses `Support\TokenAbilities`, and skips requests without a token.

## Listing: `TableQuery`

`ResourceController::index()` creates the resource's index page, a `ListRecords` Livewire component, and passes it to `Support\TableQuery::for()`. For relation managers, the relation manager component gets its owner record and page class first.

`TableQuery` then:

1. calls `mount()`, which authorizes and selects the default tab
2. calls `bootedInteractsWithTable()` to build the table, as Livewire would
3. fills the filters form with its defaults. Filament treats a filter without state as active, so this step is required
4. writes the request's parameters into the component's public properties: `tableFilters`, `tableSearch`, `tableSortColumn`, `tableSortDirection` and `activeTab`. It validates each one against the table first
5. returns `getFilteredSortedTableQuery()`: the table's query with the filters, search, sort, eager loading and tab applied

The controller then paginates the query.

## Writing: the create and edit pages

`store()` creates the resource's create page (a `CreateRecord` component), and `update()` creates its edit page (`EditRecord`). For resources without these pages, it uses `Support\Pages\CreateRecord` or `Support\Pages\EditRecord`, which are minimal pages bound to the resource at runtime. Then the controller:

1. calls `mount()`, which authorizes and fills the form with defaults (create) or with the record (edit)
2. merges the request body into the form state (`$page->data`), keeping only the keys the state already has
3. calls `create()` or `save(shouldRedirect: false, shouldSendSavedNotification: false)`. This validates the form, dehydrates it, runs the page's hooks and saves the record and its relationships
4. catches the `ValidationException` and renames the keys from `data.title` to `title`

## The API tokens page

`Pages\ApiTokens` is a regular Filament page with a table, registered by `FilamentApiPlugin::register()`. Its table queries the user's `tokens()` relationship, so users can only see and revoke their own tokens. Creating a token turns the chosen access into abilities, keeping only endpoints that exist, and calls Sanctum's `createToken()`. The plain-text token is kept in a locked Livewire property until the user dismisses it.

## Request logs

`LogApiRequest` is the first middleware of every API route, so the duration includes authentication and rate limiting, and rejected requests are recorded. It checks `isLoggingRequests()` at runtime, so turning logging on or off doesn't require clearing the route cache. `Models\ApiRequest` is mass-prunable: its `prunable()` query combines the retention of each panel, and the service provider schedules `model:prune` for it daily when a panel logs requests with a retention.

`Pages\ApiLogs` lists the rows of the current panel, with `Widgets\ApiRequestsOverview` as a header widget. The widget is registered with Livewire directly, so it doesn't appear on the panel's dashboard.

## Login endpoints

With `->login()`, `routes/api.php` registers two more route groups at the plugin's prefix, without the tenant segment. `POST login` uses `->loginMiddleware()` instead of the plugin's middleware, since guests call it. `GET user` and `POST logout` use the plugin's middleware and `ServeFilamentApi`. `AuthController::login()` looks the user up with the user provider of the panel's guard, rate limits failures per panel, email and IP, checks panel access and email verification, and issues a Sanctum token with the panel's `*:read` and `*:write` abilities. `ApiResource` removes sensitive attributes (passwords, remember tokens, two-factor secrets) from every model that implements `Authenticatable`.

## Model endpoints

`ModelController` doesn't use Filament components. It authorizes with the `Filament\authorize()` helper, which uses the model's policy if there is one, and builds the query with `spatie/laravel-query-builder`.

## Tests

The tests use Orchestra Testbench with a test panel in `tests/Fixtures`. It has a resource with create and edit pages, filters, tabs, a policy and a relation manager, a simple resource, a user with Sanctum tokens, and a panel without the plugin. Run them with:

```bash
composer test
composer analyse
```
