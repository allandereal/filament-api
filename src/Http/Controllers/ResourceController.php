<?php

namespace Allandereal\FilamentApi\Http\Controllers;

use Allandereal\FilamentApi\Http\Resources\ApiResource;
use Allandereal\FilamentApi\Support\Pages\CreateRecord;
use Allandereal\FilamentApi\Support\Pages\EditRecord;
use Allandereal\FilamentApi\Support\TableQuery;
use Closure;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints for Filament resources. Every action goes through the resource the same way the panel does:
 * its policies, its Eloquent query (including tenancy), its table, and its create / edit pages.
 */
class ResourceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection | JsonResponse
    {
        $resource = $this->getResource($request);

        abort_unless($resource::canViewAny(), 403);

        $page = ($resource::getPages()['index'] ?? null)?->getPage();

        // A custom index page without a table is listed with the resource's query, without filters.
        $query = ($page && is_subclass_of($page, HasTable::class))
            ? TableQuery::for(app($page), $request)
            : $resource::getEloquentQuery();

        return $this->applyColumnQuery($query, $request)
            ?? ApiResource::collection($this->paginate($query, $request));
    }

    public function store(Request $request): JsonResponse
    {
        $resource = $this->getResource($request);

        abort_unless($resource::canCreate(), 403);

        $page = $this->getPage($resource, 'create', BaseCreateRecord::class) ?? CreateRecord::forResource($resource);
        $page->mount();

        $this->fillForm($page, $request);
        $this->callForm(fn () => $page->create());

        $record = $page->getRecord();

        // A `Halt` exception thrown from a page hook stops the creation without an error.
        abort_unless($record?->exists, 422, 'The record could not be created.');

        return (new ApiResource($record))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request): ApiResource
    {
        $resource = $this->getResource($request);

        $record = $this->getRecord($resource, $request);

        abort_unless($resource::canView($record), 403);

        return new ApiResource($record);
    }

    public function update(Request $request): ApiResource
    {
        $resource = $this->getResource($request);

        $record = $this->getRecord($resource, $request);

        abort_unless($resource::canEdit($record), 403);

        $page = $this->getPage($resource, 'edit', BaseEditRecord::class) ?? EditRecord::forResource($resource);
        $page->mount($request->route('record'));

        $this->fillForm($page, $request);
        $this->callForm(fn () => $page->save(shouldRedirect: false, shouldSendSavedNotification: false));

        return new ApiResource($page->getRecord()->refresh());
    }

    public function destroy(Request $request): Response
    {
        $resource = $this->getResource($request);

        $record = $this->getRecord($resource, $request);

        abort_unless($resource::canDelete($record), 403);

        $record->delete();

        return response()->noContent();
    }

    /**
     * List the records of one of the resource's relation managers, e.g. `GET /api/shop/orders/1/payments`.
     */
    public function relationIndex(Request $request): AnonymousResourceCollection | JsonResponse
    {
        $resource = $this->getResource($request);

        $ownerRecord = $this->getRecord($resource, $request);

        abort_unless($resource::canView($ownerRecord), 403);

        /** @var class-string<RelationManager> $relationManager */
        $relationManager = $request->route('filamentApiRelationManager');

        // Relation managers are rendered on the edit or view page of the record.
        $pages = $resource::getPages();
        $pageClass = ($pages['edit'] ?? $pages['view'] ?? $pages['index'])->getPage();

        abort_unless($relationManager::canViewForRecord($ownerRecord, $pageClass), 403);

        $livewire = app($relationManager);
        $livewire->ownerRecord = $ownerRecord;
        $livewire->pageClass = $pageClass;

        $query = TableQuery::for($livewire, $request);

        return $this->applyColumnQuery($query, $request)
            ?? ApiResource::collection($this->paginate($query, $request));
    }

    /**
     * @return class-string<\Filament\Resources\Resource>
     */
    protected function getResource(Request $request): string
    {
        return $request->route('filamentApiResource');
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function getRecord(string $resource, Request $request): Model
    {
        $record = $resource::resolveRecordRouteBinding($request->route('record'));

        abort_unless($record, 404, 'Record not found.');

        return $record;
    }

    /**
     * @template TPage
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @param  class-string<TPage>  $type
     * @return TPage|null
     */
    protected function getPage(string $resource, string $name, string $type): mixed
    {
        $page = ($resource::getPages()[$name] ?? null)?->getPage();

        if (! ($page && is_subclass_of($page, $type))) {
            return null;
        }

        return app($page);
    }

    /**
     * Merge the request body over the form's state. Only the fields of the form are accepted, everything else
     * is ignored. Fields that are left out keep their default value (create) or current value (update).
     */
    protected function fillForm(BaseCreateRecord | BaseEditRecord $page, Request $request): void
    {
        $page->data = [
            ...($page->data ?? []),
            ...Arr::only(
                $request->isJson() ? $request->json()->all() : $request->post(),
                array_keys($page->data ?? []),
            ),
        ];
    }

    /**
     * Run the page action and report validation errors with the field names instead of the form state paths.
     */
    protected function callForm(Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $key): array => [Str::after($key, 'data.') => $messages])
                    ->all(),
            );
        }
    }
}
