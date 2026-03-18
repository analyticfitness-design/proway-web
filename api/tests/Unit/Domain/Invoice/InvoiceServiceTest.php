<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\Invoice;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Domain\Invoice\InvoiceService;
use PDO;
use PDOStatement;

class InvoiceServiceTest extends TestCase
{
    private PDO&MockObject $pdo;
    private InvoiceService $service;

    protected function setUp(): void
    {
        $this->pdo     = $this->createMock(PDO::class);
        $this->service = new InvoiceService($this->pdo);
    }

    public function test_update_status_throws_on_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus(1, 'cancelada');
    }

    public function test_mark_paid_executes_update(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->markPaid(5, 'PayU', 'REF123');

        $this->assertTrue($result);
    }

    public function test_list_for_client_returns_invoices(): void
    {
        $rows = [['id' => 1, 'invoice_number' => 'INV-2026-001']];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);
        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->service->listForClient(3);

        $this->assertSame($rows, $result);
    }
}
