<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Auth\AuthService;
use ProWay\Infrastructure\Email\MailjetService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class AuthController
{
    public function __construct(
        private readonly AuthService    $auth,
        private readonly AuthMiddleware $middleware,
        private readonly MailjetService $mailer,
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

    /**
     * POST /api/v1/auth/forgot-password
     * Body: { email }
     */
    public function forgotPassword(Request $request, array $vars): never
    {
        $email = $request->input('email');

        if (empty($email)) {
            Response::error('VALIDATION', 'email is required', 422);
        }

        $result = $this->auth->forgotPassword($email);

        // Always return success to avoid email enumeration
        if ($result !== null) {
            $resetLink = 'https://prowaylab.com/reset-password.html?token=' . $result['token'];
            $this->mailer->send(
                $result['email'],
                $result['name'],
                'Restablecer tu contraseña — ProWay Lab',
                $this->buildResetEmailHtml($result['name'], $resetLink),
            );
        }

        Response::success(['message' => 'If that email exists, a reset link has been sent']);
    }

    /**
     * POST /api/v1/auth/reset-password
     * Body: { token, password }
     */
    public function resetPassword(Request $request, array $vars): never
    {
        $token    = $request->input('token');
        $password = $request->input('password');

        if (empty($token) || empty($password)) {
            Response::error('VALIDATION', 'token and password are required', 422);
        }

        if (strlen($password) < 8) {
            Response::error('VALIDATION', 'password must be at least 8 characters', 422);
        }

        $ok = $this->auth->resetPassword($token, $password);

        if (!$ok) {
            Response::error('INVALID_TOKEN', 'Token is invalid or expired', 400);
        }

        Response::success(['message' => 'Password has been reset successfully']);
    }

    // ── Private helpers ──────────────────────────────────────────────────────────

    private function buildResetEmailHtml(string $name, string $resetLink): string
    {
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $link = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto; padding: 24px;">
          <div style="border-bottom: 3px solid #4F8EFF; padding-bottom: 16px; margin-bottom: 24px;">
            <h1 style="color: #4F8EFF; margin: 0; font-size: 22px;">ProWay Lab</h1>
          </div>
          <h2 style="font-size: 18px;">Restablecer tu contrase&ntilde;a</h2>
          <p>Hola <strong>{$name}</strong>,</p>
          <p>Recibimos una solicitud para restablecer la contrase&ntilde;a de tu cuenta. Haz clic en el bot&oacute;n para crear una nueva contrase&ntilde;a:</p>
          <div style="text-align: center; margin: 32px 0;">
            <a href="{$link}" style="background: #4F8EFF; color: white; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: bold;">
              Restablecer contrase&ntilde;a
            </a>
          </div>
          <p style="color: #666; font-size: 13px;">Este enlace expira en 1 hora. Si no solicitaste este cambio, puedes ignorar este correo.</p>
          <p style="margin-top: 32px; color: #666; font-size: 13px;">ProWay Lab &mdash; Soluciones digitales para marcas fitness</p>
        </body>
        </html>
        HTML;
    }
}
