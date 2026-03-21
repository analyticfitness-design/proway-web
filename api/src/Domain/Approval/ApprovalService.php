<?php
declare(strict_types=1);

namespace ProWay\Domain\Approval;

class ApprovalService
{
    private const VALID_STATUSES = ['approved', 'changes_requested'];

    public function __construct(private readonly ApprovalRepository $repo) {}

    /**
     * Client reviews a deliverable (approve or request changes).
     *
     * @throws \InvalidArgumentException
     */
    public function review(int $deliverableId, int $clientId, string $status, ?string $comment): array
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Estado inválido. Valores permitidos: ' . implode(', ', self::VALID_STATUSES)
            );
        }

        $this->repo->upsert([
            'deliverable_id' => $deliverableId,
            'client_id'      => $clientId,
            'status'         => $status,
            'comment'        => $comment,
            'reviewed_at'    => date('Y-m-d H:i:s'),
        ]);

        // Return the full row (findByDeliverable returns array of rows)
        $rows = $this->repo->findByDeliverable($deliverableId);
        foreach ($rows as $row) {
            if ((int) $row['client_id'] === $clientId) {
                return $row;
            }
        }

        // Fallback — should never happen after a successful upsert
        return [
            'deliverable_id' => $deliverableId,
            'client_id'      => $clientId,
            'status'         => $status,
            'comment'        => $comment,
        ];
    }

    public function listByProject(int $projectId): array
    {
        return $this->repo->findByProject($projectId);
    }

    public function listPendingAll(int $limit = 50): array
    {
        return $this->repo->findPendingAll($limit);
    }
}
