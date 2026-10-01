# Creating, updating and deleting records

Writes go through the resource's form and its create and edit pages, so the API saves records exactly like the panel does:

- the form's validation rules, including rules added by components (`->required()`, `->email()`, `->unique()`, `->maxLength()`...)
- the form's default values
- dehydration: `->dehydrateStateUsing()`, `->dehydrated(false)`, hashing a password field, and so on
- relationships saved by form components: selects with `->relationship()`, repeaters, `SpatieTagsInput`...
- the page's hooks: `mutateFormDataBeforeCreate()`, `handleRecordCreation()`, `beforeCreate()`, `afterSave()`...
- tenancy: new records are associated with the current tenant
- database transactions, if the panel uses them

## Creating records

`POST /api/{endpoint}` with the form's fields as JSON:

```http
POST /api/blog/posts
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json

{
    "title": "Hello",
    "slug": "hello",
    "content": "My first post",
    "blog_author_id": 1,
    "blog_category_id": 1,
    "tags": ["news", "laravel"]
}
```

The response is `201 Created` with the new record:

```json
{"data": {"id": 101, "title": "Hello", "slug": "hello"}}
```

Fields you leave out get the form's default value.

## Updating records

`PUT` or `PATCH /api/{endpoint}/{record}` with the fields to change:

```http
PATCH /api/blog/posts/101
Content-Type: application/json

{"title": "Hello again"}
```

The response is `200 OK` with the updated record.

`PUT` and `PATCH` behave the same and both do partial updates. The form is first filled with the record, as when you open the edit page, and the fields you send replace the current values. Fields you leave out keep their current value, but they're still validated, so an update can fail if an existing value no longer passes the form's rules.

## Deleting records

`DELETE /api/{endpoint}/{record}` deletes the record with `$record->delete()`, like the panel's delete action. Model events and observers run, and models that use `SoftDeletes` are soft deleted.

The response is `204 No Content`.

## Which fields are accepted

Only the fields of the form are accepted: the top-level keys of the form's state, which are the names of its fields (`TextInput::make('title')`) and of components with their own state path, such as a `Group::make()->statePath('address')`. Everything else in the request, such as `id` or `created_at`, is ignored.

Hidden fields follow the form's rules. A field that is hidden for the current operation (for example `->hiddenOn('edit')`) is not validated or saved, unless it's `->dehydratedWhenHidden()`.

## Relationships

Relationships that are part of the form are saved with the record, the same way as in the panel:

```json
{
    "number": "OR-1001",
    "shop_customer_id": 1,
    "status": "new",
    "currency": "usd",
    "items": [
        {"shop_product_id": 1, "qty": 2, "unit_price": 10}
    ],
    "address": {"country": "us", "street": "1 Main St", "city": "Springfield", "state": "IL", "zip": "62701"}
}
```

- **Select and CheckboxList with `->relationship()`**: send the related keys, such as `"categories": [1, 2]`.
- **Repeater with `->relationship()`**: send the list of items. On update, the list you send **replaces** all the existing items, and items that aren't in it are deleted. To keep the items, leave the field out.
- **Group, Fieldset or Section with `->relationship()`**: send an object with the related fields, like `address` above.

## Validation errors

A request that fails the form's validation is rejected with `422 Unprocessable Entity`. Errors use the field names, with dots for nested fields:

```json
{
    "message": "The title field is required. (and 1 more error)",
    "errors": {
        "title": ["The title field is required."],
        "slug": ["The slug field is required."]
    }
}
```

The messages use the fields' labels, as in the panel. Errors for repeater items include the item's index in the list you sent, such as `items.1.unit_price`. If you leave a repeater out on create, its default items are validated, and their errors use generated keys instead, such as `items.5b31f0a3-….unit_price`.

> [!IMPORTANT]
> The API only enforces the rules declared on the form. Some components accept values that the panel's UI wouldn't let users pick. For example, Filament's `Select` doesn't check that the value is one of its options. Add the rules you need:
>
> ```php
> Select::make('status')
>     ->options(OrderStatus::class)
>     ->in(fn (Select $component): array => array_keys($component->getOptions()))
> ```

## Resources without create or edit pages

Simple (modal) resources, such as resources generated with `--simple`, have a `ManageRecords` page instead of create and edit pages. For these, the API uses a default create and edit page with the resource's form. Hooks defined on the modal actions, such as `CreateAction::mutateFormDataUsing()`, don't run.

## Halting

If a page hook calls `$this->halt()`, the record isn't saved. On create, the API then responds with `422` and the message `The record could not be created.` On update, the API responds with the record unchanged.

## Limitations

- **File uploads**: `FileUpload` and `SpatieMediaLibraryFileUpload` fields can't be uploaded through the API yet.
- **Page side effects**: the pages still run their usual side effects. For example, they flash the "Saved" notification into the session, which an API request doesn't use.
