<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\Project;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Domain\Project\ProjectRepository;
use ProWay\Domain\Project\ProjectService;

class ProjectServiceTest extends TestCase
{
    private ProjectRepository&MockObject $repo;
    private ProjectService               $service;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(ProjectRepository::class);
        $this->service = new ProjectService($this->repo);
    }

    public function test_update_status_throws_on_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus(1, 'invalid_status');
    }

    public function test_update_status_accepts_valid_statuses(): void
    {
        $this->repo->method('updateStatus')->willReturn(true);

        foreach (['pendiente', 'en_progreso', 'revision', 'completado'] as $status) {
            $this->assertTrue($this->service->updateStatus(1, $status));
        }
    }

    public function test_list_for_client_delegates_to_repo(): void
    {
        $rows = [['id' => 10, 'title' => 'Video A']];
        $this->repo->method('findAllForClient')->with(1)->willReturn($rows);

        $this->assertSame($rows, $this->service->listForClient(1));
    }
}
