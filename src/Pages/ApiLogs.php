<?php

namespace Allandereal\FilamentApi\Pages;

use Allandereal\FilamentApi\FilamentApiPlugin;
use Allandereal\FilamentApi\Models\ApiRequest;
use Allandereal\FilamentApi\Widgets\ApiRequestsOverview;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Lists the requests made to the API of the panel, when the plugin uses `->logRequests()`.
 */
class ApiLogs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'API logs';

    protected static ?string $slug = 'api-logs';

    protected static string $view = 'filament-api::pages.api-logs';

    /**
     * Logs include the users, IP addresses and query strings of every request. Define a `viewApiLogs` gate to
     * limit who can see them, otherwise every user of the panel can, like resources without a policy.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (! Gate::has('viewApiLogs')) || Gate::forUser($user)->allows('viewApiLogs');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentApiPlugin::get()->getLogsNavigationGroup();
    }

    public function getSubheading(): ?string
    {
        $retention = FilamentApiPlugin::get()->getLogRetention();

        return $retention
            ? "Requests made to the API of this panel. Logs are kept for {$retention} days."
            : 'Requests made to the API of this panel.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ApiRequestsOverview::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ApiRequest::query()
                ->with('user')
                ->where('panel', Filament::getCurrentPanel()->getId()))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, H:i:s')
                    ->description(fn (ApiRequest $record): string => $record->created_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'POST' => 'success',
                        'PUT', 'PATCH' => 'warning',
                        'DELETE' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('path')
                    ->description(fn (ApiRequest $record): ?string => static::formatQuery($record->query))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status_code')
                    ->label('Status')
                    ->badge()
                    ->color(fn (int $state): string => static::getStatusColor($state))
                    ->sortable(),
                TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->suffix(' ms')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('user')
                    ->state(fn (ApiRequest $record): ?string => $record->user ? Filament::getUserName($record->user) : null)
                    ->placeholder('Guest'),
                TextColumn::make('token_name')
                    ->label('Token')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ip')
                    ->label('IP address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'successful' => 'Successful (2xx, 3xx)',
                        'client_error' => 'Client errors (4xx)',
                        'server_error' => 'Server errors (5xx)',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'successful' => $query->where('status_code', '<', 400),
                        'client_error' => $query->whereBetween('status_code', [400, 499]),
                        'server_error' => $query->where('status_code', '>=', 500),
                        default => $query,
                    }),
                SelectFilter::make('method')
                    ->options(array_combine($methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $methods)),
                SelectFilter::make('endpoint')
                    ->options(fn (): array => ApiRequest::query()
                        ->where('panel', Filament::getCurrentPanel()->getId())
                        ->whereNotNull('endpoint')
                        ->distinct()
                        ->orderBy('endpoint')
                        ->pluck('endpoint', 'endpoint')
                        ->all())
                    ->searchable(),
                SelectFilter::make('token_name')
                    ->label('Token')
                    ->options(fn (): array => ApiRequest::query()
                        ->where('panel', Filament::getCurrentPanel()->getId())
                        ->whereNotNull('token_name')
                        ->distinct()
                        ->orderBy('token_name')
                        ->pluck('token_name', 'token_name')
                        ->all())
                    ->searchable(),
                Filter::make('created_at')
                    ->form([
                        DateTimePicker::make('from'),
                        DateTimePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<=', $date))),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading(fn (ApiRequest $record): string => "{$record->method} {$record->path}")
                    ->infolist([
                        Section::make('Request')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('created_at')->label('Time')->dateTime('M j, Y H:i:s'),
                                    TextEntry::make('status_code')->label('Status')->badge()->color(fn (int $state): string => static::getStatusColor($state)),
                                    TextEntry::make('duration_ms')->label('Duration')->suffix(' ms'),
                                ]),
                                TextEntry::make('path')->copyable(),
                                TextEntry::make('query')
                                    ->label('Query string')
                                    ->state(fn (ApiRequest $record): ?string => static::formatQuery($record->query))
                                    ->placeholder('None'),
                                TextEntry::make('error')
                                    ->color('danger')
                                    ->visible(fn (ApiRequest $record): bool => filled($record->error)),
                                Grid::make(3)->schema([
                                    TextEntry::make('endpoint')->placeholder('—'),
                                    TextEntry::make('action')->placeholder('—'),
                                    TextEntry::make('record_key')->label('Record')->placeholder('—'),
                                ]),
                            ]),
                        Section::make('Client')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('user')
                                        ->state(fn (ApiRequest $record): ?string => $record->user ? Filament::getUserName($record->user) : null)
                                        ->placeholder('Guest'),
                                    TextEntry::make('token_name')->label('Token')->placeholder('—'),
                                    TextEntry::make('tenant_key')->label('Tenant')->placeholder('—'),
                                ]),
                                TextEntry::make('ip')->label('IP address')->placeholder('—'),
                                TextEntry::make('user_agent')->placeholder('—'),
                            ]),
                    ]),
            ])
            ->recordAction(ViewAction::class)
            ->poll('10s')
            ->emptyStateIcon('heroicon-o-queue-list')
            ->emptyStateHeading('No API requests yet')
            ->emptyStateDescription('Requests to the API of this panel will show up here.');
    }

    /**
     * @param  array<string, mixed>|null  $query
     */
    protected static function formatQuery(?array $query): ?string
    {
        return filled($query) ? urldecode(http_build_query($query)) : null;
    }

    protected static function getStatusColor(int $status): string
    {
        return match (true) {
            $status >= 500 => 'danger',
            $status >= 400 => 'warning',
            default => 'success',
        };
    }
}
