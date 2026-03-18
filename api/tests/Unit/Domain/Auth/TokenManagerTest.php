<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\Auth;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Domain\Auth\TokenManager;
use PDO;
use PDOStatement;

class TokenManagerTest extends TestCase
{
    private PDO&MockObject $pdo;
    private TokenManager $manager;

    protected function setUp(): void
    {
        $this->pdo     = $this->createMock(PDO::class);
        $this->manager = new TokenManager($this->pdo);
    }

    public function test_create_returns_64_char_hex_token(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($stmt);

        $token = $this->manager->create(1, 'client', 168);

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function test_validate_returns_null_when_not_found(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->manager->validate('invalidtoken');

        $this->assertNull($result);
    }

    public function test_validate_returns_data_when_found(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['client_id' => 5, 'type' => 'client']);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->manager->validate('sometoken');

        $this->assertSame(5, $result['client_id']);
        $this->assertSame('client', $result['type']);
    }
}
