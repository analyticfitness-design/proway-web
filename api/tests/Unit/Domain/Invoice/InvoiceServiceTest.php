<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\Invoice;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Domain\Invoice\InvoiceRepository;
use ProWay\Domain\Invoice\InvoiceService;

class InvoiceServiceTest extends TestCase
{
    private InvoiceRepository&MockObject $repo;
    private InvoiceService               $service;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(InvoiceRepository::class);
        $this->service = new InvoiceService($this->repo);
    }

    public function test_update_status_throws_on_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus(1, 'cancelada');
    }

    public function test_mark_paid_delegates_to_repo(): void
    {
        $this->repo->expects($this->once())->method('markPaid')->with(5, 'PayU', 'REF123')->willReturn(true);

        $this->assertTrue($this->service->markPaid(5, 'PayU', 'REF123'));
    }

    public function test_list_for_client_delegates_to_repo(): void
    {
        $rows = [['id' => 1, 'invoice_number' => 'INV-2026-001']];
        $this->repo->method('findAllForClient')->with(3)->willReturn($rows);

        $this->assertSame($rows, $this->service->listForClient(3));
    }
}
