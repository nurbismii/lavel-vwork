<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditForbiddenAccess
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            if ($response->getStatusCode() === 403) {
                $this->record($request);
            }

            return $response;
        } catch (Throwable $exception) {
            if ($request->user() && $this->isForbidden($exception)) {
                $this->record($request);
            }

            throw $exception;
        }
    }

    private function isForbidden(Throwable $exception): bool
    {
        return $exception instanceof AuthorizationException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403);
    }

    private function record(Request $request): void
    {
        if (! $request->user()) {
            return;
        }

        try {
            $this->audit->record('security.access_denied', $request->user(), null, [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
            ], $request);
        } catch (Throwable $auditException) {
            report($auditException);
        }
    }
}
