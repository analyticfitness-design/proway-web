<?php
declare(strict_types=1);

namespace ProWay\Domain\ContentCalendar;

class ContentCalendarService
{
    private const VALID_STATUSES = ['planned', 'in_production', 'ready', 'published', 'cancelled'];
    private const VALID_TYPES    = ['reel', 'story', 'post', 'video', 'carousel'];

    public function __construct(private readonly ContentSlotRepository $repo) {}

    /**
     * Client view: get own slots for the next N days (30/60/90).
     */
    public function getClientCalendar(int $clientId, int $horizon = 30): array
    {
        $from = date('Y-m-d');
        $to   = date('Y-m-d', strtotime("+{$horizon} days"));
        return $this->repo->findForClientInRange($clientId, $from, $to);
    }

    /**
     * Admin view: all slots in a date range, optionally filtered by client.
     */
    public function getAdminCalendar(string $from, string $to, ?int $clientId = null): array
    {
        return $this->repo->findAllInRange($from, $to, $clientId);
    }

    /**
     * Find a single slot by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    /**
     * Create a new content slot.
     *
     * @throws \InvalidArgumentException
     */
    public function createSlot(array $data): array
    {
        $this->validateSlot($data);

        $id = $this->repo->create($data);
        return $this->repo->findById($id) ?? ['id' => $id];
    }

    /**
     * Update an existing content slot.
     *
     * @throws \InvalidArgumentException
     */
    public function updateSlot(int $id, array $data): array
    {
        $existing = $this->repo->findById($id);
        if ($existing === null) {
            throw new \InvalidArgumentException('Slot no encontrado.');
        }

        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Estado inválido. Valores permitidos: ' . implode(', ', self::VALID_STATUSES)
            );
        }

        if (isset($data['content_type']) && !in_array($data['content_type'], self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Tipo de contenido inválido. Valores permitidos: ' . implode(', ', self::VALID_TYPES)
            );
        }

        $this->repo->update($id, $data);
        return $this->repo->findById($id) ?? $existing;
    }

    /**
     * Delete a content slot.
     */
    public function deleteSlot(int $id): bool
    {
        return $this->repo->delete($id);
    }

    private function validateSlot(array $data): void
    {
        if (empty($data['client_id'])) {
            throw new \InvalidArgumentException('client_id es requerido.');
        }
        if (empty($data['scheduled_date'])) {
            throw new \InvalidArgumentException('scheduled_date es requerido.');
        }
        if (empty($data['content_type'])) {
            throw new \InvalidArgumentException('content_type es requerido.');
        }
        if (!in_array($data['content_type'], self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Tipo de contenido inválido. Valores permitidos: ' . implode(', ', self::VALID_TYPES)
            );
        }
        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Estado inválido. Valores permitidos: ' . implode(', ', self::VALID_STATUSES)
            );
        }
    }
}
