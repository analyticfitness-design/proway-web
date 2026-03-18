<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\Project;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Domain\Project\ProjectService;
use PDO;
use PDOStatement;

class ProjectServiceTest extends TestCase
{
    private PDO&MockObject     $pdo;
    private ProjectService     $service;

    protected function setUp(): void
    {
        $this->pdo     = $this->createMock(PDO::class);
        $this->service = new ProjectService($this->pdo);
    }

    public function test_update_status_throws_on_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus(1, 'invalid_status');
    }

    public function test_update_status_accepts_valid_statuses(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);
        $this->pdo->method('prepare')->willReturn($stmt);

        foreach (['pendiente', 'en_progreso', 'revision', 'completado'] as $status) {
            $result = $this->service->updateStatus(1, $status);
            $this->assertTrue($result);
        }
    }

    public function test_list_for_client_returns_array(): void
    {
        $rows = [['id' => 10, 'title' => 'Video A']];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->listForClient(1);

        $this->assertSame($rows, $result);
    }
}
