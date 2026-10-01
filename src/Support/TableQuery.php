<?php

namespace Allandereal\FilamentApi\Support;

use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Builds the query of a Filament table (a resource's list page or a relation manager) from the API
 * request, so that the API filters, searches and sorts exactly like the table in the panel does.
 *
 * - `?filter[status]=new` / `?filter[created_at][created_from]=2024-01-01` apply the table's filters.
 * - `?search=foo` applies the table's global search over its searchable columns.
 * - `?sort=-total_price` sorts by a sortable column, `-` for descending.
 * - `?tab=processing` activates a tab of the list page.
 *
 * The record key can always be used to filter (`?filter[id]=1,2,3`) and sort (`?sort=-id`), even if the table
 * doesn't declare it, so that API clients can batch-load records and page through them in a stable order.
 */
class TableQuery
{
    public static function for(Component & HasTable $livewire, Request $request): Builder
    {
        if (method_exists($livewire, 'mount')) {
            $livewire->mount();
        }

        if (method_exists($livewire, 'bootedInteractsWithTable')) {
            $livewire->bootedInteractsWithTable();
        }

        $table = $livewire->getTable();

        $model = $table->getQuery()->getModel();
        $keyName = $model->getKeyName();

        $keyFilter = null;
        $keySortDirection = null;

        $errors = [];

        $filters = $request->query('filter', []);

        if (! is_array($filters)) {
            $errors['filter'] = 'The filter parameter must be an array, e.g. filter[status]=new.';
            $filters = [];
        }

        $availableFilters = $table->getFilters();

        // Start from the filters' default state, like the panel does. Filament treats a filter without
        // state as active, so skipping this would apply every filter.
        $livewire->getTableFiltersForm()->fill();

        // With deferred filters, the form fills `tableDeferredFilters`, which the panel only copies to the
        // applied `tableFilters` when the user clicks "Apply".
        if ($table->hasDeferredFilters()) {
            $livewire->tableFilters = $livewire->tableDeferredFilters;
        }

        foreach ($filters as $name => $value) {
            if (($name === $keyName) && (! array_key_exists($name, $availableFilters))) {
                $keyFilter = is_array($value) ? array_values($value) : explode(',', (string) $value);

                continue;
            }

            if (! array_key_exists($name, $availableFilters)) {
                $errors["filter.{$name}"] = static::unknown('filter', $name, [$keyName, ...array_keys($availableFilters)]);

                continue;
            }

            $livewire->tableFilters[$name] = [
                ...($livewire->tableFilters[$name] ?? []),
                ...static::normalizeFilterState($availableFilters[$name], $value),
            ];
        }

        if (filled($search = $request->query('search'))) {
            if (! is_string($search)) {
                $errors['search'] = 'The search parameter must be a string.';
            } elseif (! $table->isSearchable()) {
                $errors['search'] = 'This endpoint is not searchable.';
            } else {
                $livewire->tableSearch = $search;
            }
        }

        if (filled($sort = $request->query('sort'))) {
            $column = is_string($sort) ? ltrim($sort, '-') : '';

            if (($column === $keyName) && (! $table->getSortableVisibleColumn($column))) {
                $keySortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
            } elseif (! $table->getSortableVisibleColumn($column)) {
                $sortable = collect($table->getColumns())
                    ->filter(fn ($column): bool => $column->isSortable() && (! $column->isHidden()))
                    ->keys()
                    ->all();

                $errors['sort'] = static::unknown('sort', $column, [$keyName, ...$sortable]);
            } else {
                $livewire->tableSortColumn = $column;
                $livewire->tableSortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
            }
        }

        if (filled($tab = $request->query('tab'))) {
            $tabs = method_exists($livewire, 'getCachedTabs') ? $livewire->getCachedTabs() : [];

            if (! (is_string($tab) && array_key_exists($tab, $tabs))) {
                $errors['tab'] = static::unknown('tab', (string) (is_string($tab) ? $tab : ''), array_keys($tabs));
            } else {
                $livewire->activeTab = $tab;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $query = $livewire->getFilteredSortedTableQuery();

        if ($keyFilter !== null) {
            $query->whereIn($model->getQualifiedKeyName(), $keyFilter);
        }

        if ($keySortDirection) {
            $query->reorder($model->getQualifiedKeyName(), $keySortDirection);
        }

        return $query;
    }

    /**
     * Convert a query string value into the state of the filter's form, which is what Filament
     * stores for each filter. A nested array (`filter[name][field]=value`) is passed through as-is.
     *
     * @return array<string, mixed>
     */
    protected static function normalizeFilterState(BaseFilter $filter, mixed $value): array
    {
        if (is_array($value) && Arr::isAssoc($value)) {
            return $value;
        }

        if (($filter instanceof SelectFilter) && $filter->isMultiple()) {
            return ['values' => is_array($value) ? $value : explode(',', (string) $value)];
        }

        // Ternary filters are select filters too.
        if ($filter instanceof SelectFilter) {
            return ['value' => $value];
        }

        // A plain filter is a toggle / checkbox in the panel.
        return ['isActive' => filter_var($value, FILTER_VALIDATE_BOOLEAN)];
    }

    /**
     * @param  array<string>  $allowed
     */
    protected static function unknown(string $parameter, string $value, array $allowed): string
    {
        return "The {$parameter} [{$value}] is not allowed. Allowed: " . (implode(', ', $allowed) ?: 'none') . '.';
    }
}
