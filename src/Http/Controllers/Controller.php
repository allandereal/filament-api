<?php

namespace Allandereal\FilamentApi\Http\Controllers;

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\FilamentApiPlugin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

abstract class Controller
{
    protected function getPlugin(): FilamentApiPlugin
    {
        return FilamentApi::getPlugin(Filament::getCurrentPanel());
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
