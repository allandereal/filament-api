<?php

namespace Allandereal\FilamentApi\Widgets;

use Allandereal\FilamentApi\Models\ApiRequest;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * The API traffic of the current panel over the last 24 hours.
 */
class ApiRequestsOverview extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '30s';

    // The stats are cheap indexed queries, so they render with the page instead of loading afterwards.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $requests = $this->getQuery()->count();

        $errors = $this->getQuery()->where('status_code', '>=', 400)->count();
        $serverErrors = $this->getQuery()->where('status_code', '>=', 500)->count();
        $errorRate = $requests ? ($errors / $requests) * 100 : 0;

        $averageDuration = (int) round((float) $this->getQuery()->avg('duration_ms'));

        $slowest = $this->getQuery()
            ->whereNotNull('endpoint')
            ->selectRaw('endpoint, avg(duration_ms) as average_duration')
            ->groupBy('endpoint')
            ->orderByDesc('average_duration')
            ->first();

        return [
            Stat::make('Requests', Number::format($requests))
                ->description('In the last 24 hours'),
            Stat::make('Error rate', Number::format($errorRate, maxPrecision: 1) . '%')
                ->description(trans_choice('{0} No server errors|{1} :count server error (5xx)|[2,*] :count server errors (5xx)', $serverErrors, ['count' => Number::format($serverErrors)]))
                ->color(match (true) {
                    $serverErrors > 0 => 'danger',
                    $errorRate > 10 => 'warning',
                    default => 'success',
                }),
            Stat::make('Average response time', $requests ? "{$averageDuration} ms" : '—')
                ->description('Including authentication'),
            // Endpoints can be long, so the endpoint goes in the description rather than the large value.
            Stat::make('Slowest endpoint', $slowest ? ((int) round((float) $slowest->getAttribute('average_duration'))) . ' ms' : '—')
                ->description($slowest ? $slowest->getAttribute('endpoint') . ' (average)' : 'No requests yet'),
        ];
    }

    /**
     * @return Builder<ApiRequest>
     */
    protected function getQuery(): Builder
    {
        return ApiRequest::query()
            ->where('panel', Filament::getCurrentPanel()->getId())
            ->where('created_at', '>=', now()->subDay());
    }
}
