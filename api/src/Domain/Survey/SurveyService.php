<?php
declare(strict_types=1);

namespace ProWay\Domain\Survey;

class SurveyService
{
    private const VALID_TYPES  = ['nps', 'csat'];
    private const NPS_MAX      = 10;
    private const CSAT_MAX     = 5;

    public function __construct(private readonly SurveyRepository $repo) {}

    /**
     * Create and send an NPS survey after a deliverable is approved.
     * A new survey is only created when no pending/sent one exists for the client.
     */
    public function triggerNPS(int $clientId, int $projectId, ?int $deliverableId = null): int
    {
        // Avoid duplicate pending surveys for the same client
        $existing = $this->repo->findPendingForClient($clientId);
        if (!empty($existing)) {
            return (int) $existing[0]['id'];
        }

        return $this->repo->create([
            'client_id'      => $clientId,
            'project_id'     => $projectId,
            'deliverable_id' => $deliverableId,
            'type'           => 'nps',
            'status'         => 'sent',
            'sent_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Record a client's survey response.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function respond(int $surveyId, int $clientId, int $score, ?string $comment): void
    {
        /** @var MySQLSurveyRepository $repo */
        $survey = $this->repo instanceof MySQLSurveyRepository
            ? $this->repo->findById($surveyId)
            : null;

        if ($survey === null) {
            throw new \RuntimeException('Encuesta no encontrada.');
        }

        if ((int) $survey['client_id'] !== $clientId) {
            throw new \RuntimeException('No tienes acceso a esta encuesta.');
        }

        if (!in_array($survey['status'], ['pending', 'sent'], true)) {
            throw new \RuntimeException('Esta encuesta ya fue respondida o expiró.');
        }

        $max = $survey['type'] === 'csat' ? self::CSAT_MAX : self::NPS_MAX;
        if ($score < 0 || $score > $max) {
            throw new \InvalidArgumentException("Puntaje inválido. Rango válido: 0–{$max}.");
        }

        $this->repo->respond($surveyId, $score, $comment);
    }

    /**
     * Return the average NPS score, optionally scoped to a project.
     */
    public function getAverageNPS(?int $projectId = null): float
    {
        return $this->repo->averageNPS($projectId);
    }

    /**
     * Return pending surveys for the authenticated client.
     */
    public function getPendingForClient(int $clientId): array
    {
        return $this->repo->findPendingForClient($clientId);
    }

    /**
     * Return recent responses for admin view.
     */
    public function listRecent(int $limit = 50): array
    {
        return $this->repo->listRecent($limit);
    }
}
