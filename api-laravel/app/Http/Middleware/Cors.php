<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $headers = $this->prepareHeaders($request);

if ($request->getMethod() === Request::METHOD_OPTIONS) {
            return response()->noContent(204)->withHeaders($headers);
        }

        try {
            $response = $next($request);
        } catch (Throwable $throwable) {
            /** @var ExceptionHandler $exceptionHandler */
            $exceptionHandler = app(ExceptionHandler::class);
            $exceptionHandler->report($throwable);
            $response = $exceptionHandler->render($request, $throwable);
        }

        if (! $response instanceof Response) {
            $response = response($response);
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function prepareHeaders(Request $request): array
    {
        $allowedMethods = implode(', ', config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']));
        $requestHeaders = $request->headers->get('Access-Control-Request-Headers');
        $allowedHeaders = $requestHeaders ?: implode(', ', config('cors.allowed_headers', ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept']));

        return [
            'Access-Control-Allow-Origin' => $this->resolveOrigin($request),
            'Access-Control-Allow-Methods' => $allowedMethods,
            'Access-Control-Allow-Headers' => $allowedHeaders,
            'Access-Control-Allow-Credentials' => config('cors.supports_credentials') ? 'true' : 'false',
            'Access-Control-Max-Age' => (string) config('cors.max_age', 0),
        ];
    }

    private function resolveOrigin(Request $request): string
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins', ['*']);

        if (in_array('*', $allowedOrigins, true)) {
            return '*';
        }

        if ($origin && in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if ($origin && preg_match($pattern, $origin)) {
                return $origin;
            }
        }

        return $allowedOrigins[0] ?? '*';
    }
}
