<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\Auth;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Domain\Auth\AuthService;
use ProWay\Domain\Auth\TokenManager;
use ProWay\Domain\Auth\UserDTO;
use PDO;
use PDOStatement;

class AuthServiceTest extends TestCase
{
    private PDO&MockObject          $pdo;
    private TokenManager&MockObject $tokens;
    private AuthService             $service;

    protected function setUp(): void
    {
        $this->pdo     = $this->createMock(PDO::class);
        $this->tokens  = $this->createMock(TokenManager::class);
        $this->service = new AuthService($this->pdo, $this->tokens);
    }

    // ── loginClient ──────────────────────────────────────────────────────────

    public function test_login_client_returns_null_on_wrong_password(): void
    {
        $hash = password_hash('correct', PASSWORD_BCRYPT);
        $row  = ['id' => 1, 'email' => 'a@b.com', 'name' => 'A', 'plan_type' => '', 'code' => 'pw-001', 'password_hash' => $hash];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->loginClient('a@b.com', 'wrong');

        $this->assertNull($result);
    }

    public function test_login_client_returns_token_and_user_on_success(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);
        $row  = ['id' => 7, 'email' => 'c@d.com', 'name' => 'Client', 'plan_type' => 'video_mensual', 'code' => 'pw-007', 'password_hash' => $hash];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        $this->pdo->method('prepare')->willReturn($stmt);

        $this->tokens->expects($this->once())
            ->method('create')
            ->with(7, 'client')
            ->willReturn('tok123');

        $result = $this->service->loginClient('c@d.com', 'secret');

        $this->assertNotNull($result);
        $this->assertSame('tok123', $result['token']);
        $this->assertInstanceOf(UserDTO::class, $result['user']);
        $this->assertSame(7, $result['user']->id);
        $this->assertSame('client', $result['user']->type);
    }

    public function test_login_client_returns_null_when_user_not_found(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->loginClient('nobody@b.com', 'pass');

        $this->assertNull($result);
    }

    // ── loginAdmin ────────────────────────────────────────────────────────────

    public function test_login_admin_returns_null_on_wrong_password(): void
    {
        $hash = password_hash('adminpass', PASSWORD_BCRYPT);
        $row  = ['id' => 2, 'email' => 'admin@pw.com', 'name' => 'Admin', 'password_hash' => $hash];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->loginAdmin('admin', 'wrongpass');

        $this->assertNull($result);
    }

    public function test_login_admin_sets_admin_type_on_success(): void
    {
        $hash = password_hash('adminpass', PASSWORD_BCRYPT);
        $row  = ['id' => 2, 'email' => 'admin@pw.com', 'name' => 'Admin', 'password_hash' => $hash];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        $this->pdo->method('prepare')->willReturn($stmt);

        $this->tokens->method('create')->willReturn('admintok');

        $result = $this->service->loginAdmin('admin', 'adminpass');

        $this->assertNotNull($result);
        $this->assertSame('admin', $result['user']->type);
        $this->assertSame('', $result['user']->planType);
    }

    // ── getCurrentUser ────────────────────────────────────────────────────────

    public function test_get_current_user_returns_null_on_invalid_token(): void
    {
        $this->tokens->method('validate')->willReturn(null);

        $result = $this->service->getCurrentUser('bad_token');

        $this->assertNull($result);
    }

    public function test_get_current_user_returns_dto_for_valid_client_token(): void
    {
        $this->tokens->method('validate')->willReturn(['client_id' => 3, 'type' => 'client']);

        $row  = ['id' => 3, 'email' => 'x@y.com', 'name' => 'X', 'plan_type' => 'video_individual', 'code' => 'pw-003'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        $this->pdo->method('prepare')->willReturn($stmt);

        $user = $this->service->getCurrentUser('validtoken');

        $this->assertInstanceOf(UserDTO::class, $user);
        $this->assertSame(3, $user->id);
        $this->assertSame('client', $user->type);
    }

    // ── logout ────────────────────────────────────────────────────────────────

    public function test_logout_calls_revoke(): void
    {
        $this->tokens->expects($this->once())->method('revoke')->with('sometoken');

        $this->service->logout('sometoken');
    }
}
