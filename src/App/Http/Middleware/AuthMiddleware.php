<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        session_start();
        if (empty($_SESSION['user_id'])) {
            if ($request->expectsJson()) {
                return Response::json(['ok' => false, 'error' => 'Unauthorized'], 401);
            }
            return Response::html('Unauthorized', 401);
        }

        return $next($request);
    }
}
