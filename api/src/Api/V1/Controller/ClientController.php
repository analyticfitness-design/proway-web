<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Client\ClientService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class ClientController
{
    public function __construct(
        private readonly ClientService  $clients,
        private readonly AuthMiddleware $middleware,
    ) {}

    /**
     * GET /api/v1/clients/me
     */
    public function me(Request $request, array $vars): never
    {
        $user   = $this->middleware->requireAuth($request);
        $client = $this->clients->getByCode($user->code);

        if ($client === null) {
            Response::error('NOT_FOUND', 'Client profile not found', 404);
        }

        Response::success(['client' => $client]);
    }

    /**
     * GET /api/v1/clients — Admin only
     */
    public function index(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);
        Response::success(['clients' => $this->clients->getActiveClients()]);
    }

    /**
     * PUT /api/v1/clients/{id}
     * Clients can only update their own profile; admins can update any.
     */
    public function update(Request $request, array $vars): never
    {
        $user     = $this->middleware->requireAuth($request);
        $targetId = (int) $vars['id'];

        // Clients may only update their own record
        if ($user->type === 'client' && $user->id !== $targetId) {
            Response::error('FORBIDDEN', 'Forbidden', 403);
        }

        $allowed = ['nombre', 'telefono', 'objetivo', 'notas'];
        $data    = array_intersect_key($request->getBody(), array_flip($allowed));

        if (empty($data)) {
            Response::error('VALIDATION', 'No valid fields provided', 422);
        }

        $ok = $this->clients->update($targetId, $data);
        Response::success(['updated' => $ok]);
    }
}
