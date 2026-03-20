<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Deliverable\DeliverableService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class DeliverableController
{
    public function __construct(
        private readonly DeliverableService $deliverables,
        private readonly AuthMiddleware     $middleware,
    ) {}

    /**
     * GET /api/v1/deliverables?project_id=N
     */
    public function listByProject(Request $request, array $vars): never
    {
        $this->middleware->requireAuth($request);

        $projectId = (int) $request->query('project_id', 0);
        if ($projectId === 0) {
            Response::error('VALIDATION', 'project_id is required', 422);
        }

        Response::success([
            'deliverables' => $this->deliverables->listByProject($projectId),
        ]);
    }

    /**
     * POST /api/v1/admin/deliverables
     * Accepts multipart/form-data with file upload.
     */
    public function upload(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $projectId   = (int) ($_POST['project_id'] ?? 0);
        $type        = trim((string) ($_POST['type'] ?? ''));
        $title       = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? '')) ?: null;

        if ($projectId === 0 || $type === '' || $title === '') {
            Response::error('VALIDATION', 'project_id, type and title are required', 422);
        }

        if (empty($_FILES['file'])) {
            Response::error('VALIDATION', 'file is required', 422);
        }

        try {
            $deliverable = $this->deliverables->uploadFile(
                $projectId,
                $type,
                $title,
                $_FILES['file'],
                $description
            );
            Response::success(['deliverable' => $deliverable], 201);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            Response::error('UPLOAD_FAILED', $e->getMessage(), 500);
        }
    }
}
