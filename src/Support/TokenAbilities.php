<?php

namespace Allandereal\FilamentApi\Support;

use Carbon\CarbonInterval;

/**
 * API tokens are scoped with Sanctum abilities:
 *
 * - `*` gives full access.
 * - `{panel}:*:read` gives read access to every endpoint of the panel, and `{panel}:*:write` write access.
 * - `{panel}:{endpoint}:read` gives read access to one endpoint (list and show records, list relation managers).
 * - `{panel}:{endpoint}:write` gives write access to one endpoint (create, update and delete records).
 */
class TokenAbilities
{
    public const READ = 'read';

    public const WRITE = 'write';

    /**
     * Login tokens that expire after a period of inactivity store its length in seconds in an ability, so that no
     * column has to be added to Sanctum's table. It never grants access: abilities that do have three parts.
     */
    public const IDLE_PREFIX = 'filament-api-idle:';

    public static function make(string $panel, string $endpoint, string $action): string
    {
        return "{$panel}:{$endpoint}:{$action}";
    }

    public static function readEverything(string $panel): string
    {
        return static::make($panel, '*', static::READ);
    }

    public static function writeEverything(string $panel): string
    {
        return static::make($panel, '*', static::WRITE);
    }

    public static function idle(int $seconds): string
    {
        return static::IDLE_PREFIX . $seconds;
    }

    /**
     * The inactivity period of a login token, in seconds, or `null` if the token has a fixed expiry.
     *
     * @param  array<string>  $abilities
     */
    public static function getIdleSeconds(array $abilities): ?int
    {
        foreach ($abilities as $ability) {
            if (str_starts_with($ability, static::IDLE_PREFIX)) {
                return (int) substr($ability, strlen(static::IDLE_PREFIX)) ?: null;
            }
        }

        return null;
    }

    /**
     * The abilities that allow the action. A token needs one of them.
     *
     * @return array<string>
     */
    public static function allowing(string $panel, string $endpoint, string $action): array
    {
        return [
            static::make($panel, $endpoint, $action),
            static::make($panel, '*', $action),
        ];
    }

    /**
     * A human-readable label for an ability, for the tokens table.
     */
    public static function getLabel(string $ability, ?string $currentPanel = null): string
    {
        if ($ability === '*') {
            return 'Full access';
        }

        if (str_starts_with($ability, static::IDLE_PREFIX)) {
            $seconds = (int) substr($ability, strlen(static::IDLE_PREFIX));

            return 'Expires after ' . CarbonInterval::seconds($seconds)->cascade()->forHumans() . ' idle';
        }

        $parts = explode(':', $ability);

        if (count($parts) !== 3) {
            return $ability;
        }

        [$panel, $endpoint, $action] = $parts;

        $label = ($endpoint === '*') ? "Everything ({$action})" : "{$endpoint} ({$action})";

        return ($panel === $currentPanel) ? $label : "{$panel}: {$label}";
    }
}
