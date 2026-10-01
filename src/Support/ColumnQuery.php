<?php

namespace Allandereal\FilamentApi\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Column filters (`?where[total_price][gte]=100`) and aggregates (`?aggregate=avg:total_price&group=month:created_at`),
 * applied to the query of a list endpoint after its own scoping (resource query, tenancy, tabs, filters, search).
 *
 * Only the real columns of the model's table that the model returns (not in `$hidden`, and in `$visible` if set)
 * can be used, so that clients can't filter or aggregate their way to values the API doesn't show.
 */
class ColumnQuery
{
    public const OPERATORS = ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'notin', 'between', 'null', 'notnull', 'like'];

    public const FUNCTIONS = ['count', 'sum', 'avg', 'min', 'max'];

    public const BUCKETS = ['value', 'minute', 'hour', 'day', 'month', 'year'];

    /**
     * The most groups an aggregate can return. Clients must narrow the query to get more.
     */
    public const MAX_GROUPS = 1000;

    /**
     * @var array<string, array<string, bool>>
     */
    protected static array $columns = [];

    public static function applyWhere(Builder $query, Request $request): void
    {
        $where = $request->query('where');

        if (blank($where)) {
            return;
        }

        if (! is_array($where)) {
            throw ValidationException::withMessages(['where' => 'The where parameter must be an array, e.g. where[status][eq]=new.']);
        }

        $model = $query->getModel();
        $errors = [];

        foreach ($where as $column => $constraints) {
            $column = (string) $column;

            if (! static::isQueryable($model, $column)) {
                $errors["where.{$column}"] = "The column [{$column}] can't be filtered.";

                continue;
            }

            if (! is_array($constraints)) {
                $errors["where.{$column}"] = "Use an operator, e.g. where[{$column}][eq]=value.";

                continue;
            }

            foreach ($constraints as $operator => $value) {
                if ($error = static::applyConstraint($query, $model->qualifyColumn($column), (string) $operator, $value)) {
                    $errors["where.{$column}"] = $error;
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<int, array{aggregate: mixed}|array{group: mixed, aggregate: mixed}>
     */
    public static function aggregate(Builder $query, Request $request): array
    {
        $model = $query->getModel();

        [$function, $column] = array_pad(explode(':', (string) $request->query('aggregate'), 2), 2, null);

        if (! in_array($function, static::FUNCTIONS, true)) {
            throw ValidationException::withMessages(['aggregate' => 'The aggregate must be one of ' . implode(', ', static::FUNCTIONS) . ', e.g. aggregate=sum:total_price.']);
        }

        if (! (($function === 'count') && ($column === '*')) && (blank($column) || (! static::isQueryable($model, $column)))) {
            throw ValidationException::withMessages(['aggregate' => "The column [{$column}] can't be aggregated."]);
        }

        if (in_array($function, ['sum', 'avg'], true) && (! static::isNumeric($model, $column))) {
            throw ValidationException::withMessages(['aggregate' => "The column [{$column}] isn't numeric."]);
        }

        // The table query's columns (e.g. relationship counts) and order don't apply to an aggregate.
        $base = $query->toBase()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset', 'unionOrders', 'unionLimit', 'unionOffset'])
            ->cloneWithoutBindings(['select', 'order']);

        $grammar = $base->getGrammar();
        $aggregate = ($column === '*') ? 'count(*)' : "{$function}(" . $grammar->wrap($model->qualifyColumn($column)) . ')';

        if (blank($request->query('group'))) {
            $value = (clone $base)->selectRaw("{$aggregate} as aggregate")->value('aggregate');

            return [['aggregate' => static::castAggregate($value, $function, $model, $column)]];
        }

        $bucket = static::getBucket($base, $model, (string) $request->query('group'));
        $groupAlias = $grammar->wrap('group');

        $rows = (clone $base)
            ->selectRaw("{$bucket} as {$groupAlias}, {$aggregate} as aggregate")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->limit(static::MAX_GROUPS + 1)
            ->get();

        if ($rows->count() > static::MAX_GROUPS) {
            throw ValidationException::withMessages(['group' => 'The aggregate has more than ' . static::MAX_GROUPS . ' groups. Narrow the query, or use a larger bucket.']);
        }

        return $rows
            ->map(fn (object $row): array => [
                'group' => $row->group,
                'aggregate' => static::castAggregate($row->aggregate, $function, $model, $column),
            ])
            ->all();
    }

    protected static function applyConstraint(Builder $query, string $column, string $operator, mixed $value): ?string
    {
        $list = fn (): array => is_array($value) ? array_values($value) : explode(',', (string) $value);

        if (is_array($value) && (! in_array($operator, ['in', 'notin'], true))) {
            return "The [{$operator}] operator takes a single value.";
        }

        switch ($operator) {
            case 'eq':
                $query->where($column, '=', $value);

                break;
            case 'ne':
                $query->where($column, '!=', $value);

                break;
            case 'gt':
                $query->where($column, '>', $value);

                break;
            case 'gte':
                $query->where($column, '>=', $value);

                break;
            case 'lt':
                $query->where($column, '<', $value);

                break;
            case 'lte':
                $query->where($column, '<=', $value);

                break;
            case 'like':
                $query->where($column, 'like', $value);

                break;
            case 'in':
                $query->whereIn($column, $list());

                break;
            case 'notin':
                $query->whereNotIn($column, $list());

                break;
            case 'between':
                $range = $list();

                if (count($range) !== 2) {
                    return 'The [between] operator takes two values separated by a comma.';
                }

                $query->whereBetween($column, $range);

                break;
            case 'null':
                $query->whereNull($column);

                break;
            case 'notnull':
                $query->whereNotNull($column);

                break;
            default:
                return "The operator [{$operator}] isn't supported. Supported: " . implode(', ', static::OPERATORS) . '.';
        }

        return null;
    }

    /**
     * The SQL expression of a group, formatted like laravel-trend so that clients can map it onto their charts.
     */
    protected static function getBucket(QueryBuilder $base, Model $model, string $group): string
    {
        [$bucket, $column] = array_pad(explode(':', $group, 2), 2, null);

        if (! in_array($bucket, static::BUCKETS, true)) {
            throw ValidationException::withMessages(['group' => 'The group must be one of ' . implode(', ', static::BUCKETS) . ', e.g. group=month:created_at.']);
        }

        if (blank($column) || (! static::isQueryable($model, $column))) {
            throw ValidationException::withMessages(['group' => "The column [{$column}] can't be grouped."]);
        }

        $wrapped = $base->getGrammar()->wrap($model->qualifyColumn($column));

        if ($bucket === 'value') {
            return $wrapped;
        }

        $driver = $model->getConnection()->getDriverName();

        // The SQL template takes the format (1) and the column (2).
        [$template, $formats] = match ($driver) {
            'sqlite' => ["strftime('%1\$s', %2\$s)", ['minute' => '%Y-%m-%d %H:%M:00', 'hour' => '%Y-%m-%d %H:00', 'day' => '%Y-%m-%d', 'month' => '%Y-%m', 'year' => '%Y']],
            'mysql', 'mariadb' => ["date_format(%2\$s, '%1\$s')", ['minute' => '%Y-%m-%d %H:%i:00', 'hour' => '%Y-%m-%d %H:00', 'day' => '%Y-%m-%d', 'month' => '%Y-%m', 'year' => '%Y']],
            'pgsql' => ["to_char(%2\$s, '%1\$s')", ['minute' => 'YYYY-MM-DD HH24:MI:00', 'hour' => 'YYYY-MM-DD HH24:00', 'day' => 'YYYY-MM-DD', 'month' => 'YYYY-MM', 'year' => 'YYYY']],
            'sqlsrv' => ["format(%2\$s, '%1\$s')", ['minute' => 'yyyy-MM-dd HH:mm:00', 'hour' => 'yyyy-MM-dd HH:00', 'day' => 'yyyy-MM-dd', 'month' => 'yyyy-MM', 'year' => 'yyyy']],
            default => throw ValidationException::withMessages(['group' => "Grouping by date isn't supported on the [{$driver}] database."]),
        };

        return sprintf($template, $formats[$bucket], $wrapped);
    }

    protected static function castAggregate(mixed $value, string $function, Model $model, ?string $column): mixed
    {
        if ($function === 'count') {
            return (int) $value;
        }

        if ($value === null) {
            return null;
        }

        // Databases return decimals and averages as strings: return numbers as JSON numbers.
        if (is_string($value) && is_numeric($value) && (in_array($function, ['sum', 'avg'], true) || static::isNumeric($model, $column))) {
            return str_contains($value, '.') || str_contains(strtolower($value), 'e') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * The columns that can be filtered, sorted and aggregated.
     *
     * @return array<string>
     */
    public static function getQueryableColumns(Model $model): array
    {
        return array_values(array_filter(
            array_keys(static::getColumns($model)),
            fn (string $column): bool => static::isQueryable($model, $column),
        ));
    }

    public static function isQueryable(Model $model, string $column): bool
    {
        if (! array_key_exists($column, static::getColumns($model))) {
            return false;
        }

        if (in_array($column, $model->getHidden(), true)) {
            return false;
        }

        $visible = $model->getVisible();

        return blank($visible) || in_array($column, $visible, true);
    }

    protected static function isNumeric(Model $model, ?string $column): bool
    {
        return (bool) (static::getColumns($model)[$column] ?? false);
    }

    /**
     * The columns of the model's table, mapped to whether they're numeric. Cached per connection and table.
     *
     * @return array<string, bool>
     */
    protected static function getColumns(Model $model): array
    {
        $key = $model->getConnectionName() . '.' . $model->getTable();

        return static::$columns[$key] ??= collect($model->getConnection()->getSchemaBuilder()->getColumns($model->getTable()))
            ->mapWithKeys(fn (array $column): array => [
                $column['name'] => Str::contains(strtolower($column['type_name']), ['int', 'dec', 'num', 'float', 'double', 'real', 'money']),
            ])
            ->all();
    }

    public static function flushColumns(): void
    {
        static::$columns = [];
    }
}
