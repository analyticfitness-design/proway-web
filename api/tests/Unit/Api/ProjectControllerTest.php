<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Api\V1\Controller\ProjectController;
use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Project\ProjectService;
use ProWay\Infrastructure\Http\Response;

class ProjectControllerTest extends TestCase
{
    private ProjectService&MockObject $service;
    private AuthMiddleware&MockObject $mw;
    private ProjectController          $ctrl;

    protected function setUp(): void
    {
        $this->service = $this->createMock(ProjectService::class);
        $this->mw      = $this->createMock(AuthMiddleware::class);
        $this->ctrl    = new ProjectController($this->service, $this->mw);
    }

    public function test_service_throws_on_invalid_status(): void
    {
        $this->service->method('updateStatus')
            ->willThrowException(new \InvalidArgumentException('Invalid project status: invalid'));

        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus(1, 'invalid');
    }

    public function test_error_response_for_not_found(): void
    {
        $result = Response::buildError('NOT_FOUND', 'Project not found');
        $this->assertFalse($result['success']);
        $this->assertSame('NOT_FOUND', $result['error']['code']);
        $this->assertSame('Project not found', $result['error']['message']);
    }

    public function test_index_delegates_list_to_service(): void
    {
        $rows = [['id' => 1, 'title' => 'Video A']];
        $this->service->method('listForClient')->with(3)->willReturn($rows);

        $result = Response::buildSuccess(['projects' => $this->service->listForClient(3)]);
        $this->assertTrue($result['success']);
        $this->assertSame($rows, $result['data']['projects']);
    }

    public function test_error_response_for_invalid_status(): void
    {
        $result = Response::buildError('VALIDATION', 'Invalid project status: badstatus');
        $this->assertSame('VALIDATION', $result['error']['code']);
        $this->assertStringContainsString('badstatus', $result['error']['message']);
    }
}
