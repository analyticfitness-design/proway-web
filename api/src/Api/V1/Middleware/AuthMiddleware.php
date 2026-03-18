<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Middleware;

use ProWay\Domain\Auth\AuthService;
use ProWay\Domain\Auth\UserDTO;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class AuthMiddleware
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * Validate the bearer token / httpOnly cookie.
     * Returns the authenticated UserDTO on success, or emits a 401 and exits.
     */
    public function requireAuth(Request $request): UserDTO
    {
        $token = $request->resolveAccessToken();

        if ($token === null) {
            Response::error('UNAUTHENTICATED', 'Token missing', 401);
        }

        $user = $this->auth->getCurrentUser($token);

        if ($user === null) {
            Response::error('UNAUTHENTICATED', 'Invalid or expired token', 401);
        }

        return $user;
    }

    /**
     * Like requireAuth(), but also enforces that the user is an admin.
     */
    public function requireAdmin(Request $request): UserDTO
    {
        $user = $this->requireAuth($request);

        if ($user->type !== 'admin') {
            Response::error('FORBIDDEN', 'Admin access required', 403);
        }

        return $user;
    }
}
