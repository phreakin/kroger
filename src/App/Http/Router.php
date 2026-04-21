<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\MiddlewareInterface;

final class Router
{
    private array $routes = [];
    private array $namedMiddleware = [];

    public function middleware(string $name, MiddlewareInterface $middleware): void
    {
        $this->namedMiddleware[$name] = $middleware;
    }

    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [strtoupper($method), $path, $handler, $middleware];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as [$method, $path, $handler, $middleware]) {
            if ($method === $request->method && $path === $request->path) {
                $pipeline = array_reduce(
                    array_reverse($middleware),
                    fn (callable $next, string $name) => fn (Request $req): Response => $this->namedMiddleware[$name]->handle($req, $next),
                    fn (Request $req): Response => $handler($req)
                );

                return $pipeline($request);
            }
        }

        return $request->expectsJson()
            ? Response::json(['ok' => false, 'error' => 'Not Found'], 404)
            : Response::html('Not Found', 404);
    }
}
