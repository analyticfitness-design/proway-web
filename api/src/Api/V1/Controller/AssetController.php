<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Asset\AssetService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class AssetController
{
    public function __construct(
        private readonly AssetService   $assets,
        private readonly AuthMiddleware $middleware,
    ) {}

    /**
     * GET /api/v1/admin/assets — search all assets with filters.
     */
    public function adminSearch(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $filters = $this->extractFilters($request);

        $page    = (int) ($request->query('page') ?: 1);
        $perPage = (int) ($request->query('per_page') ?: 24);

        $result = $this->assets->search($filters, $page, $perPage);

        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    /**
     * GET /api/v1/admin/assets/tags — list all tags.
     */
    public function listTags(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        Response::success(['tags' => $this->assets->getTags()]);
    }

    /**
     * POST /api/v1/admin/assets/{id}/tags — attach tags to deliverable.
     * Body: { "tag_ids": [1, 2, 3] }
     */
    public function updateTags(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $id     = (int) $vars['id'];
        $tagIds = $request->input('tag_ids', []);

        if (!is_array($tagIds)) {
            Response::error('VALIDATION', 'tag_ids must be an array', 422);
        }

        $tagIds = array_map('intval', $tagIds);

        try {
            $tags = $this->assets->tagDeliverable($id, $tagIds);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }

        Response::success(['tags' => $tags]);
    }

    /**
     * POST /api/v1/admin/assets/tags — create a new tag.
     * Body: { "name": "Brand" }
     */
    public function createTag(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $name = trim((string) $request->input('name', ''));

        try {
            $id = $this->assets->createTag($name);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }

        Response::success(['tag' => ['id' => $id, 'name' => $name]], 201);
    }

    /**
     * GET /api/v1/assets — client's own assets (filtered by auth client_id).
     */
    public function clientAssets(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        $filters = $this->extractFilters($request);
        $filters['client_id'] = $user->id;

        $page    = (int) ($request->query('page') ?: 1);
        $perPage = (int) ($request->query('per_page') ?: 24);

        $result = $this->assets->search($filters, $page, $perPage);

        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    /**
     * GET /api/v1/admin/assets/{id} — single asset detail.
     */
    public function show(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $id    = (int) $vars['id'];
        $asset = $this->assets->getAsset($id);

        if ($asset === null) {
            Response::error('NOT_FOUND', 'Asset not found', 404);
        }

        Response::success(['asset' => $asset]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function extractFilters(Request $request): array
    {
        $filters = [];

        $clientId = $request->query('client_id');
        if ($clientId !== null && $clientId !== '') {
            $filters['client_id'] = (int) $clientId;
        }

        $type = $request->query('type');
        if ($type !== null && $type !== '') {
            $filters['type'] = $type;
        }

        $tagId = $request->query('tag_id');
        if ($tagId !== null && $tagId !== '') {
            $filters['tag_id'] = (int) $tagId;
        }

        $q = $request->query('q');
        if ($q !== null && $q !== '') {
            $filters['q'] = $q;
        }

        return $filters;
    }
}
