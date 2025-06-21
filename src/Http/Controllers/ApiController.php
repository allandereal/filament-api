<?php

namespace Allandereal\FilamentApi\Http\Controllers;

use Allandereal\FilamentApi\Http\Requests\ApiRequest;
use Allandereal\FilamentApi\Http\Resources\ApiResource;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;

class ApiController
{
    protected null | string | Model $model;

    protected ApiRequest $request;

    public function __invoke(ApiRequest $request, $resource, $id = null): ApiResource | AnonymousResourceCollection | JsonResponse
    {
        $this->request = $request;

        $this->resolveModel($resource);

        return match ($request->method()) {
            'GET' => $id ? $this->show($id) : $this->index(),
            'POST' => $this->store(),
            'PUT', 'PATCH' => $this->update($id),
            'DELETE' => $this->delete($id),
            default => response()->json([], Response::HTTP_NOT_FOUND),
        };
    }

    protected function index(): AnonymousResourceCollection
    {
        return ApiResource::collection(
            QueryBuilder::for($this->model)
                ->allowedFilters(['status', 'deleted_at'])
                ->paginate(
                    perPage: $this->request->query('perPage') ?? 10,
                    page: $this->request->query('page') ?? 1
                )
        );
    }

    protected function store(): ApiResource
    {
        $record = new $this->model;

        $record->fill($this->request->validated())
            ->save();

        return new ApiResource($record);
    }

    protected function update($id): ApiResource
    {
        $record = $this->model::findOrFail($id);

        $record->fill($this->request->validated())
            ->save();

        return new ApiResource($record->fresh());
    }

    protected function show($id): ApiResource
    {
        return new ApiResource(
            $this->model::findOrFail($id)
        );
    }

    protected function delete($id): JsonResponse
    {
        $this->model::whereKey($id)->delete();

        return response()->json();
    }

    private function resolveModel(string $resource): void
    {
        $resourceModels = collect(Filament::getResources())
            ->mapWithKeys(fn ($item, $key) => [$item => $item::getModel()]);

        $relatedModels = $resourceModels->mapWithKeys(
            fn ($item) => [$item => $this->getRelatedModel($item, $resource)]
        )->filter();

        $this->model = collect(config('filament-api.models'))
            ->merge($resourceModels)
            ->merge($relatedModels)
            ->firstWhere(
                fn ($item) => Str::of($item)
                    ->afterLast('\\')
                    ->slug()
                    ->singular()
                    ->value() === Str::singular($resource)
            );

        if (! $this->model) {
            throw new HttpResponseException(response()->json([
                'message' => "Resource <{$resource}> not supported!",
            ], Response::HTTP_NOT_FOUND));
        }
    }

    private function getRelatedModel($model, $method): ?string
    {
        $parent = new $model;

        if (method_exists($parent, $method)) {
            $relation = $parent->$method();

            if ($relation instanceof Relation) {
                return get_class($relation->getRelated());
            }
        }

        return null;
    }
}
