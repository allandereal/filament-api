<?php

namespace Allandereal\FilamentApi\Http\Controllers;

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\FilamentApiPlugin;
use Allandereal\FilamentApi\Support\ColumnQuery;
use Filament\Facades\Filament;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

abstract class Controller
{
    protected function getPlugin(): FilamentApiPlugin
    {
        return FilamentApi::getPlugin(Filament::getCurrentPanel());
    }

    /**
     * Apply the `where` filters of the request, and compute its `aggregate` if it has one. Returns the aggregate
     * response, or `null` to list the records.
     */
    protected function applyColumnQuery(Builder | QueryBuilder $query, Request $request): ?JsonResponse
    {
        $plugin = $this->getPlugin();

        $query = ($query instanceof QueryBuilder) ? $query->getEloquentBuilder() : $query;

        if ($request->query->has('where')) {
            if (! $plugin->hasOperatorFilters()) {
                throw ValidationException::withMessages(['where' => 'Operator filters are not enabled on this API.']);
            }

            ColumnQuery::applyWhere($query, $request);
        }

        if (! $request->query->has('aggregate')) {
            if ($request->query->has('group')) {
                throw ValidationException::withMessages(['group' => 'The group parameter needs an aggregate.']);
            }

            return null;
        }

        if (! $plugin->hasAggregates()) {
            throw ValidationException::withMessages(['aggregate' => 'Aggregates are not enabled on this API.']);
        }

        return response()->json(['data' => ColumnQuery::aggregate($query, $request)]);
    }

    protected function paginate(Builder | QueryBuilder $query, Request $request): LengthAwarePaginator
    {
        $plugin = $this->getPlugin();

        $pagination = Validator::make($request->query(), [
            'per_page' => ['nullable', 'integer', 'min:1', "max:{$plugin->getMaxPerPage()}"],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        return $query
            ->paginate(
                perPage: (int) ($pagination['per_page'] ?? $plugin->getPerPage()),
                page: (int) ($pagination['page'] ?? 1),
            )
            ->withQueryString();
    }
}
