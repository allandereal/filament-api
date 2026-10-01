<?php

namespace Allandereal\FilamentApi\Http\Controllers;

use Allandereal\FilamentApi\Http\Resources\ApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

use function Filament\authorize;

/**
 * Read-only endpoints for models that are registered with `FilamentApiPlugin::models()`.
 */
class ModelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $definition = $this->getDefinition($request);

        /** @var class-string<Model> $model */
        $model = $definition['model'];

        authorize('viewAny', $model);

        $query = QueryBuilder::for($model, $request)
            ->allowedFilters(array_map(
                fn (string $filter): AllowedFilter => AllowedFilter::exact($filter),
                $definition['filters'],
            ))
            ->allowedSorts($definition['sorts']);

        if ($definition['default_sort']) {
            $query->defaultSort($definition['default_sort']);
        }

        return ApiResource::collection($this->paginate($query, $request));
    }

    public function show(Request $request): ApiResource
    {
        $model = $this->getDefinition($request)['model'];

        $record = app($model)->resolveRouteBinding($request->route('record'));

        abort_unless($record, 404, 'Record not found.');

        authorize('view', $record);

        return new ApiResource($record);
    }

    /**
     * @return array{model: class-string<Model>, filters: array<string>, sorts: array<string>, default_sort: string|null}
     */
    protected function getDefinition(Request $request): array
    {
        return $this->getPlugin()->getModels()[$request->route('filamentApiModel')];
    }
}
