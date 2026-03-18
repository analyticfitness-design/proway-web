<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\Auth;

use PHPUnit\Framework\TestCase;
use ProWay\Domain\Auth\UserDTO;

class UserDTOTest extends TestCase
{
    public function test_from_array_maps_fields(): void
    {
        $dto = UserDTO::fromArray([
            'id'        => '42',
            'email'     => 'test@prowaylab.com',
            'name'      => 'Test Client',
            'plan_type' => 'video_mensual',
            'code'      => 'pw-042',
        ], 'client');

        $this->assertSame(42, $dto->id);
        $this->assertSame('test@prowaylab.com', $dto->email);
        $this->assertSame('Test Client', $dto->name);
        $this->assertSame('client', $dto->type);
        $this->assertSame('video_mensual', $dto->planType);
        $this->assertSame('pw-042', $dto->code);
    }

    public function test_to_array_round_trips(): void
    {
        $dto = new UserDTO(
            id:       1,
            email:    'admin@prowaylab.com',
            name:     'Admin',
            type:     'admin',
        );

        $arr = $dto->toArray();

        $this->assertSame(1, $arr['id']);
        $this->assertSame('admin@prowaylab.com', $arr['email']);
        $this->assertSame('admin', $arr['type']);
        $this->assertSame('', $arr['plan_type']);
        $this->assertSame('', $arr['code']);
    }

    public function test_readonly_properties_cannot_be_mutated(): void
    {
        $dto = new UserDTO(1, 'a@b.com', 'Name', 'client');

        $this->expectException(\Error::class);
        $dto->id = 99; // @phpstan-ignore-line
    }

    public function test_admin_type_sets_empty_plan_and_code(): void
    {
        $dto = UserDTO::fromArray(['id' => 5, 'email' => 'a@b.com', 'name' => 'Admin'], 'admin');
        $this->assertSame('', $dto->planType);
        $this->assertSame('', $dto->code);
    }
}
