# Models without a resource

Some models don't have a Filament resource, such as pivot models, tags or media. You can expose them as read-only endpoints with `->models()`:

```php
use Allandereal\FilamentApi\FilamentApiPlugin;

FilamentApiPlugin::make()
    ->models([
        'tags' => \Spatie\Tags\Tag::class,
        'comments' => [
            'model' => \App\Models\Comment::class,
            'filters' => ['customer_id', 'commentable_id', 'commentable_type'],
            'sorts' => ['id', 'created_at'],
        ],
        'shop/order-items' => [
            'model' => \App\Models\Shop\OrderItem::class,
            'filters' => ['shop_order_id', 'shop_product_id'],
        ],
        'taggables' => [
            'model' => \App\Models\Taggable::class,
            'filters' => ['tag_id', 'taggable_id', 'taggable_type'],
            'default_sort' => 'tag_id',
        ],
    ])
```

The key is the endpoint, which can contain slashes. The value is the model class, or an array with these options:

| Option | Default | Description |
|---|---|---|
| `model` | required | The Eloquent model class. |
| `filters` | `[]` | Columns that can be filtered with an exact match: `?filter[customer_id]=1`. Separate several values with commas: `?filter[customer_id]=1,2`. |
| `sorts` | `[]` | Columns that can be sorted: `?sort=created_at`, or `?sort=-created_at` for descending order. |
| `default_sort` | the primary key | The column to sort by when `sort` is left out. Set it for tables without an `id` column, such as pivot tables, or set it to `null` for no default order. |

Each model gets two routes:

| Method | URL | Action |
|---|---|---|
| `GET` | `/api/comments` | List records, with `filter`, `sort`, `per_page` and `page` |
| `GET` | `/api/comments/{record}` | Show a record by its route key |

Other methods get `405 Method Not Allowed`.

Filters and sorts that aren't allowed are rejected with `422 Unprocessable Entity`, like on resource endpoints. Filtering and sorting use [spatie/laravel-query-builder](https://spatie.be/docs/laravel-query-builder).

The responses have the same format as resource endpoints: see [Response](listing.md#response).

## Authorization

Requests go through the same authentication and panel access checks as resource endpoints. The model's policy then authorizes them: `viewAny` to list and `view` to show. If the model has no policy, any user who can access the panel can read every record. See [Authorization](authorization.md#model-endpoints).

> [!WARNING]
> Model endpoints return every attribute of the model that isn't in `$hidden`. Check the model before you expose it. For example, a `User` model must hide `password` and `remember_token`.

## When to use a resource instead

Model endpoints are deliberately simple. Create a resource for the model if you need any of these:

- creating, updating or deleting records
- validation
- search, custom filters or tabs
- scoping the records, for example to a tenant

The resource doesn't have to appear in the navigation: use `protected static bool $shouldRegisterNavigation = false;`.
