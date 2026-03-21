<?php
declare(strict_types=1);

namespace ProWay\Domain\AI;

interface SuggestionRepository
{
    /**
     * Insert a new AI suggestion row.
     * @param array{client_id: int, prompt_type: string, context_json: string, result_text: string, tokens_used: int, expires_at: string} $data
     */
    public function create(array $data): int;

    /**
     * Return the N most recent suggestions for a client, newest first.
     * @return array<int, array>
     */
    public function findLatestForClient(int $clientId, int $limit = 5): array;

    /**
     * Find a single suggestion by ID.
     */
    public function findById(int $id): ?array;

    /**
     * Find a fresh (< 24 h) suggestion for a given client + prompt type.
     * Returns the row or null if nothing fresh exists.
     */
    public function findFresh(int $clientId, string $promptType): ?array;

    /**
     * Count how many suggestions were created for a given client in the last hour.
     */
    public function countRecentByClient(int $clientId, int $windowMinutes = 60): int;
}
