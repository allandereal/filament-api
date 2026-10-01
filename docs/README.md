# Filament API documentation

Filament API turns the resources of a Filament panel into a REST API. Every request goes through the resource, exactly like the panel does: its policies, its query, its table, its form and its pages. You don't write controllers, requests, or validation rules twice.

## Guides

1. [Installation](installation.md): install the package, enable it on a panel, and authenticate requests.
2. [Endpoints](endpoints.md): the URLs and routes the package registers.
3. [Listing records](listing.md): filtering, searching, sorting, tabs, pagination and the response format.
4. [Creating, updating and deleting records](writing.md): how requests go through the resource's form and pages.
5. [Authorization](authorization.md): who can call the API, and what they can do.
6. [Models without a resource](models.md): read-only endpoints for other models.
7. [Configuration reference](configuration.md): every plugin option.
8. [Errors](errors.md): status codes and error responses.
9. [How it works](how-it-works.md): the internals, for contributors and for debugging.
10. [Upgrading from 1.0](upgrading.md): moving from the `filament-api` config file to the plugin.

## Requirements

- PHP 8.1 or higher
- Filament 3.2 or higher
- An authentication middleware for the API, such as [Laravel Sanctum](https://laravel.com/docs/sanctum)
