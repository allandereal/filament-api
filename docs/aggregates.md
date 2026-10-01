# Operator filters and aggregates

Two opt-in features let clients query list endpoints more precisely, so that stats and chart widgets don't have to download every record:

```php
FilamentApiPlugin::make()
    ->operatorFilters()   // ?where[total_price][gte]=100
    ->aggregates()        // ?aggregate=avg:total_price&group=month:created_at
```

Both work on resource endpoints, relation manager endpoints and [model endpoints](models.md). They apply inside the endpoint's own query, after everything [listing](listing.md) applies: `getEloquentQuery()`, tenancy, the tab, the table's filters and the search. Both are read requests, so they need read access, like listing.

When an option is off, requests that use it get `422`, so clients can fall back to listing records.

## Which columns can be used

Only the real columns of the model's table that the API returns: columns in the model's `$hidden` can't be used, and if the model has `$visible`, only those columns can. Since clients can already read these values, filtering or aggregating them reveals nothing new. Other columns get `422`.

## Operator filters

```http
GET /api/shop/orders?where[created_at][between]=2025-10-01 00:00:00,2026-09-30 23:59:59&where[status][in]=new,processing
```

`where[{column}][{operator}]={value}`. Several constraints are combined with AND, including several on the same column (`where[total_price][gte]=100&where[total_price][lt]=500`).

| Operator | SQL | Value |
|---|---|---|
| `eq`, `ne` | `=`, `!=` | One value |
| `gt`, `gte`, `lt`, `lte` | `>`, `>=`, `<`, `<=` | One value |
| `in`, `notin` | `IN`, `NOT IN` | A comma-separated list, or an array (`where[status][in][]=new`) |
| `between` | `BETWEEN` | Two values separated by a comma |
| `null`, `notnull` | `IS NULL`, `IS NOT NULL` | Ignored, e.g. `where[deleted_at][null]=1` |
| `like` | `LIKE` | A SQL pattern, e.g. `%term%` |

Dates are compared as stored, so send them in the database's format, usually `Y-m-d H:i:s` in the app's timezone.

`where` is separate from `filter`, which applies the table's own [filters](listing.md#filtering).

### Sorting by any column

With operator filters on, `sort` also accepts any column that `where` accepts, not only the table's sortable columns: `?sort=-created_at&per_page=5` lists the 5 latest records even if the table can't sort by `created_at`. Sorting by a column replaces the table's default sort. The table's sortable columns, including relationship columns such as `customer.name`, keep working as before. Resource endpoints sort by one column at a time.

## Aggregates

`aggregate={function}:{column}` computes one value over the whole query, ignoring pagination:

```http
GET /api/shop/orders?aggregate=avg:total_price
```

```json
{"data": [{"aggregate": 1049.99}]}
```

| Function | Column |
|---|---|
| `count` | Any column (counts non-null values), or `*` for all rows |
| `sum`, `avg` | Numeric columns |
| `min`, `max` | Any column |

The value is the database's: numbers are JSON numbers, and other values (such as the `max` of a date) are strings. Without matching rows, `count` returns `0` and the other functions return `null`.

### Grouping

Add `group={bucket}:{column}` to compute the aggregate per group:

```http
GET /api/shop/orders?aggregate=count:*&group=month:created_at&where[created_at][between]=2025-10-01 00:00:00,2026-09-30 23:59:59
```

```json
{"data": [{"group": "2025-10", "aggregate": 81}, {"group": "2025-11", "aggregate": 94}]}
```

| Bucket | Group label |
|---|---|
| `value` | The column's value |
| `minute` | `2025-10-01 14:30:00` |
| `hour` | `2025-10-01 14:00` |
| `day` | `2025-10-01` |
| `month` | `2025-10` |
| `year` | `2025` |

- Groups are sorted by label, ascending. Groups without rows are left out, so fill in the gaps on the client.
- The date labels use the same formats as [laravel-trend](https://github.com/Flowframe/laravel-trend), so they can be mapped straight onto its charts.
- Dates are grouped as stored, in the app's timezone.
- A request can return at most 1,000 groups. Narrow the query or use a larger bucket to stay under it, otherwise the request gets `422`.
- Date buckets are supported on SQLite, MySQL, MariaDB, PostgreSQL and SQL Server. The package's tests run on SQLite.

## Errors

Invalid requests get `422`, with the error under `where.{column}`, `aggregate` or `group`:

```json
{"message": "The column [password] can't be aggregated.", "errors": {"aggregate": ["The column [password] can't be aggregated."]}}
```
