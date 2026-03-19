<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ProWay\Domain\Project\CachedProjectRepository;
use ProWay\Domain\Project\ProjectRepository;
use ProWay\Infrastructure\Cache\CacheInterface;

class CachedProjectRepositoryTest extends TestCase
{
    private ProjectRepository&MockObject $inner;
    private CacheInterface&MockObject    $cache;
    private CachedProjectRepository      $repo;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(ProjectRepository::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->repo  = new CachedProjectRepository($this->inner, $this->cache);
    }

    public function test_findAllForClient_hits_cache_on_second_call(): void
    {
        $clientId = 42;
        $projects = [['id' => 1, 'title' => 'Test project']];

        // First call: cache miss, then store result
        $this->cache
            ->expects($this->exactly(2))
            ->method('get')
            ->with("pw:projects:client:{$clientId}")
            ->willReturnOnConsecutiveCalls(null, $projects);

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with("pw:projects:client:{$clientId}", $projects, 300);

        // Inner repository should only be called once (cache miss)
        $this->inner
            ->expects($this->once())
            ->method('findAllForClient')
            ->with($clientId)
            ->willReturn($projects);

        $result1 = $this->repo->findAllForClient($clientId);
        $result2 = $this->repo->findAllForClient($clientId);

        $this->assertSame($projects, $result1);
        $this->assertSame($projects, $result2);
    }

    public function test_findAllForClient_returns_data_from_cache_when_warm(): void
    {
        $clientId = 7;
        $cached   = [['id' => 3, 'title' => 'Cached project']];

        $this->cache
            ->method('get')
            ->with("pw:projects:client:{$clientId}")
            ->willReturn($cached);

        // Inner should never be called
        $this->inner
            ->expects($this->never())
            ->method('findAllForClient');

        $result = $this->repo->findAllForClient($clientId);
        $this->assertSame($cached, $result);
    }

    public function test_findById_caches_result(): void
    {
        $id      = 5;
        $project = ['id' => 5, 'title' => 'My project'];

        $this->cache
            ->method('get')
            ->with("pw:projects:{$id}")
            ->willReturn(null);

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with("pw:projects:{$id}", $project, 600);

        $this->inner
            ->method('findById')
            ->with($id)
            ->willReturn($project);

        $result = $this->repo->findById($id);
        $this->assertSame($project, $result);
    }

    public function test_findById_returns_null_and_does_not_cache_when_not_found(): void
    {
        $id = 999;

        $this->cache
            ->method('get')
            ->with("pw:projects:{$id}")
            ->willReturn(null);

        $this->cache
            ->expects($this->never())
            ->method('set');

        $this->inner
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $result = $this->repo->findById($id);
        $this->assertNull($result);
    }

    public function test_updateStatus_invalidates_cache(): void
    {
        $id     = 10;
        $status = 'completado';

        $this->inner
            ->method('updateStatus')
            ->with($id, $status)
            ->willReturn(true);

        // Must invalidate the project key
        $this->cache
            ->expects($this->once())
            ->method('delete')
            ->with("pw:projects:{$id}");

        $result = $this->repo->updateStatus($id, $status);
        $this->assertTrue($result);
    }

    public function test_updateStatus_does_not_invalidate_cache_when_inner_fails(): void
    {
        $id     = 10;
        $status = 'completado';

        $this->inner
            ->method('updateStatus')
            ->with($id, $status)
            ->willReturn(false);

        // No deletion should happen when inner returns false
        $this->cache
            ->expects($this->never())
            ->method('delete');

        $result = $this->repo->updateStatus($id, $status);
        $this->assertFalse($result);
    }
}
