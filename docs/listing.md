# Listing records

`GET /api/{endpoint}` lists records using the resource's table, the same way as the table in the panel. A relation manager endpoint uses the relation manager's table.

```http
GET /api/shop/orders?filter[status]=new&search=OR85&sort=-total_price&tab=processing&per_page=25&page=2
```

The listing starts from the table's query, so it includes everything the panel applies:

- `Resource::getEloquentQuery()` and any global scopes
- tenant scoping, on panels with [tenancy](endpoints.md#tenancy)
- `modifyQueryUsing()` on the table
- the list page's default tab (see [Tabs](#tabs))
- the default state of the table's filters, such as a `TrashedFilter` that hides deleted records
- the table's default sort

## Filtering

`filter[name]=value` applies one of the table's filters. `name` is the filter's name, as in `SelectFilter::make('status')`.

| Filter | Query string |
|---|---|
| `SelectFilter` | `filter[status]=new` |
| `SelectFilter::multiple()` | `filter[status]=new,processing` or `filter[status][]=new&filter[status][]=processing` |
| `TernaryFilter` and `TrashedFilter` | `filter[trashed]=1` (true), `filter[trashed]=0` (false) |
| `Filter` (a toggle or checkbox) | `filter[featured]=1` |
| `Filter` with a custom `form()` | `filter[created_at][created_from]=2024-01-01&filter[created_at][created_until]=2024-12-31` |

A nested array (`filter[name][field]=value`) is passed to the filter as its form state, so it works with any filter. Use the names of the fields in the filter's form.

`TrashedFilter` values: `1` lists all records including deleted ones, `0` only deleted ones, and leaving it out hides deleted ones.

Filters you leave out keep their default state, like in the panel.

## Searching

`search=term` searches the table's searchable columns (`->searchable()`), like the search field above the table. Relationship columns such as `customer.name` are searched too.

If the table has no searchable column, `search` is rejected.

## Sorting

`sort=column` sorts by one of the table's sortable columns (`->sortable()`), in ascending order. Prefix it with `-` to sort in descending order:

```
?sort=total_price
?sort=-created_at
?sort=customer.name
```

Columns that are hidden or not sortable are rejected. When `sort` is left out, the table's default sort applies.

## Tabs

`tab=key` activates one of the list page's tabs (`ListRecords::getTabs()`), using the tab's array key:

```php
public function getTabs(): array
{
    return [
        'all' => Tab::make(),
        'processing' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing')),
    ];
}
```

```
?tab=processing
```

When `tab` is left out, the default tab applies (`getDefaultActiveTab()`, the first tab by default), like in the panel.

## Pagination

| Parameter | Default | Rules |
|---|---|---|
| `per_page` | 15 (`->perPage()`) | from 1 to 100 (`->maxPerPage()`) |
| `page` | 1 | 1 or more |

## Response

Lists are paginated Laravel API resources:

```json
{
    "data": [
        {
            "id": 65,
            "number": "OR852997",
            "status": "new",
            "total_price": 1998,
            "shop_customer_id": 571,
            "created_at": "2024-10-08T05:05:24.000000Z",
            "customer": {"id": 571, "name": "Cruz Sporer"}
        }
    ],
    "links": {
        "first": "https://example.com/api/shop/orders?page=1",
        "last": "https://example.com/api/shop/orders?page=67",
        "prev": null,
        "next": "https://example.com/api/shop/orders?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 67,
        "path": "https://example.com/api/shop/orders",
        "per_page": 15,
        "to": 15,
        "total": 1000
    }
}
```

The `links` keep the query string, so you can follow `next` to get the next page with the same filters.

Each record is the model's array form (`Model::toArray()`), so `$hidden`, `$visible`, `$appends` and casts apply. Relationships that the table eager loads, such as the relationship of a `customer.name` column, are included.

`GET /api/{endpoint}/{record}` returns a single record in a `data` key:

```json
{"data": {"id": 65, "number": "OR852997"}}
```

## Errors

Unknown filters, sorts and tabs, and invalid pagination, are rejected with a `422` response that lists the allowed values:

```json
{
    "message": "The filter [nope] is not allowed. Allowed: trashed, created_at. (and 1 more error)",
    "errors": {
        "filter.nope": ["The filter [nope] is not allowed. Allowed: trashed, created_at."],
        "sort": ["The sort [notes] is not allowed. Allowed: number, customer.name, currency, total_price, shipping_price."]
    }
}
```
