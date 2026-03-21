<?php
declare(strict_types=1);

namespace ProWay\Domain\ContentCalendar;

interface ContentSlotRepository
{
    /** Slots for a single client between two dates. */
    public function findForClientInRange(int $clientId, string $from, string $to): array;

    /** All slots between two dates, optionally filtered by client. */
    public function findAllInRange(string $from, string $to, ?int $clientId = null): array;

    /** Find a single slot by ID. */
    public function findById(int $id): ?array;

    /** Insert a new slot, return inserted ID. */
    public function create(array $data): int;

    /** Update an existing slot, return true on success. */
    public function update(int $id, array $data): bool;

    /** Delete a slot, return true on success. */
    public function delete(int $id): bool;
}
