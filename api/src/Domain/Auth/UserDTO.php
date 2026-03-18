<?php
declare(strict_types=1);

namespace ProWay\Domain\Auth;

class UserDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $email,
        public readonly string $name,
        public readonly string $type,     // 'client' | 'admin'
        public readonly string $planType = '',
        public readonly string $code      = '',
    ) {}

    public static function fromArray(array $data, string $type): self
    {
        return new self(
            id:       (int) ($data['id'] ?? 0),
            email:    $data['email'] ?? '',
            name:     $data['name'] ?? '',
            type:     $type,
            planType: $data['plan_type'] ?? '',
            code:     $data['code'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'email'     => $this->email,
            'name'      => $this->name,
            'type'      => $this->type,
            'plan_type' => $this->planType,
            'code'      => $this->code,
        ];
    }
}
