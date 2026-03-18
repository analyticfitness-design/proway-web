<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Auth\AuthService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class AuthController
{
    public function __construct(
        private readonly AuthService    $auth,
        private readonly AuthMiddleware $middleware,
    ) {}

    /**
     * POST /api/v1/auth/login
     * Body: { email, password } or { username, password }
     */
    public function login(Request $request, array $vars): never
    {
        $email    = $request->input('email');
        $username = $request->input('username');
        $password = $request->input('password');

        if (empty($password)) {
            Response::error('VALIDATION', 'password is required', 422);
        }

        // Attempt client login first, then admin
        if ($email) {
            $result = $this->auth->loginClient($email, $password);
        } elseif ($username) {
            $result = $this->auth->loginAdmin($username, $password);
        } else {
            Response::error('VALIDATION', 'email or username is required', 422);
        }

        if ($result === null) {
            Response::error('INVALID_CREDENTIALS', 'Invalid credentials', 401);
        }

        // httpOnly cookie for web clients (SEC-001)
        setcookie('pw_access', $result['token'], [
            'expires'  => time() + 3600 * 24,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);

        Response::success([
            'token' => $result['token'],
            'user'  => $result['user']->toArray(),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request, array $vars): never
    {
        $token = $request->resolveAccessToken();

        if ($token !== null) {
            $this->auth->logout($token);
        }

        // Clear the httpOnly cookie
        setcookie('pw_access', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);

        Response::success(['message' => 'Logged out successfully']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);
        Response::success(['user' => $user->toArray()]);
    }
}
