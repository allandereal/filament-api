<?php

namespace Allandereal\FilamentApi\Support;

/**
 * API tokens are scoped with Sanctum abilities:
 *
 * - `*` gives full access.
 * - `{panel}:*:read` gives read access to every endpoint of the panel.
 * - `{panel}:{endpoint}:read` gives read access to one endpoint (list and show records, list relation managers).
 * - `{panel}:{endpoint}:write` gives write access to one endpoint (create, update and delete records).
 */
class TokenAbilities
{
    public const READ = 'read';

    public const WRITE = 'write';

    public static function make(string $panel, string $endpoint, string $action): string
    {
        return "{$panel}:{$endpoint}:{$action}";
    }

    public static function readEverything(string $panel): string
    {
        return static::make($panel, '*', static::READ);
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

        $parts = explode(':', $ability);

        if (count($parts) !== 3) {
            return $ability;
        }

        [$panel, $endpoint, $action] = $parts;

        $label = ($endpoint === '*') ? "Everything ({$action})" : "{$endpoint} ({$action})";

        return ($panel === $currentPanel) ? $label : "{$panel}: {$label}";
    }
}
