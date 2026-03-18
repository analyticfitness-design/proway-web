<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Api\V1\Controller\InvoiceController;
use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Invoice\InvoiceService;
use ProWay\Infrastructure\Http\Response;

class InvoiceControllerTest extends TestCase
{
    private InvoiceService&MockObject $service;
    private AuthMiddleware&MockObject $mw;
    private InvoiceController          $ctrl;

    protected function setUp(): void
    {
        $this->service = $this->createMock(InvoiceService::class);
        $this->mw      = $this->createMock(AuthMiddleware::class);
        $this->ctrl    = new InvoiceController($this->service, $this->mw);
    }

    public function test_error_response_when_method_missing(): void
    {
        $result = Response::buildError('VALIDATION', 'method is required');
        $this->assertFalse($result['success']);
        $this->assertSame('VALIDATION', $result['error']['code']);
        $this->assertStringContainsString('method', $result['error']['message']);
    }

    public function test_service_throws_on_invalid_status(): void
    {
        $this->service->method('updateStatus')
            ->willThrowException(new \InvalidArgumentException('Invalid invoice status: cancelada'));

        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus(1, 'cancelada');
    }

    public function test_index_delegates_to_service(): void
    {
        $rows = [['id' => 1, 'invoice_number' => 'INV-001']];
        $this->service->method('listForClient')->with(5)->willReturn($rows);

        $result = Response::buildSuccess(['invoices' => $this->service->listForClient(5)]);
        $this->assertTrue($result['success']);
        $this->assertSame($rows, $result['data']['invoices']);
    }

    public function test_mark_paid_delegates_to_service(): void
    {
        $this->service->method('markPaid')->with(3, 'PayU', 'REF123')->willReturn(true);
        $this->assertTrue($this->service->markPaid(3, 'PayU', 'REF123'));
    }
}
