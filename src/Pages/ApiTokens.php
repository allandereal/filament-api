<?php

namespace Allandereal\FilamentApi\Pages;

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\FilamentApiPlugin;
use Allandereal\FilamentApi\Support\TokenAbilities;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Livewire\Attributes\Locked;

/**
 * Lets users create, scope and revoke their own Sanctum tokens for the API.
 */
class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $title = 'API tokens';

    protected static ?string $slug = 'api-tokens';

    protected static string $view = 'filament-api::pages.api-tokens';

    /**
     * The token that was just created. It's only shown once, and Sanctum only stores its hash.
     */
    #[Locked]
    public ?string $plainTextToken = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user && method_exists($user, 'tokens') && method_exists($user, 'createToken');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentApiPlugin::get()->getTokensNavigationGroup();
    }

    public function getSubheading(): ?string
    {
        return 'Tokens let other applications call the API on your behalf, with your permissions.';
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getUser()->tokens()->getQuery())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abilities')
                    ->label('Access')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TokenAbilities::getLabel($state, Filament::getCurrentPanel()->getId())),
                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never')
                    ->color(fn (?Carbon $state): ?string => $state?->isPast() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                DeleteAction::make()
                    ->label('Revoke')
                    ->modalHeading('Revoke API token')
                    ->modalDescription('Applications that use this token will no longer be able to call the API.')
                    ->modalSubmitActionLabel('Revoke')
                    ->successNotificationTitle('Token revoked'),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->label('Revoke selected')
                    ->modalHeading('Revoke API tokens')
                    ->successNotificationTitle('Tokens revoked'),
            ])
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateHeading('No API tokens')
            ->emptyStateDescription('Create a token to call the API from another application.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create token')
                ->icon('heroicon-m-plus')
                ->modalHeading('Create API token')
                ->modalSubmitActionLabel('Create')
                ->form([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('A name to recognize the token, such as the application that uses it.'),
                    Select::make('expires_in')
                        ->label('Expiration')
                        ->options([
                            '7' => '7 days',
                            '30' => '30 days',
                            '60' => '60 days',
                            '90' => '90 days',
                            '365' => '1 year',
                            'never' => 'No expiration',
                        ])
                        ->default('30')
                        ->selectablePlaceholder(false)
                        ->in(['7', '30', '60', '90', '365', 'never'])
                        ->required(),
                    Radio::make('access')
                        ->options([
                            'read' => 'Read-only',
                            'custom' => 'Custom',
                            'full' => 'Full access',
                        ])
                        ->descriptions([
                            'read' => 'List and show the records of every endpoint of this panel.',
                            'custom' => 'Choose which endpoints the token can read and write.',
                            'full' => 'Everything you can do, in every panel, and in other routes that check Sanctum abilities.',
                        ])
                        ->default('read')
                        ->in(['read', 'custom', 'full'])
                        ->required()
                        ->live(),
                    CheckboxList::make('read')
                        ->helperText('List and show records.')
                        ->options($this->getEndpointOptions(writable: false))
                        ->descriptions($this->getEndpointDescriptions(writable: false))
                        ->bulkToggleable()
                        ->searchable()
                        ->columns(2)
                        ->requiredWithout('write')
                        ->validationMessages(['required_without' => 'Choose at least one endpoint.'])
                        ->visible(fn (Get $get): bool => $get('access') === 'custom'),
                    CheckboxList::make('write')
                        ->helperText('Create, update and delete records.')
                        ->options($this->getEndpointOptions(writable: true))
                        ->descriptions($this->getEndpointDescriptions(writable: true))
                        ->bulkToggleable()
                        ->searchable()
                        ->columns(2)
                        ->visible(fn (Get $get): bool => $get('access') === 'custom'),
                ])
                ->action(function (array $data): void {
                    // The `HasApiTokens` contract doesn't declare the expiration, but the trait accepts it since Sanctum 3.3.
                    /** @phpstan-ignore-next-line */
                    $this->plainTextToken = $this->getUser()->createToken(
                        $data['name'],
                        $this->getAbilities($data),
                        ($data['expires_in'] === 'never') ? null : now()->addDays((int) $data['expires_in']),
                    )->plainTextToken;

                    Notification::make()
                        ->success()
                        ->title('API token created')
                        ->send();
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string>
     */
    protected function getAbilities(array $data): array
    {
        $panel = Filament::getCurrentPanel()->getId();

        return match ($data['access']) {
            'full' => ['*'],
            'read' => [TokenAbilities::readEverything($panel)],
            default => [
                ...array_map(
                    fn (string $endpoint): string => TokenAbilities::make($panel, $endpoint, TokenAbilities::READ),
                    array_values(array_intersect($data['read'] ?? [], array_keys($this->getEndpointOptions(writable: false)))),
                ),
                ...array_map(
                    fn (string $endpoint): string => TokenAbilities::make($panel, $endpoint, TokenAbilities::WRITE),
                    array_values(array_intersect($data['write'] ?? [], array_keys($this->getEndpointOptions(writable: true)))),
                ),
            ],
        };
    }

    /**
     * Resource endpoints can be read and written. Model endpoints are read-only.
     *
     * @return array<string, string>
     */
    protected function getEndpointOptions(bool $writable): array
    {
        $panel = Filament::getCurrentPanel();

        $options = [];

        foreach (FilamentApi::getResourceEndpoints($panel) as $endpoint => $resource) {
            $options[$endpoint] = Str::ucfirst($resource::getPluralModelLabel());
        }

        if (! $writable) {
            foreach (array_keys(FilamentApi::getPlugin($panel)->getModels()) as $endpoint) {
                $options[$endpoint] = Str::headline(Str::afterLast($endpoint, '/'));
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function getEndpointDescriptions(bool $writable): array
    {
        $prefix = FilamentApi::getPlugin(Filament::getCurrentPanel())->getPrefix(Filament::getCurrentPanel());

        return collect($this->getEndpointOptions($writable))
            ->mapWithKeys(fn (string $label, string $endpoint): array => [$endpoint => "/{$prefix}/{$endpoint}"])
            ->all();
    }

    /**
     * Sanctum is only required by apps that use the tokens page, see `canAccess()`.
     *
     * @return Model&HasApiTokens
     */
    protected function getUser(): Model
    {
        /** @var Model&HasApiTokens $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
