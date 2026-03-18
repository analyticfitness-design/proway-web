<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\Client;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Domain\Client\ClientRepository;
use ProWay\Domain\Client\ClientService;

class ClientServiceTest extends TestCase
{
    private ClientRepository&MockObject $repo;
    private ClientService               $service;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(ClientRepository::class);
        $this->service = new ClientService($this->repo);
    }

    public function test_get_by_code_delegates_to_repo(): void
    {
        $client = ['id' => 1, 'code' => 'pw-001', 'name' => 'Test'];
        $this->repo->expects($this->once())->method('findByCode')->with('pw-001')->willReturn($client);

        $result = $this->service->getByCode('pw-001');

        $this->assertSame($client, $result);
    }

    public function test_get_by_code_returns_null_when_not_found(): void
    {
        $this->repo->method('findByCode')->willReturn(null);

        $result = $this->service->getByCode('pw-999');

        $this->assertNull($result);
    }

    public function test_get_active_clients_returns_list(): void
    {
        $clients = [['id' => 1], ['id' => 2]];
        $this->repo->method('findAllActive')->willReturn($clients);

        $result = $this->service->getActiveClients();

        $this->assertCount(2, $result);
    }

    public function test_update_delegates_to_repo(): void
    {
        $this->repo->expects($this->once())->method('update')->with(5, ['name' => 'New'])->willReturn(true);

        $result = $this->service->update(5, ['name' => 'New']);

        $this->assertTrue($result);
    }
}
