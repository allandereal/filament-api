<?php

namespace Allandereal\FilamentApi;

use Allandereal\FilamentApi\Pages\ApiLogs;
use Allandereal\FilamentApi\Pages\ApiTokens;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class FilamentApiPlugin implements Plugin
{
    protected ?string $prefix = null;

    /**
     * @var array<string>
     */
    protected array $middleware = ['api', 'auth:sanctum', 'throttle:60,1'];

    /**
     * @var array<class-string>|null
     */
    protected ?array $resources = null;

    /**
     * @var array<class-string>
     */
    protected array $excludedResources = [];

    /**
     * @var array<string, array{model: class-string<Model>, filters: array<string>, sorts: array<string>, default_sort: string|null}>
     */
    protected array $models = [];

    protected int $perPage = 15;

    protected int $maxPerPage = 100;

    protected bool $hasTokens = true;

    protected ?string $navigationGroup = 'API';

    protected ?string $tokensNavigationGroup = null;

    protected bool $hasTokensNavigationGroup = false;

    protected bool $isLoggingRequests = false;

    protected ?int $logRetention = 30;

    protected ?string $logsNavigationGroup = null;

    protected bool $hasLogsNavigationGroup = false;

    protected bool $hasOperatorFilters = false;

    protected bool $hasAggregates = false;

    protected bool $hasLogin = false;

    protected ?int $loginTokenLifetime = 30;

    /**
     * @var array<string>
     */
    protected array $loginMiddleware = ['api'];

    public function getId(): string
    {
        return 'filament-api';
    }

    public function register(Panel $panel): void
    {
        if ($this->hasTokens()) {
            $panel->pages([ApiTokens::class]);
        }

        if ($this->isLoggingRequests()) {
            $panel->pages([ApiLogs::class]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * The URL prefix of the API. Defaults to `api` for the default panel and `api/{panel-id}` for other panels.
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = trim($prefix, '/');

        return $this;
    }

    public function getPrefix(Panel $panel): string
    {
        return $this->prefix ?? ($panel->isDefault() ? 'api' : "api/{$panel->getId()}");
    }

    /**
     * The middleware the API routes run through. It must authenticate the user, the API refuses guests.
     *
     * @param  array<string>  $middleware
     */
    public function middleware(array $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Only expose these resources. By default, every resource of the panel is exposed.
     *
     * @param  array<class-string>  $resources
     */
    public function resources(array $resources): static
    {
        $this->resources = $resources;

        return $this;
    }

    /**
     * @param  array<class-string>  $resources
     */
    public function excludeResources(array $resources): static
    {
        $this->excludedResources = $resources;

        return $this;
    }

    /**
     * @return array<class-string>
     */
    public function getResources(Panel $panel): array
    {
        return array_values(array_filter(
            $panel->getResources(),
            fn (string $resource): bool => (($this->resources === null) || in_array($resource, $this->resources))
                && (! in_array($resource, $this->excludedResources)),
        ));
    }

    /**
     * Expose models that have no Filament resource as read-only endpoints. Access is authorized with the
     * model's policy (`viewAny` / `view`) when one exists.
     *
     * ```php
     * ->models([
     *     'tags' => Tag::class,
     *     'payments' => ['model' => Payment::class, 'filters' => ['order_id'], 'sorts' => ['created_at']],
     * ])
     * ```
     *
     * `filters` are exact-match columns (`?filter[order_id]=1`), `sorts` are sortable columns (`?sort=-created_at`)
     * and `default_sort` defaults to the primary key (set it for tables without an `id`, e.g. pivot tables).
     *
     * @param  array<string, class-string<Model>|array<string, mixed>>  $models
     */
    public function models(array $models): static
    {
        foreach ($models as $slug => $definition) {
            $definition = is_array($definition) ? $definition : ['model' => $definition];

            if (! is_subclass_of($definition['model'] ?? null, Model::class)) {
                throw new InvalidArgumentException("The API endpoint [{$slug}] must be mapped to an Eloquent model.");
            }

            $this->models[trim($slug, '/')] = [
                'model' => $definition['model'],
                'filters' => $definition['filters'] ?? [],
                'sorts' => $definition['sorts'] ?? [],
                'default_sort' => array_key_exists('default_sort', $definition)
                    ? $definition['default_sort']
                    : app($definition['model'])->getKeyName(),
            ];
        }

        return $this;
    }

    /**
     * @return array<string, array{model: class-string<Model>, filters: array<string>, sorts: array<string>, default_sort: string|null}>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    public function perPage(int $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function maxPerPage(int $maxPerPage): static
    {
        $this->maxPerPage = $maxPerPage;

        return $this;
    }

    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    /**
     * Add the API tokens page to the panel, where users create and revoke their Sanctum tokens.
     * The page is only shown to users whose model uses Sanctum's `HasApiTokens` trait.
     */
    public function tokens(bool $condition = true): static
    {
        $this->hasTokens = $condition;

        return $this;
    }

    public function hasTokens(): bool
    {
        return $this->hasTokens;
    }

    /**
     * The navigation group of the plugin's pages (API tokens and API logs). `null` puts them outside of a group.
     */
    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    /**
     * Put the API tokens page in another navigation group than the plugin's other pages.
     */
    public function tokensNavigationGroup(?string $group): static
    {
        $this->tokensNavigationGroup = $group;
        $this->hasTokensNavigationGroup = true;

        return $this;
    }

    public function getTokensNavigationGroup(): ?string
    {
        return $this->hasTokensNavigationGroup ? $this->tokensNavigationGroup : $this->getNavigationGroup();
    }

    /**
     * Record every API request of the panel, and add the API logs page to the panel.
     */
    public function logRequests(bool $condition = true): static
    {
        $this->isLoggingRequests = $condition;

        return $this;
    }

    public function isLoggingRequests(): bool
    {
        return $this->isLoggingRequests;
    }

    /**
     * How many days to keep the logs. Older logs are pruned daily. `null` keeps them forever.
     */
    public function logRetention(?int $days): static
    {
        $this->logRetention = $days;

        return $this;
    }

    public function getLogRetention(): ?int
    {
        return $this->logRetention;
    }

    /**
     * Put the API logs page in another navigation group than the plugin's other pages.
     */
    public function logsNavigationGroup(?string $group): static
    {
        $this->logsNavigationGroup = $group;
        $this->hasLogsNavigationGroup = true;

        return $this;
    }

    public function getLogsNavigationGroup(): ?string
    {
        return $this->hasLogsNavigationGroup ? $this->logsNavigationGroup : $this->getNavigationGroup();
    }

    /**
     * Add `POST login`, `GET user` and `POST logout` endpoints, so that each user of an API client can sign in with
     * their own email and password and get their own token, instead of the client sharing one token.
     */
    public function login(bool $condition = true): static
    {
        $this->hasLogin = $condition;

        return $this;
    }

    public function hasLogin(): bool
    {
        return $this->hasLogin;
    }

    /**
     * How many days the tokens issued by the login endpoint are valid. `null` issues tokens that don't expire.
     */
    public function loginTokenLifetime(?int $days): static
    {
        $this->loginTokenLifetime = $days;

        return $this;
    }

    public function getLoginTokenLifetime(): ?int
    {
        return $this->loginTokenLifetime;
    }

    /**
     * The middleware of the login endpoint, which guests call, so it must not require authentication.
     *
     * @param  array<string>  $middleware
     */
    public function loginMiddleware(array $middleware): static
    {
        $this->loginMiddleware = $middleware;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getLoginMiddleware(): array
    {
        return $this->loginMiddleware;
    }

    /**
     * Let list endpoints filter by any visible column with operators: `?where[total_price][gte]=100`.
     */
    public function operatorFilters(bool $condition = true): static
    {
        $this->hasOperatorFilters = $condition;

        return $this;
    }

    public function hasOperatorFilters(): bool
    {
        return $this->hasOperatorFilters;
    }

    /**
     * Let list endpoints compute aggregates of any visible column, optionally grouped by a date bucket:
     * `?aggregate=avg:total_price` or `?aggregate=count:*&group=month:created_at`.
     */
    public function aggregates(bool $condition = true): static
    {
        $this->hasAggregates = $condition;

        return $this;
    }

    public function hasAggregates(): bool
    {
        return $this->hasAggregates;
    }
}
