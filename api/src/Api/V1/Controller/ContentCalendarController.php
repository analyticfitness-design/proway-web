<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\ContentCalendar\ContentCalendarService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class ContentCalendarController
{
    public function __construct(
        private readonly ContentCalendarService $calendar,
        private readonly AuthMiddleware         $middleware,
    ) {}

    /**
     * GET /api/v1/admin/content-calendar — all slots (?from=&to=&client_id=)
     */
    public function adminIndex(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $from     = trim((string) $request->query('from', date('Y-m-01')));
        $to       = trim((string) $request->query('to', date('Y-m-t')));
        $clientId = $request->query('client_id') ? (int) $request->query('client_id') : null;

        Response::success([
            'slots' => $this->calendar->getAdminCalendar($from, $to, $clientId),
        ]);
    }

    /**
     * GET /api/v1/content-calendar — client's own slots (?horizon=30|60|90)
     */
    public function clientIndex(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        $horizon = (int) ($request->query('horizon') ?: 30);
        if (!in_array($horizon, [30, 60, 90], true)) {
            $horizon = 30;
        }

        Response::success([
            'slots'   => $this->calendar->getClientCalendar($user->id, $horizon),
            'horizon' => $horizon,
        ]);
    }

    /**
     * POST /api/v1/admin/content-calendar — create slot
     */
    public function create(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $data = [
            'client_id'      => (int) $request->input('client_id', 0),
            'project_id'     => $request->input('project_id') ? (int) $request->input('project_id') : null,
            'scheduled_date' => trim((string) $request->input('scheduled_date', '')),
            'content_type'   => trim((string) $request->input('content_type', '')),
            'title'          => trim((string) $request->input('title', '')) ?: null,
            'description'    => trim((string) $request->input('description', '')) ?: null,
            'status'         => trim((string) $request->input('status', 'planned')),
            'platform'       => trim((string) $request->input('platform', '')) ?: null,
        ];

        try {
            $slot = $this->calendar->createSlot($data);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }

        Response::success(['slot' => $slot], 201);
    }

    /**
     * PATCH /api/v1/admin/content-calendar/{id} — update slot
     */
    public function update(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $id   = (int) $vars['id'];
        $body = $request->getBody();

        // Only pass fields that were actually sent
        $data = [];
        $allowed = ['client_id', 'project_id', 'scheduled_date', 'content_type', 'title', 'description', 'status', 'platform'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = $body[$field];
            }
        }

        try {
            $slot = $this->calendar->updateSlot($id, $data);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }

        Response::success(['slot' => $slot]);
    }

    /**
     * DELETE /api/v1/admin/content-calendar/{id} — delete slot
     */
    public function destroy(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $id      = (int) $vars['id'];
        $deleted = $this->calendar->deleteSlot($id);

        if (!$deleted) {
            Response::error('NOT_FOUND', 'Slot no encontrado', 404);
        }

        Response::success(['deleted' => true]);
    }
}
