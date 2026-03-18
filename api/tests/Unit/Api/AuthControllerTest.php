<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Api\V1\Controller\AuthController;
use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Auth\AuthService;
use ProWay\Domain\Auth\UserDTO;
use ProWay\Infrastructure\Http\Response;

class AuthControllerTest extends TestCase
{
    private AuthService&MockObject    $auth;
    private AuthMiddleware&MockObject $mw;
    private AuthController            $ctrl;

    protected function setUp(): void
    {
        $this->auth = $this->createMock(AuthService::class);
        $this->mw   = $this->createMock(AuthMiddleware::class);
        $this->ctrl = new AuthController($this->auth, $this->mw);
    }

    public function test_error_response_has_correct_structure(): void
    {
        $result = Response::buildError('VALIDATION', 'password is required');

        $this->assertFalse($result['success']);
        $this->assertSame('VALIDATION', $result['error']['code']);
        $this->assertSame('password is required', $result['error']['message']);
        $this->assertNull($result['data']);
    }

    public function test_error_response_for_bad_credentials(): void
    {
        $result = Response::buildError('INVALID_CREDENTIALS', 'Invalid credentials');

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_CREDENTIALS', $result['error']['code']);
        $this->assertStringContainsString('credentials', $result['error']['message']);
    }

    public function test_error_response_for_identifier_missing(): void
    {
        $result = Response::buildError('VALIDATION', 'email or username is required');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('email', $result['error']['message']);
    }

    public function test_auth_service_returns_null_on_bad_credentials(): void
    {
        $this->auth->method('loginClient')->willReturn(null);
        $result = $this->auth->loginClient('bad@test.com', 'wrongpass');
        $this->assertNull($result);
    }

    public function test_user_dto_has_correct_type(): void
    {
        $user = new UserDTO(1, 'test@test.com', 'Test', 'client', 'mensual', 'PW001');
        $this->assertSame('client', $user->type);
        $this->assertSame('PW001', $user->code);
    }

    public function test_logout_success_response(): void
    {
        $result = Response::buildSuccess(['message' => 'Logged out successfully']);
        $this->assertTrue($result['success']);
        $this->assertSame('Logged out successfully', $result['data']['message']);
    }
}
