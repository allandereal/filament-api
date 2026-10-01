# Changelog

All notable changes to `filament-api` will be documented in this file.

## Unreleased

- The API is enabled per panel with `FilamentApiPlugin`, and the `filament-api` config file was removed.
- Endpoints mirror the resource slugs (e.g. `api/shop/orders`), and each panel gets its own routes.
- Requests require an authenticated user who can access the panel. They are authorized with the resource's policies.
- Listing uses the resource's table: its filters, search, sortable columns and tabs.
- Creating and updating go through the resource's form and its create / edit pages.
- Relation managers are exposed as nested endpoints.
- Models without a resource can be exposed as read-only endpoints with `->models()`.
- `per_page` is capped by `->maxPerPage()`. Deleting returns `204`, and unsupported methods return `405`.
- Removed the endpoints that were discovered from model relationships.
- Added an API tokens page where users create, scope and revoke their Sanctum tokens. Token abilities are checked on every request.
- Resource endpoints can always be filtered (`filter[id]=1,2`) and sorted (`sort=-id`) by the record key.
- Fixed listing tables with deferred filters, which crashed on query builder filters.
- Model endpoints answer `422` instead of `400` for filters and sorts that aren't allowed.
- Added request logging (`->logRequests()`) with an API logs page, a stats widget and daily pruning.
- Added login, user and logout endpoints (`->login()`) that issue per-user tokens.
- Login tokens can expire after a period of inactivity, like the client's session (`expires_in`).
- Added operator filters (`->operatorFilters()`, `where[column][operator]`) and aggregates (`->aggregates()`, `aggregate` and `group`).
- With operator filters, `sort` accepts any visible column.
- The API tokens and API logs pages are grouped under "API" in the navigation (`->navigationGroup()`).
- User responses never include passwords, remember tokens or two-factor secrets.
- Supports Laravel 10, 11, 12 and 13, tested on PHP 8.1 to 8.5.

## 1.0.0 - 202X-XX-XX

- initial release
