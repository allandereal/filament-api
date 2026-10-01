# Errors

Errors are JSON responses with a `message`, and an `errors` object for validation errors. Send `Accept: application/json` with every request: see [Routing errors](#routing-errors).

| Status | When |
|---|---|
| `401 Unauthorized` | The request isn't authenticated. |
| `403 Forbidden` | The user can't access the panel, the resource's policy denies the action, or the API token doesn't have access to the endpoint. |
| `404 Not Found` | The endpoint doesn't exist, or the record doesn't exist or is outside the resource's query. On panels with tenancy, the user can't access the tenant. |
| `405 Method Not Allowed` | The endpoint doesn't support the method, for example `POST /api/posts/1`, or writes on a model endpoint. |
| `422 Unprocessable Entity` | Validation failed, or a filter, sort, tab or pagination parameter is invalid, or a page hook halted the creation. |
| `429 Too Many Requests` | The rate limiter rejected the request. The `Retry-After` header says how many seconds to wait. See [Rate limiting](configuration.md#rate-limiting). |

## Validation errors

```json
{
    "message": "The title field is required. (and 1 more error)",
    "errors": {
        "title": ["The title field is required."],
        "status": ["The selected status is invalid."]
    }
}
```

Field errors use the form's field names, and listing errors use the parameter name, such as `filter.status`, `sort`, `tab` or `per_page`. See [Validation errors](writing.md#validation-errors) and [Listing errors](listing.md#errors).

## Other errors

```json
{"message": "Unauthenticated."}
```

```json
{"message": "Record not found."}
```

## Routing errors

Laravel raises `404` for a URL that matches no route, and `405` for a method the route doesn't support, while it matches the route. This happens before the package's middleware runs, so a client that doesn't send `Accept: application/json` gets the HTML error page for these two errors, and they aren't [logged](logging.md).

## Debug mode

When `APP_DEBUG` is enabled, error responses also include the exception and a stack trace. Disable debug mode in production.
