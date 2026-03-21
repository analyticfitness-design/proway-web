<?php
declare(strict_types=1);

namespace ProWay\Domain\Brief;

class BriefService
{
    public function __construct(private readonly BriefRepository $repo) {}

    public function getByProject(int $projectId): ?array
    {
        return $this->repo->findByProject($projectId);
    }

    /**
     * Save or update a brief as draft.
     */
    public function saveDraft(int $projectId, array $fields): int
    {
        $fields['project_id'] = $projectId;
        $fields['status']     = 'draft';

        return $this->repo->upsert($fields);
    }

    /**
     * Submit the brief (marks it as submitted).
     *
     * @throws \RuntimeException if brief not found
     */
    public function submit(int $projectId): bool
    {
        $brief = $this->repo->findByProject($projectId);

        if ($brief === null) {
            throw new \RuntimeException('No existe un brief para este proyecto. Guarda un borrador primero.');
        }

        return $this->repo->submit($projectId);
    }
}
