<?php

namespace Allandereal\FilamentApi\Models;

use Allandereal\FilamentApi\Facades\FilamentApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A request made to the API, recorded when the panel's plugin uses `->logRequests()`.
 *
 * @property int $id
 * @property string $panel
 * @property string $method
 * @property string $path
 * @property array<string, mixed>|null $query
 * @property string|null $route_name
 * @property string|null $endpoint
 * @property string|null $action
 * @property string|null $record_key
 * @property int $status_code
 * @property int $duration_ms
 * @property int|null $token_id
 * @property string|null $token_name
 * @property string|null $tenant_key
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $created_at
 */
class ApiRequest extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $table = 'filament_api_requests';

    protected $guarded = [];

    protected $casts = [
        'query' => 'array',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Requests older than the retention of their panel's plugin. Panels that keep logs forever are skipped.
     */
    public function prunable(): Builder
    {
        $query = static::query()->whereRaw('1 = 0');

        foreach (FilamentApi::getPanels() as $panel) {
            $days = FilamentApi::getPlugin($panel)->getLogRetention();

            if ($days === null) {
                continue;
            }

            $query->orWhere(fn (Builder $query) => $query
                ->where('panel', $panel->getId())
                ->where('created_at', '<', now()->subDays($days)));
        }

        return $query;
    }

    public function isSuccessful(): bool
    {
        return $this->status_code < 400;
    }
}
