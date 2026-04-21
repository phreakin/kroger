<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

final class JsonMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        if ($request->expectsJson() && !isset($response->headers['Content-Type'])) {
            return new Response($response->status, $response->content, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return $response;
    }
}
