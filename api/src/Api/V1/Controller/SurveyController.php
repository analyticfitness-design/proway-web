<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Survey\SurveyService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class SurveyController
{
    public function __construct(
        private readonly SurveyService  $surveys,
        private readonly AuthMiddleware $middleware,
    ) {}

    /**
     * GET /api/v1/surveys/pending — client sees outstanding survey
     */
    public function pending(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        $surveys = $this->surveys->getPendingForClient($user->id);

        Response::success(['surveys' => $surveys]);
    }

    /**
     * POST /api/v1/surveys/{id}/respond — client submits score + comment
     */
    public function respond(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        $surveyId = (int) $vars['id'];
        $score    = $request->input('score');
        $comment  = trim((string) $request->input('comment', '')) ?: null;

        if ($score === null || $score === '') {
            Response::error('VALIDATION', 'El campo score es requerido.', 422);
        }

        $score = (int) $score;

        try {
            $this->surveys->respond($surveyId, $user->id, $score, $comment);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            Response::error('FORBIDDEN', $e->getMessage(), 403);
        }

        Response::success(['message' => '¡Gracias por tu respuesta!']);
    }

    /**
     * GET /api/v1/admin/surveys — recent responses (admin)
     */
    public function adminList(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $limit   = (int) ($request->query('limit') ?: 50);
        $avgNPS  = $this->surveys->getAverageNPS();
        $surveys = $this->surveys->listRecent($limit);

        Response::success([
            'average_nps' => $avgNPS,
            'surveys'     => $surveys,
        ]);
    }
}
