<?php

namespace Allandereal\FilamentApi\Http\Middleware;

use Allandereal\FilamentApi\Facades\FilamentApi;
use Allandereal\FilamentApi\Models\ApiRequest;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogApiRequest
{
    /**
     * The first middleware of the API routes: it starts the timer, so that the duration includes
     * authentication and rate limiting, and failed requests (401, 403, 429...) are recorded too.
     */
    public function handle(Request $request, Closure $next, string $panel): mixed
    {
        if (FilamentApi::getPlugin(Filament::getPanel($panel))->isLoggingRequests()) {
            $request->attributes->set('filament_api_log', [
                'panel' => $panel,
                'started_at' => hrtime(true),
            ]);
        }

        return $next($request);
    }

    /**
     * Record the request after the response has been sent to the client, so that logging doesn't slow
     * the API down. A failure to log is reported, but never breaks the request.
     */
    public function terminate(Request $request, Response $response): void
    {
        $log = $request->attributes->get('filament_api_log');

        if (! $log) {
            return;
        }

        try {
            $user = $request->user();
            $user = ($user instanceof Model) ? $user : null;

            $token = ($user && method_exists($user, 'currentAccessToken')) ? $user->currentAccessToken() : null;
            $token = ($token instanceof Model) ? $token : null;

            $route = $request->route();

            ApiRequest::create([
                'panel' => $log['panel'],
                'method' => $request->method(),
                'path' => Str::limit('/' . ltrim($request->path(), '/'), 255, ''),
                'query' => $request->query() ?: null,
                'route_name' => $route?->getName(),
                'endpoint' => $request->route('filamentApiEndpoint'),
                'action' => $route?->getActionMethod(),
                'record_key' => filled($record = $request->route('record')) ? Str::limit((string) $record, 255, '') : null,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) round((hrtime(true) - $log['started_at']) / 1_000_000),
                'user_type' => $user?->getMorphClass(),
                'user_id' => $user?->getKey(),
                'token_id' => $token?->getKey(),
                'token_name' => $token?->getAttribute('name'),
                'tenant_key' => Filament::getTenant()?->getKey(),
                'ip' => $request->ip(),
                'user_agent' => filled($userAgent = $request->userAgent()) ? Str::limit($userAgent, 255, '') : null,
                'error' => $this->getError($response),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function getError(Response $response): ?string
    {
        if ($response->getStatusCode() < 400) {
            return null;
        }

        $message = ($response instanceof JsonResponse) ? ($response->getData(true)['message'] ?? null) : null;

        return filled($message) ? Str::limit((string) $message, 1000) : Response::$statusTexts[$response->getStatusCode()] ?? null;
    }
}
