<?php
declare(strict_types=1);

namespace ProWay\Domain\Survey;

interface SurveyRepository
{
    /**
     * Persist a new survey record and return its generated ID.
     */
    public function create(array $data): int;

    /**
     * Return pending/sent surveys for a client (outstanding responses).
     */
    public function findPendingForClient(int $clientId): array;

    /**
     * Record the client's response (score + optional comment).
     */
    public function respond(int $id, int $score, ?string $comment): void;

    /**
     * Return the average NPS score across all responded surveys.
     * Optionally scoped to a project.
     */
    public function averageNPS(?int $projectId = null): float;

    /**
     * Return the most recent responded surveys for admin review.
     */
    public function listRecent(int $limit = 50): array;
}
